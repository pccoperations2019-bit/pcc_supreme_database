<script setup>
import { displayCenterLabel } from '@/lib/center'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import SearchableSelect from '@/components/SearchableSelect.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Plus, Trash2 } from 'lucide-vue-next'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'

import BudgetUtilization from '@/components/form/BudgetUtilization.vue'
import TrainingRecords from '@/components/form/TrainingRecords.vue'
import PsgcAddressFields from '@/components/PsgcAddressFields.vue'
import { usePsgcAddress } from '@/composable/usePsgcAddress'
import { useMarketingInfrastructureAddress } from '@/composable/useMarketingInfrastructureAddress'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
  projectSite: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
  partners: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  program: { type: String, default: '' },
  dairyBoxes: { type: Array, default: () => [] },
  farmerOrgs: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})

const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')
const isViewMode = computed(() => props.mode === 'view')

const centerOptions = computed(() => {
  const centers = props.centers || []
  return centers.map(center => ({
    value: center.id,
    label: displayCenterLabel(center.code),
  }))
})

const filteredPartners = computed(() => {
  const centerId = Number(form.center_id || 0)
  if (!centerId) return []
  return (props.partners || []).filter(partner => Number(partner.center_id) === centerId)
})

const partnerOptions = computed(() => {
  return filteredPartners.value.map(partner => ({
    value: partner.id,
    label: `${partner.name} `,
  }))
})

const processingPartnerLabel = partnerId => {
  const processingPartner =
    props.projectSite?.processing_partner || props.projectSite?.processingPartner
  const id = String(
    partnerId ?? props.projectSite?.processing_partner_id ?? processingPartner?.id ?? ''
  )
  if (!id) return ''

  return (
    props.partners?.find(partner => String(partner.id) === id)?.name ||
    processingPartner?.name ||
    ''
  )
}

// Farmer Organization suggestions: partner names plus farmer-org names
// already stored in site metadata, deduplicated case-insensitively.
// The stored value is the name string (metadata.partner_farmer_org).
const farmerOrgOptions = computed(() => {
  const seen = new Map()
  const add = name => {
    const trimmed = String(name || '').trim()
    if (trimmed && !seen.has(trimmed.toLowerCase())) {
      seen.set(trimmed.toLowerCase(), trimmed)
    }
  }
  ;(props.partners || []).forEach(partner => add(partner.name))
  ;(props.farmerOrgs || []).forEach(add)
  return [...seen.values()]
    .sort((a, b) => a.localeCompare(b))
    .map(name => ({ value: name, label: name }))
})

const programOptions = computed(() => {
  const uniquePrograms = [
    ...new Set(
      (props.projects || []).map(project => String(project.program || '').trim()).filter(Boolean)
    ),
  ]
  return uniquePrograms.map(value => ({ value, label: value.toUpperCase() }))
})

const selectedProgram = ref(props.program || String(props.projectSite?.project?.program || ''))

const valueChainStatusOptions = computed(() => {
  const statuses = props.enums?.valueChainStatuses || {}
  return Object.entries(statuses).map(([value, label]) => ({ value, label }))
})

const contractTypeOptions = computed(() => {
  const types = props.enums?.contractTypes || {}
  return Object.entries(types).map(([value, label]) => ({ value, label }))
})

const addressPsgc = usePsgcAddress()

const dairyBoxOptions = computed(() => {
  return (props.dairyBoxes || []).map(dairyBox => ({
    value: dairyBox.id,
    label: `${dairyBox.dairy_box_id} - ${dairyBox.business_name}`,
    data: dairyBox,
  }))
})

const dairyBoxStatusOptions = computed(() => {
  const statuses = props.enums?.dairyBoxStatuses || {}
  return Object.entries(statuses).map(([value, label]) => ({ value, label }))
})

// Imported records may carry free-text values (e.g. "1.0", "bidding",
// "Operational") in enum-constrained selects. Map them onto a valid enum
// key (case-insensitive, key or label) or fall back to "" so the record
// can actually be updated instead of 422-ing on submit.
const normalizeEnumValue = (raw, options) => {
  if (raw === null || raw === undefined || raw === '') return ''
  const map = options || {}
  const target = String(raw).trim().toLowerCase()
  return (
    Object.keys(map).find(
      key => key.toLowerCase() === target || String(map[key]).toLowerCase() === target
    ) ?? ''
  )
}

// Mirrors BackedEnumStringCast::get() — resolve to the enum key when the
// value matches, otherwise keep the raw string so imported free-text values
// (e.g. completion "1.0", contract "bidding") still DISPLAY in the form.
// sanitizeEnumValues() maps them back to valid keys-or-null on submit so
// updates never 422 on the backend Rule::in validations.
const castEnumForDisplay = (raw, options) => {
  if (raw === null || raw === undefined) return ''
  const key = normalizeEnumValue(raw, options)
  return key !== '' ? key : String(raw).trim()
}

const isUnmappedEnumValue = (value, options) =>
  value !== null &&
  value !== undefined &&
  String(value).trim() !== '' &&
  normalizeEnumValue(value, options) === ''

const form = useForm({
  // A center admin's site always belongs to their own center; the server pins
  // this too, so the field only has to reflect it.
  center_id: props.projectSite?.center_id || props.defaultCenterId || '',
  production_partner_id: props.projectSite?.production_partner_id || '',
  processing_partner_id: props.projectSite?.processing_partner_id || '',
  project_id:
    props.projectSite?.project_id ||
    (props.mode === 'create' ? props.projects?.find(p => p.program === 'ccdp')?.id || '' : ''),
  partner_farmer_org: props.projectSite?.partner_farmer_org || '',
  value_chain_status: castEnumForDisplay(
    props.projectSite?.value_chain_status,
    props.enums?.valueChainStatuses
  ),
  inauguration_date: props.projectSite?.inauguration_date || '',
  staff_name: props.projectSite?.staff_name || '',
  staff_phone: props.projectSite?.staff_phone || '',
  staff_position: props.projectSite?.staff_position || '',
  type_of_contract: castEnumForDisplay(
    props.projectSite?.type_of_contract,
    props.enums?.contractTypes
  ),
  region: props.projectSite?.address?.region || '',
  province: props.projectSite?.address?.province || '',
  municipality: props.projectSite?.address?.city || '',
  barangay: props.projectSite?.address?.barangay || '',
  district: props.projectSite?.address?.district || '',
  latitude: props.projectSite?.address?.latitude || '',
  longitude: props.projectSite?.address?.longitude || '',
  year: props.projectSite?.year || new Date().getFullYear(),
  productions: props.projectSite?.productions?.length
    ? props.projectSite.productions
    : [
        {
          no_of_animals_to_be_delivered: 0,
          pcc_counterpart: '',
          delivered: 0,
          supplier: '',
          no_of_recipients: 0,
          procuring_agency: '',
          mode_of_procurement: '',
        },
      ],
  processing_infrastructure: (props.projectSite?.processing_infrastructure?.length
    ? props.projectSite.processing_infrastructure
    : [
        {
          processing_partner_id: '',
          type_of_ownership: '',
          region: '',
          province: '',
          municipality: '',
          barangay: '',
          district: '',
          latitude: '',
          longitude: '',
          start_of_construction: '',
          completion_status: '',
          target_date_for_completion: '',
          date_inaugurated: '',
          contractor: '',
        },
      ]
  ).map(infra => ({
    ...infra,
    processing_partner_id:
      infra.processing_partner_id || props.projectSite?.processing_partner_id || '',
    type_of_ownership: castEnumForDisplay(infra.type_of_ownership, props.enums?.ownershipTypes),
    completion_status: castEnumForDisplay(infra.completion_status, props.enums?.dairyBoxStatuses),
    processing_staff: infra.processing_staff?.length
      ? infra.processing_staff
      : [
          {
            staff_name: '',
            contact_number: '',
            number_of_staff: 0,
          },
        ],
  })),
  equipment: (props.projectSite?.equipment?.length
    ? props.projectSite.equipment
    : [
        {
          name_of_equipment: '',
          quantity: '',
          capacity: '',
          unit_cost: '',
          total_cost: '',
          delivery_of_equipment: '',
          date_installed: '',
        },
      ]
  ).map(eq => ({
    ...eq,
    name_of_equipment: castEnumForDisplay(eq.name_of_equipment, props.enums?.equipmentNames),
  })),
  marketing_staff: props.projectSite?.marketing_staff?.length
    ? props.projectSite.marketing_staff
    : [
        {
          staff_name: '',
          contact_number: '',
          number_of_staff: 0,
        },
      ],
  marketing_infrastructure: props.projectSite?.marketing_infrastructure || [
    {
      dairy_box_id: '',
      partner: '',
      business_name: '',
      manager: '',
      region: '',
      province: '',
      municipality: '',
      barangay: '',
      district: '',
      latitude: '',
      longitude: '',
      operational_status: '',
      launched_date: '',
      update_dairy_box_address: false,
    },
  ],
  legal_documents: {
    moa: null,
    dod_usufruct: null,
    management_agreement_contract: null,
    dairy_buffalo_dispersal_agreement: null,
  },
  budget_utilization: props.projectSite?.budget_utilization || {
    budget_allocated: 0,
    obligated: 0,
    disbursed: 0,
    utilization_percentage: 0,
  },
  training_records: props.projectSite?.training_records?.length
    ? props.projectSite.training_records
    : [
        {
          project_orientation_date: '',
          project_orientation_manual_pax: 0,
          strategic_planning_date: '',
          strategic_planning_manual_pax: 0,
          social_preparation_training_date: '',
          social_preparation_training_manual_pax: 0,
          forage_production_training_date: '',
          forage_production_training_manual_pax: 0,
          silage_making_training_date: '',
          silage_making_training_manual_pax: 0,
          animal_health_training_date: '',
          animal_health_training_manual_pax: 0,
          breeding_management_training_date: '',
          breeding_management_training_manual_pax: 0,
          milk_handling_training_date: '',
          milk_handling_training_manual_pax: 0,
          milk_processing_training_date: '',
          milk_processing_training_manual_pax: 0,
          animal_husbandry_training_date: '',
          animal_husbandry_training_manual_pax: 0,
          organic_fertilizer_training_date: '',
          organic_fertilizer_training_manual_pax: 0,
          cooperative_management_training_date: '',
          cooperative_management_training_manual_pax: 0,
          fls_launching_date: '',
          fls_launching_manual_pax: 0,
          fls_buffalo_training_date: '',
          fls_buffalo_training_manual_pax: 0,
          fls_date: '',
          fls_manual_pax: 0,
        },
      ],
})

const normalizeDateForInput = value => {
  if (!value) return ''
  if (typeof value === 'string') return value.slice(0, 10)

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return ''

  return date.toISOString().slice(0, 10)
}

watch(
  () => form.center_id,
  (newCenterId, oldCenterId) => {
    if (String(newCenterId || '') !== String(oldCenterId || '')) {
      form.production_partner_id = ''
      form.processing_partner_id = ''
    }
  }
)

const { copyFlags, onCopyToggle, populateFromDairyBox } = useMarketingInfrastructureAddress({
  form,
  psgc: addressPsgc,
})

watch(
  () => form.marketing_infrastructure.map(mk => mk.dairy_box_id),
  (newIds, oldIds) => {
    newIds.forEach((dairyBoxId, index) => {
      if (dairyBoxId && dairyBoxId !== oldIds?.[index]) {
        const selectedDairyBox = props.dairyBoxes?.find(db => db.id === Number(dairyBoxId))
        populateFromDairyBox(index, selectedDairyBox)
      }
    })
  },
  { deep: true }
)

onMounted(async () => {
  await addressPsgc.load()
})

watch(
  () => props.projectSite,
  site => {
    if (!site) return
    nextTick(() => {
      if (form.inauguration_date) {
        form.inauguration_date = normalizeDateForInput(form.inauguration_date)
      }
      if (Array.isArray(form.processing_infrastructure)) {
        form.processing_infrastructure.forEach(item => {
          item.start_of_construction = normalizeDateForInput(item.start_of_construction)
          item.target_date_for_completion = normalizeDateForInput(item.target_date_for_completion)
          item.date_inaugurated = normalizeDateForInput(item.date_inaugurated)
        })
      }
      if (Array.isArray(form.equipment)) {
        form.equipment.forEach(item => {
          item.delivery_of_equipment = normalizeDateForInput(item.delivery_of_equipment)
          item.date_installed = normalizeDateForInput(item.date_installed)
        })
      }
      if (Array.isArray(form.marketing_infrastructure)) {
        form.marketing_infrastructure.forEach(item => {
          item.launched_date = normalizeDateForInput(item.launched_date)
        })
      }
      if (Array.isArray(form.training_records)) {
        form.training_records.forEach(record => {
          Object.keys(record).forEach(key => {
            if (key.endsWith('_date') && record[key]) {
              record[key] = normalizeDateForInput(record[key])
            }
          })
        })
      }
    })
  },
  { immediate: true, deep: true }
)

const addProduction = () => {
  form.productions.push({
    no_of_animals_to_be_delivered: 0,
    pcc_counterpart: '',
    delivered: 0,
    supplier: '',
    no_of_recipients: 0,
    procuring_agency: '',
    mode_of_procurement: '',
  })
}

const removeProduction = index => {
  form.productions.splice(index, 1)
}

const addProcessingInfrastructure = () => {
  form.processing_infrastructure.push({
    processing_partner_id: '',
    type_of_ownership: '',
    region: '',
    province: '',
    municipality: '',
    barangay: '',
    district: '',
    latitude: '',
    longitude: '',
    start_of_construction: '',
    completion_status: '',
    target_date_for_completion: '',
    date_inaugurated: '',
    contractor: '',
    processing_staff: [
      {
        staff_name: '',
        contact_number: '',
        number_of_staff: 0,
      },
    ],
  })
}

const removeProcessingInfrastructure = index => {
  form.processing_infrastructure.splice(index, 1)
}

const addEquipment = () => {
  form.equipment.push({
    name_of_equipment: '',
    quantity: '',
    capacity: '',
    unit_cost: '',
    total_cost: '',
    delivery_of_equipment: '',
    date_installed: '',
  })
}

const removeEquipment = index => {
  form.equipment.splice(index, 1)
}

const addProcessingStaff = infrastructure => {
  const target = infrastructure || form.processing_infrastructure[0]
  if (!target) return

  target.processing_staff ||= []
  target.processing_staff.push({
    staff_name: '',
    contact_number: '',
    number_of_staff: 0,
  })
}

const removeProcessingStaff = (infrastructureIndex, staffIndex) => {
  form.processing_infrastructure[infrastructureIndex].processing_staff.splice(staffIndex, 1)
}

const addMarketingStaff = () => {
  form.marketing_staff.push({
    staff_name: '',
    contact_number: '',
    number_of_staff: 0,
  })
}

const removeMarketingStaff = index => {
  form.marketing_staff.splice(index, 1)
}

const addMarketingInfrastructure = () => {
  form.marketing_infrastructure.push({
    dairy_box_id: '',
    partner: '',
    business_name: '',
    manager: '',
    region: '',
    province: '',
    municipality: '',
    barangay: '',
    district: '',
    latitude: '',
    longitude: '',
    operational_status: '',
    launched_date: '',
    update_dairy_box_address: false,
  })
}

const removeMarketingInfrastructure = index => {
  form.marketing_infrastructure.splice(index, 1)
}

const addTrainingRecord = () => {
  form.training_records.push({
    project_orientation_date: '',
    project_orientation_manual_pax: 0,
    strategic_planning_date: '',
    strategic_planning_manual_pax: 0,
    social_preparation_training_date: '',
    social_preparation_training_manual_pax: 0,
    forage_production_training_date: '',
    forage_production_training_manual_pax: 0,
    silage_making_training_date: '',
    silage_making_training_manual_pax: 0,
    animal_health_training_date: '',
    animal_health_training_manual_pax: 0,
    breeding_management_training_date: '',
    breeding_management_training_manual_pax: 0,
    milk_handling_training_date: '',
    milk_handling_training_manual_pax: 0,
    milk_processing_training_date: '',
    milk_processing_training_manual_pax: 0,
    animal_husbandry_training_date: '',
    animal_husbandry_training_manual_pax: 0,
    organic_fertilizer_training_date: '',
    organic_fertilizer_training_manual_pax: 0,
    cooperative_management_training_date: '',
    cooperative_management_training_manual_pax: 0,
    fls_launching_date: '',
    fls_launching_manual_pax: 0,
    fls_buffalo_training_date: '',
    fls_buffalo_training_manual_pax: 0,
    fls_date: '',
    fls_manual_pax: 0,
  })
}

const removeTrainingRecord = index => {
  form.training_records.splice(index, 1)
}

const sanitizeEnumValues = data => {
  const enums = props.enums || {}
  return {
    ...data,
    value_chain_status:
      normalizeEnumValue(data.value_chain_status, enums.valueChainStatuses) || null,
    type_of_contract: normalizeEnumValue(data.type_of_contract, enums.contractTypes) || null,
    processing_infrastructure: (data.processing_infrastructure || []).map(infra => ({
      ...infra,
      type_of_ownership: normalizeEnumValue(infra.type_of_ownership, enums.ownershipTypes) || null,
      completion_status:
        normalizeEnumValue(infra.completion_status, enums.dairyBoxStatuses) || null,
    })),
    equipment: (data.equipment || []).map(eq => ({
      ...eq,
      name_of_equipment: normalizeEnumValue(eq.name_of_equipment, enums.equipmentNames) || null,
    })),
  }
}

const submit = () => {
  const syncPartner = data => ({
    ...sanitizeEnumValues(data),
    processing_partner_id:
      data.processing_infrastructure?.[0]?.processing_partner_id ||
      data.processing_partner_id ||
      null,
  })
  const data = syncPartner(form.data())
  if (isEditMode.value) {
    router.put(route('ccdp-project-sites.update', props.projectSite?.id), data, {
      forceFormData: true,
      onError: errors => {
        console.log('Update errors:', errors)
      },
      onSuccess: () => {
        console.log('Update successful')
      },
    })
  } else {
    router.post(route('ccdp-project-sites.store'), data, {
      forceFormData: true,
      onError: errors => {
        console.log('Create errors:', errors)
      },
      onSuccess: () => {
        console.log('Create successful')
      },
    })
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <Card>
      <CardHeader>
        <div class="flex items-start justify-between gap-3">
          <div>
            <CardTitle>
              {{
                isViewMode
                  ? 'CCDP Project Site Details'
                  : isEditMode
                    ? 'Edit CCDP Project Site'
                    : 'Create CCDP Project Site'
              }}
            </CardTitle>
            <CardDescription
              >Fill in project site information and all related details</CardDescription
            >
          </div>
          <div class="flex items-center gap-2">
            <Button
              v-if="isViewMode"
              variant="outline"
              type="button"
              @click="router.visit(route('ccdp-project-sites.edit', props.projectSite?.id))"
            >
              Edit
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <form class="flex flex-col gap-6" @submit.prevent="submit">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Project Details</CardTitle>
              <CardDescription
                >Project, partner, budget, and production information</CardDescription
              >
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div v-if="!disableCenterField || isViewMode" class="space-y-2">
                <Label for="center_id">Center</Label>
                <Select v-if="!isViewMode" v-model="form.center_id">
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select center" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="center in centerOptions"
                      :key="center.value"
                      :value="center.value"
                      >{{ center.label }}</SelectItem
                    >
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.projectSite?.center?.name || 'N/A' }}
                </div>
                <p v-if="form.errors.center_id" class="text-sm text-red-500">
                  {{ form.errors.center_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="project_program">Project</Label>
                <Select v-if="!isViewMode" v-model="selectedProgram" :disabled="!!program">
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select project" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="program in programOptions"
                      :key="program.value"
                      :value="program.value"
                      >{{ program.label }}</SelectItem
                    >
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.projectSite?.project?.program || 'N/A' }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="year">Year</Label>
                <Input
                  v-if="!isViewMode"
                  id="year"
                  v-model.number="form.year"
                  type="number"
                  min="2000"
                  max="2100"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ form.year || 'N/A' }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="partner_farmer_org">Partner Farmer Organization</Label>
                <SearchableSelect
                  v-if="!isViewMode"
                  v-model="form.partner_farmer_org"
                  :options="farmerOrgOptions"
                  :limit="100"
                  placeholder="Select partner farmer organization"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.projectSite?.partner_farmer_org || 'N/A' }}
                </div>
                <p v-if="form.errors.partner_farmer_org" class="text-sm text-red-500">
                  {{ form.errors.partner_farmer_org }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="type_of_contract">Type of Contract</Label>
                <Select v-if="!isViewMode" v-model="form.type_of_contract">
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select contract type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="contract in contractTypeOptions"
                      :key="contract.value"
                      :value="contract.value"
                      >{{ contract.label }}</SelectItem
                    >
                  </SelectContent>
                </Select>
                <p
                  v-if="
                    !isViewMode &&
                    isUnmappedEnumValue(form.type_of_contract, props.enums?.contractTypes)
                  "
                  class="text-xs text-amber-600"
                >
                  Imported value "{{ form.type_of_contract }}" is not a standard option — select one
                  to update.
                </p>
                <div v-else-if="isViewMode" class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    props.enums.contractTypes?.[form.type_of_contract] ||
                    form.type_of_contract ||
                    'N/A'
                  }}
                </div>
              </div>

              <div class="space-y-2 md:col-span-2">
                <PsgcAddressFields
                  :model-value="form"
                  :is-view-mode="isViewMode"
                  city-field="municipality"
                  @update:model-value="Object.assign(form, $event)"
                />
              </div>

              <div v-if="!isCreateMode" class="space-y-2">
                <Label for="latitude">Latitude</Label>
                <Input
                  v-if="!isViewMode"
                  id="latitude"
                  v-model="form.latitude"
                  placeholder="e.g. 14.0725"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ form.latitude || 'N/A' }}
                </div>
              </div>

              <div v-if="!isCreateMode" class="space-y-2">
                <Label for="longitude">Longitude</Label>
                <Input
                  v-if="!isViewMode"
                  id="longitude"
                  v-model="form.longitude"
                  placeholder="e.g. 121.3251"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ form.longitude || 'N/A' }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="value_chain_status">Value Chain Status</Label>
                <Select v-if="!isViewMode" v-model="form.value_chain_status">
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="status in valueChainStatusOptions"
                      :key="status.value"
                      :value="status.value"
                      >{{ status.label }}</SelectItem
                    >
                  </SelectContent>
                </Select>
                <p
                  v-if="
                    !isViewMode &&
                    isUnmappedEnumValue(form.value_chain_status, props.enums?.valueChainStatuses)
                  "
                  class="text-xs text-amber-600"
                >
                  Imported value "{{ form.value_chain_status }}" is not a standard option — select
                  one to update.
                </p>
                <div v-else-if="isViewMode" class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.projectSite?.value_chain_status || 'N/A' }}
                </div>
                <p v-if="form.errors.value_chain_status" class="text-sm text-red-500">
                  {{ form.errors.value_chain_status }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="inauguration_date">Inauguration Date</Label>
                <Input
                  v-if="!isViewMode"
                  id="inauguration_date"
                  v-model="form.inauguration_date"
                  type="date"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ form.inauguration_date || 'N/A' }}
                </div>
                <p v-if="form.errors.inauguration_date" class="text-sm text-red-500">
                  {{ form.errors.inauguration_date }}
                </p>
              </div>

              <div v-if="isEditMode" class="space-y-2">
                <Label for="staff_name">Staff Name</Label>
                <Input
                  v-if="!isViewMode"
                  id="staff_name"
                  v-model="form.staff_name"
                  placeholder="Enter staff name"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.projectSite?.staff_name || 'N/A' }}
                </div>
              </div>

              <div v-if="isEditMode" class="space-y-2">
                <Label for="staff_phone">Staff Phone</Label>
                <Input
                  v-if="!isViewMode"
                  id="staff_phone"
                  v-model="form.staff_phone"
                  placeholder="Enter staff phone"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.projectSite?.staff_phone || 'N/A' }}
                </div>
              </div>

              <div v-if="isEditMode" class="space-y-2">
                <Label for="staff_position">Staff Position</Label>
                <Input
                  v-if="!isViewMode"
                  id="staff_position"
                  v-model="form.staff_position"
                  placeholder="Enter staff position"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.projectSite?.staff_position || 'N/A' }}
                </div>
              </div>
            </CardContent>
          </Card>

          <BudgetUtilization v-model="form.budget_utilization" :is-view-mode="isViewMode" />

          <Card>
            <CardHeader>
              <div class="flex items-start justify-between gap-3">
                <div>
                  <CardTitle class="text-base">Production</CardTitle>
                  <CardDescription>Animal procurement and delivery details</CardDescription>
                </div>
                <Button
                  v-if="!isViewMode"
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="addProduction"
                >
                  <Plus class="h-4 w-4" /> Add Production
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div v-for="(production, index) in form.productions" :key="index" class="mb-6">
                <div class="mb-4 flex items-center justify-between">
                  <Button
                    v-if="!isViewMode && form.productions.length > 1"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="removeProduction(index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div v-if="index === 0" class="space-y-2">
                    <Label for="production_partner_id">Partner</Label>
                    <SearchableSelect
                      v-if="!isViewMode"
                      v-model="form.production_partner_id"
                      :options="partnerOptions"
                      :disabled="!form.center_id"
                      placeholder="Select partner"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ props.projectSite?.partner?.name || 'N/A' }}
                    </div>
                    <p v-if="form.errors.production_partner_id" class="text-sm text-red-500">
                      {{ form.errors.production_partner_id }}
                    </p>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`productions.${index}.mode_of_procurement`"
                      >Mode of Procurement</Label
                    >
                    <Input
                      v-if="!isViewMode"
                      :id="`productions.${index}.mode_of_procurement`"
                      v-model="production.mode_of_procurement"
                      placeholder="Enter mode of procurement"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ production.mode_of_procurement || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`productions.${index}.no_of_animals_to_be_delivered`"
                      >No. of Animals to be Delivered</Label
                    >
                    <Input
                      v-if="!isViewMode"
                      :id="`productions.${index}.no_of_animals_to_be_delivered`"
                      v-model.number="production.no_of_animals_to_be_delivered"
                      type="number"
                      min="0"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ production.no_of_animals_to_be_delivered || 0 }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`productions.${index}.pcc_counterpart`">PCC Counterpart</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`productions.${index}.pcc_counterpart`"
                      v-model="production.pcc_counterpart"
                      placeholder="Enter PCC counterpart"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ production.pcc_counterpart || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`productions.${index}.delivered`">Delivered</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`productions.${index}.delivered`"
                      v-model.number="production.delivered"
                      type="number"
                      min="0"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ production.delivered || 0 }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`productions.${index}.supplier`">Supplier</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`productions.${index}.supplier`"
                      v-model="production.supplier"
                      placeholder="Enter supplier"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ production.supplier || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`productions.${index}.no_of_recipients`">No. of Recipients</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`productions.${index}.no_of_recipients`"
                      v-model.number="production.no_of_recipients"
                      type="number"
                      min="0"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ production.no_of_recipients || 0 }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`productions.${index}.procuring_agency`">Procuring Agency</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`productions.${index}.procuring_agency`"
                      v-model="production.procuring_agency"
                      placeholder="Enter procuring agency"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ production.procuring_agency || 'N/A' }}
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <div class="flex items-start justify-between gap-3">
                <div>
                  <CardTitle class="text-base">Processing Infrastructure</CardTitle>
                  <CardDescription>Building and facility details</CardDescription>
                </div>
                <Button
                  v-if="!isViewMode"
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="addProcessingInfrastructure"
                >
                  <Plus class="h-4 w-4" /> Add Processing Infrastructure
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div
                v-for="(infra, index) in form.processing_infrastructure"
                :key="index"
                class="mb-6"
              >
                <div class="mb-4 flex items-center justify-between">
                  <Button
                    v-if="!isViewMode && form.processing_infrastructure.length > 1"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="removeProcessingInfrastructure(index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label :for="`pi.${index}.processing_partner_id`">Partner</Label>
                    <SearchableSelect
                      v-if="!isViewMode"
                      v-model="infra.processing_partner_id"
                      :options="partnerOptions"
                      :disabled="!form.center_id"
                      placeholder="Select partner"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{
                        processingPartnerLabel(infra.processing_partner_id) ||
                        'N/A'
                      }}
                    </div>
                    <p
                      v-if="form.errors[`processing_infrastructure.${index}.processing_partner_id`]"
                      class="text-sm text-red-500"
                    >
                      {{ form.errors[`processing_infrastructure.${index}.processing_partner_id`] }}
                    </p>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`pi.${index}.type_of_ownership`">Type of Ownership</Label>
                    <Select
                      v-if="!isViewMode"
                      :model-value="infra.type_of_ownership"
                      @update:model-value="infra.type_of_ownership = $event"
                    >
                      <SelectTrigger :id="`pi.${index}.type_of_ownership`" class="h-9 w-full">
                        <SelectValue placeholder="Select type of ownership" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem
                          v-for="(label, value) in props.enums.ownershipTypes"
                          :key="value"
                          :value="value"
                          >{{ label }}</SelectItem
                        >
                      </SelectContent>
                    </Select>
                    <p
                      v-if="
                        !isViewMode &&
                        isUnmappedEnumValue(infra.type_of_ownership, props.enums?.ownershipTypes)
                      "
                      class="text-xs text-amber-600"
                    >
                      Imported value "{{ infra.type_of_ownership }}" is not a standard option —
                      select one to update.
                    </p>
                    <div v-else-if="isViewMode" class="rounded bg-gray-50 p-2 text-sm">
                      {{
                        props.enums.ownershipTypes?.[infra.type_of_ownership] ||
                        infra.type_of_ownership ||
                        'N/A'
                      }}
                    </div>
                  </div>
                  <div class="space-y-2 md:col-span-2">
                    <PsgcAddressFields
                      :model-value="form.processing_infrastructure[index]"
                      :is-view-mode="isViewMode"
                      city-field="municipality"
                      @update:model-value="form.processing_infrastructure[index] = $event"
                    />
                  </div>
                  <div v-if="!isCreateMode" class="space-y-2">
                    <Label :for="`pi.${index}.latitude`">Latitude</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`pi.${index}.latitude`"
                      v-model="infra.latitude"
                      placeholder="e.g. 14.0725"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ infra.latitude || 'N/A' }}
                    </div>
                  </div>
                  <div v-if="!isCreateMode" class="space-y-2">
                    <Label :for="`pi.${index}.longitude`">Longitude</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`pi.${index}.longitude`"
                      v-model="infra.longitude"
                      placeholder="e.g. 121.3251"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ infra.longitude || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`pi.${index}.start_of_construction`">Start of Construction</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`pi.${index}.start_of_construction`"
                      v-model="infra.start_of_construction"
                      type="date"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ infra.start_of_construction || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`pi.${index}.completion_status`">Completion Status</Label>
                    <Select
                      v-if="!isViewMode"
                      :model-value="infra.completion_status"
                      @update:model-value="infra.completion_status = $event"
                    >
                      <SelectTrigger :id="`pi.${index}.completion_status`" class="h-9 w-full">
                        <SelectValue placeholder="Select completion status" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem
                          v-for="opt in dairyBoxStatusOptions"
                          :key="opt.value"
                          :value="opt.value"
                          >{{ opt.label }}</SelectItem
                        >
                      </SelectContent>
                    </Select>
                    <p
                      v-if="
                        !isViewMode &&
                        isUnmappedEnumValue(infra.completion_status, props.enums?.dairyBoxStatuses)
                      "
                      class="text-xs text-amber-600"
                    >
                      Imported value "{{ infra.completion_status }}" is not a standard option —
                      select one to update.
                    </p>
                    <div v-else-if="isViewMode" class="rounded bg-gray-50 p-2 text-sm">
                      {{
                        props.enums.dairyBoxStatuses?.[infra.completion_status] ||
                        infra.completion_status ||
                        'N/A'
                      }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`pi.${index}.target_date_for_completion`"
                      >Target Date for Completion</Label
                    >
                    <Input
                      v-if="!isViewMode"
                      :id="`pi.${index}.target_date_for_completion`"
                      v-model="infra.target_date_for_completion"
                      type="date"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ infra.target_date_for_completion || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`pi.${index}.date_inaugurated`">Date Inaugurated</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`pi.${index}.date_inaugurated`"
                      v-model="infra.date_inaugurated"
                      type="date"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ infra.date_inaugurated || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`pi.${index}.contractor`">Contractor</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`pi.${index}.contractor`"
                      v-model="infra.contractor"
                      placeholder="Enter contractor name"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ infra.contractor || 'N/A' }}
                    </div>
                  </div>
                </div>
                <!-- Staff directly under this processing infra — was detached at bottom, now nested per infra -->
                <div class="mt-8 border-t pt-6">
                  <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                      <h3 class="text-base font-medium">Processing Staff</h3>
                      <p class="muted-foreground text-sm">
                        Staff assigned to this processing facility — added with Processing
                        Infrastructure
                      </p>
                    </div>
                    <Button
                      v-if="!isViewMode"
                      type="button"
                      variant="outline"
                      size="sm"
                      @click="addProcessingStaff(infra)"
                    >
                      <Plus class="h-4 w-4" /> Add Staff
                    </Button>
                  </div>
                  <div
                    v-for="(staff, staffIndex) in infra.processing_staff || []"
                    :key="staffIndex"
                    class="mb-4"
                  >
                    <div class="mb-2 flex justify-end">
                      <Button
                        v-if="!isViewMode && infra.processing_staff.length > 1"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="removeProcessingStaff(index, staffIndex)"
                      >
                        <Trash2 class="h-4 w-4" /> Remove Staff
                      </Button>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                      <div class="space-y-2">
                        <Label :for="`processing-staff-${index}-${staffIndex}-name`"
                          >Manager Name</Label
                        >
                        <Input
                          v-if="!isViewMode"
                          :id="`processing-staff-${index}-${staffIndex}-name`"
                          v-model="staff.staff_name"
                          placeholder="Enter staff name"
                        />
                        <div v-else class="rounded bg-gray-50 p-2 text-sm">
                          {{ staff.staff_name || 'N/A' }}
                        </div>
                      </div>
                      <div class="space-y-2">
                        <Label :for="`processing-staff-${index}-${staffIndex}-contact`"
                          >Contact Number</Label
                        >
                        <Input
                          v-if="!isViewMode"
                          :id="`processing-staff-${index}-${staffIndex}-contact`"
                          v-model="staff.contact_number"
                          type="text"
                          inputmode="numeric"
                          pattern="[0-9]*"
                          maxlength="11"
                          oninput="this.value=this.value.replace(/\D/g,'')"
                          placeholder="Enter contact number"
                        />
                        <div v-else class="rounded bg-gray-50 p-2 text-sm">
                          {{ staff.contact_number || 'N/A' }}
                        </div>
                      </div>
                      <div class="space-y-2">
                        <Label :for="`processing-staff-${index}-${staffIndex}-count`"
                          >Number of Processing Staff</Label
                        >
                        <Input
                          v-if="!isViewMode"
                          :id="`processing-staff-${index}-${staffIndex}-count`"
                          v-model.number="staff.number_of_staff"
                          type="number"
                          min="0"
                        />
                        <div v-else class="rounded bg-gray-50 p-2 text-sm">
                          {{ staff.number_of_staff || 0 }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <div class="flex items-start justify-between gap-3">
                <div>
                  <CardTitle class="text-base">Marketing Infrastructure</CardTitle>
                  <CardDescription>Marketing facility physical building details</CardDescription>
                </div>
                <Button
                  v-if="!isViewMode"
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="addMarketingInfrastructure"
                >
                  <Plus class="h-4 w-4" /> Add Marketing Infrastructure
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div v-for="(mk, index) in form.marketing_infrastructure" :key="index" class="mb-6">
                <div class="mb-4 flex items-center justify-between">
                  <Button
                    v-if="!isViewMode && form.marketing_infrastructure.length > 1"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="removeMarketingInfrastructure(index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div v-if="!isViewMode" class="flex items-center gap-2 md:col-span-2">
                    <input
                      :id="`mk.${index}.copy_processing_address`"
                      type="checkbox"
                      class="text-primary accent-primary h-4 w-4 rounded border-gray-300"
                      :checked="copyFlags[index]"
                      @change="onCopyToggle(index, $event.target.checked)"
                    />
                    <Label
                      :for="`mk.${index}.copy_processing_address`"
                      class="cursor-pointer text-sm font-normal text-gray-600"
                    >
                      Copy address from Processing Infrastructure
                    </Label>
                  </div>
                  <div class="space-y-2 md:col-span-2">
                    <Label :for="`mk.${index}.dairy_box_id`">Dairybox</Label>
                    <SearchableSelect
                      v-if="!isViewMode"
                      :id="`mk.${index}.dairy_box_id`"
                      v-model="mk.dairy_box_id"
                      :options="dairyBoxOptions"
                      placeholder="Select dairybox"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{
                        dairyBoxOptions.find(opt => opt.value === mk.dairy_box_id)?.label || 'N/A'
                      }}
                    </div>
                  </div>
                  <div class="space-y-2 md:col-span-2">
                    <div class="flex items-center justify-between">
                      <Label>Dairybox Address</Label>
                      <span
                        v-if="!isViewMode && copyFlags[index]"
                        class="text-xs font-medium text-amber-600"
                      >
                        ⚠ Dairybox address will be updated on save
                      </span>
                    </div>
                    <PsgcAddressFields
                      :key="`${mk.dairy_box_id}-${mk.region}-${mk.province}-${mk.municipality}`"
                      class="md:col-span-2"
                      :model-value="form.marketing_infrastructure[index]"
                      :is-view-mode="isViewMode"
                      city-field="municipality"
                      value-mode="code"
                      @update:model-value="form.marketing_infrastructure[index] = $event"
                    />
                  </div>
                  <div class="space-y-2">
                    <Label :for="`mk.${index}.partner`">Partner</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.partner`"
                      v-model="mk.partner"
                      disabled
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.partner || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`mk.${index}.business_name`">Business Name</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.business_name`"
                      v-model="mk.business_name"
                      disabled
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.business_name || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`mk.${index}.manager`">Manager</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.manager`"
                      v-model="mk.manager"
                      disabled
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.manager || 'N/A' }}
                    </div>
                  </div>
                  <div v-if="!isCreateMode" class="space-y-2">
                    <Label :for="`mk.${index}.latitude`">Latitude</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.latitude`"
                      v-model="mk.latitude"
                      disabled
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.latitude || 'N/A' }}
                    </div>
                  </div>
                  <div v-if="!isCreateMode" class="space-y-2">
                    <Label :for="`mk.${index}.longitude`">Longitude</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.longitude`"
                      v-model="mk.longitude"
                      disabled
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.longitude || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`mk.${index}.operational_status`">Operational Status</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.operational_status`"
                      v-model="mk.operational_status"
                      disabled
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.operational_status || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`mk.${index}.launched_date`">Launched Date</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.launched_date`"
                      v-model="mk.launched_date"
                      type="date"
                      disabled
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.launched_date || 'N/A' }}
                    </div>
                  </div>
                </div>
              </div>
              <div class="mt-8 border-t pt-6">
                <div class="mb-4 flex items-start justify-between gap-3">
                  <div>
                    <h3 class="text-base font-medium">Marketing Staff</h3>
                    <p class="muted-foreground text-sm">
                      Staff assigned to this marketing facility
                    </p>
                  </div>
                  <Button
                    v-if="!isViewMode"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addMarketingStaff"
                  >
                    <Plus class="h-4 w-4" /> Add Marketing Staff
                  </Button>
                </div>
                <div
                  v-for="(staff, staffIndex) in form.marketing_staff"
                  :key="staffIndex"
                  class="mb-4"
                >
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                      <Label :for="`marketing-staff-${staffIndex}-name`">Manager Name</Label>
                      <Input
                        v-if="!isViewMode"
                        :id="`marketing-staff-${staffIndex}-name`"
                        v-model="staff.staff_name"
                        placeholder="Enter manager name"
                      />
                      <div v-else class="rounded bg-gray-50 p-2 text-sm">
                        {{ staff.staff_name || 'N/A' }}
                      </div>
                    </div>
                    <div class="space-y-2">
                      <Label :for="`marketing-staff-${staffIndex}-contact`">Contact Number</Label>
                      <Input
                        v-if="!isViewMode"
                        :id="`marketing-staff-${staffIndex}-contact`"
                        v-model="staff.contact_number"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="11"
                        oninput="this.value=this.value.replace(/\D/g,'')"
                        placeholder="Enter contact number"
                      />
                      <div v-else class="rounded bg-gray-50 p-2 text-sm">
                        {{ staff.contact_number || 'N/A' }}
                      </div>
                    </div>
                    <div class="space-y-2">
                      <Label :for="`marketing-staff-${staffIndex}-count`"
                        >Number of Marketing Staff</Label
                      >
                      <Input
                        v-if="!isViewMode"
                        :id="`marketing-staff-${staffIndex}-count`"
                        v-model.number="staff.number_of_staff"
                        type="number"
                        min="0"
                      />
                      <div v-else class="rounded bg-gray-50 p-2 text-sm">
                        {{ staff.number_of_staff || 0 }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <div class="flex items-start justify-between gap-3">
                <div>
                  <CardTitle class="text-base">Equipment</CardTitle>
                  <CardDescription>Equipment and machinery delivered and installed</CardDescription>
                </div>
                <Button
                  v-if="!isViewMode"
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="addEquipment"
                >
                  <Plus class="h-4 w-4" /> Add Equipment
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div v-for="(equip, index) in form.equipment" :key="index" class="mb-6">
                <div class="mb-4 flex items-center justify-between">
                  <Button
                    v-if="!isViewMode && form.equipment.length > 1"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="removeEquipment(index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label :for="`equip.${index}.name_of_equipment`">Equipment</Label>
                    <Select
                      v-if="!isViewMode"
                      :model-value="equip.name_of_equipment"
                      @update:model-value="equip.name_of_equipment = $event"
                    >
                      <SelectTrigger class="h-9 w-full">
                        <SelectValue placeholder="Select equipment" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem
                          v-for="(label, key) in props.enums.equipmentNames"
                          :key="key"
                          :value="key"
                          >{{ label }}</SelectItem
                        >
                      </SelectContent>
                    </Select>
                    <p
                      v-if="
                        !isViewMode &&
                        isUnmappedEnumValue(equip.name_of_equipment, props.enums?.equipmentNames)
                      "
                      class="text-xs text-amber-600"
                    >
                      Imported value "{{ equip.name_of_equipment }}" is not a standard option —
                      select one to update.
                    </p>
                    <div v-else-if="isViewMode" class="rounded bg-gray-50 p-2 text-sm">
                      {{
                        props.enums.equipmentNames?.[equip.name_of_equipment] ||
                        equip.name_of_equipment ||
                        'N/A'
                      }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`equip.${index}.quantity`">Quantity</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`equip.${index}.quantity`"
                      v-model.number="equip.quantity"
                      type="number"
                      min="0"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ equip.quantity ?? 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`equip.${index}.capacity`">Capacity</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`equip.${index}.capacity`"
                      v-model="equip.capacity"
                      placeholder="e.g. 150 Liters"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ equip.capacity || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`equip.${index}.unit_cost`">Unit Cost</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`equip.${index}.unit_cost`"
                      v-model.number="equip.unit_cost"
                      type="number"
                      min="0"
                      step="0.01"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ equip.unit_cost ?? 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`equip.${index}.total_cost`">Total Cost</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`equip.${index}.total_cost`"
                      v-model.number="equip.total_cost"
                      type="number"
                      min="0"
                      step="0.01"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ equip.total_cost ?? 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`equip.${index}.delivery_of_equipment`"
                      >Delivery of Equipment</Label
                    >
                    <Input
                      v-if="!isViewMode"
                      :id="`equip.${index}.delivery_of_equipment`"
                      v-model="equip.delivery_of_equipment"
                      type="date"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ equip.delivery_of_equipment || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`equip.${index}.date_installed`">Date Installed</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`equip.${index}.date_installed`"
                      v-model="equip.date_installed"
                      type="date"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ equip.date_installed || 'N/A' }}
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <div>
                <CardTitle class="text-base">Legal Documents</CardTitle>
                <CardDescription>Legal agreements and contracts</CardDescription>
              </div>
            </CardHeader>
            <CardContent>
              <!-- Column headers -->
              <div
                class="text-muted-foreground hidden grid-cols-2 gap-4 border-b pb-2 text-sm font-medium md:grid"
              >
                <div>Docs name</div>
                <div>File attach</div>
              </div>

              <!-- MOA -->
              <div
                class="grid grid-cols-1 items-start gap-2 border-b py-3 last:border-b-0 md:grid-cols-2 md:gap-4"
              >
                <div class="min-w-0 space-y-1">
                  <Label for="legal_moa">MOA</Label>
                  <p
                    v-if="!isViewMode && props.projectSite?.legal_document_urls?.moa"
                    class="text-muted-foreground text-xs"
                  >
                    Current:
                    <a
                      :href="props.projectSite.legal_document_urls.moa"
                      target="_blank"
                      class="text-primary underline"
                      >view file</a
                    >
                  </p>
                  <p v-if="form.errors['legal_documents.moa']" class="text-sm text-red-500">
                    {{ form.errors['legal_documents.moa'] }}
                  </p>
                </div>
                <div class="min-w-0">
                  <input
                    v-if="!isViewMode"
                    id="legal_moa"
                    type="file"
                    accept=".pdf"
                    class="order-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring block h-9 w-full min-w-0 rounded-md border px-3 py-1.5 text-sm shadow-sm transition-colors file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:ring-1 focus-visible:outline-none"
                    @change="form.legal_documents.moa = $event.target.files[0] ?? null"
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    <a
                      v-if="props.projectSite?.legal_document_urls?.moa"
                      :href="props.projectSite.legal_document_urls.moa"
                      target="_blank"
                      class="text-primary underline"
                      >View MOA</a
                    >
                    <span v-else class="text-muted-foreground">No file uploaded</span>
                  </div>
                </div>
              </div>
              <!-- DOD / USUFRUCT -->
              <div
                class="grid grid-cols-1 items-start gap-2 border-b py-3 last:border-b-0 md:grid-cols-2 md:gap-4"
              >
                <div class="min-w-0 space-y-1">
                  <Label for="legal_dod">DOD / USUFRUCT</Label>
                  <p
                    v-if="!isViewMode && props.projectSite?.legal_document_urls?.dod_usufruct"
                    class="text-muted-foreground text-xs"
                  >
                    Current:
                    <a
                      :href="props.projectSite.legal_document_urls.dod_usufruct"
                      target="_blank"
                      class="text-primary underline"
                      >view file</a
                    >
                  </p>
                  <p
                    v-if="form.errors['legal_documents.dod_usufruct']"
                    class="text-sm text-red-500"
                  >
                    {{ form.errors['legal_documents.dod_usufruct'] }}
                  </p>
                </div>
                <div class="min-w-0">
                  <input
                    v-if="!isViewMode"
                    id="legal_dod"
                    type="file"
                    accept=".pdf"
                    class="order-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring block h-9 w-full min-w-0 rounded-md border px-3 py-1.5 text-sm shadow-sm transition-colors file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:ring-1 focus-visible:outline-none"
                    @change="form.legal_documents.dod_usufruct = $event.target.files[0] ?? null"
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    <a
                      v-if="props.projectSite?.legal_document_urls?.dod_usufruct"
                      :href="props.projectSite.legal_document_urls.dod_usufruct"
                      target="_blank"
                      class="text-primary underline"
                      >View DOD / USUFRUCT</a
                    >
                    <span v-else class="text-muted-foreground">No file uploaded</span>
                  </div>
                </div>
              </div>
              <!-- Management Agreement Contract -->
              <div
                class="grid grid-cols-1 items-start gap-2 border-b py-3 last:border-b-0 md:grid-cols-2 md:gap-4"
              >
                <div class="min-w-0 space-y-1">
                  <Label for="legal_management_agreement">Management Agreement Contract</Label>
                  <p
                    v-if="
                      !isViewMode &&
                      props.projectSite?.legal_document_urls?.management_agreement_contract
                    "
                    class="text-muted-foreground text-xs"
                  >
                    Current:
                    <a
                      :href="props.projectSite.legal_document_urls.management_agreement_contract"
                      target="_blank"
                      class="text-primary underline"
                      >view file</a
                    >
                  </p>
                  <p
                    v-if="form.errors['legal_documents.management_agreement_contract']"
                    class="text-sm text-red-500"
                  >
                    {{ form.errors['legal_documents.management_agreement_contract'] }}
                  </p>
                </div>
                <div class="min-w-0">
                  <input
                    v-if="!isViewMode"
                    id="legal_management_agreement"
                    type="file"
                    accept=".pdf"
                    class="order-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring block h-9 w-full min-w-0 rounded-md border px-3 py-1.5 text-sm shadow-sm transition-colors file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:ring-1 focus-visible:outline-none"
                    @change="
                      form.legal_documents.management_agreement_contract =
                        $event.target.files[0] ?? null
                    "
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    <a
                      v-if="props.projectSite?.legal_document_urls?.management_agreement_contract"
                      :href="props.projectSite.legal_document_urls.management_agreement_contract"
                      target="_blank"
                      class="text-primary underline"
                      >View Management Agreement Contract</a
                    >
                    <span v-else class="text-muted-foreground">No file uploaded</span>
                  </div>
                </div>
              </div>
              <!-- Dairy Buffalo Dispersal Agreement -->
              <div
                class="grid grid-cols-1 items-start gap-2 py-3 last:border-b-0 md:grid-cols-2 md:gap-4"
              >
                <div class="min-w-0 space-y-1">
                  <Label for="legal_dairy_dispersal">Dairy Buffalo Dispersal Agreement</Label>
                  <p
                    v-if="
                      !isViewMode &&
                      props.projectSite?.legal_document_urls?.dairy_buffalo_dispersal_agreement
                    "
                    class="text-muted-foreground text-xs"
                  >
                    Current:
                    <a
                      :href="
                        props.projectSite.legal_document_urls.dairy_buffalo_dispersal_agreement
                      "
                      target="_blank"
                      class="text-primary underline"
                      >view file</a
                    >
                  </p>
                  <p
                    v-if="form.errors['legal_documents.dairy_buffalo_dispersal_agreement']"
                    class="text-sm text-red-500"
                  >
                    {{ form.errors['legal_documents.dairy_buffalo_dispersal_agreement'] }}
                  </p>
                </div>
                <div class="min-w-0">
                  <input
                    v-if="!isViewMode"
                    id="legal_dairy_dispersal"
                    type="file"
                    accept=".pdf"
                    class="order-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring block h-9 w-full min-w-0 rounded-md border px-3 py-1.5 text-sm shadow-sm transition-colors file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:ring-1 focus-visible:outline-none"
                    @change="
                      form.legal_documents.dairy_buffalo_dispersal_agreement =
                        $event.target.files[0] ?? null
                    "
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    <a
                      v-if="
                        props.projectSite?.legal_document_urls?.dairy_buffalo_dispersal_agreement
                      "
                      :href="
                        props.projectSite.legal_document_urls.dairy_buffalo_dispersal_agreement
                      "
                      target="_blank"
                      class="text-primary underline"
                      >View Dairy Buffalo Dispersal Agreement</a
                    >
                    <span v-else class="text-muted-foreground">No file uploaded</span>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <TrainingRecords
            v-model="form.training_records"
            :is-view-mode="isViewMode"
            @add="addTrainingRecord"
            @remove="removeTrainingRecord"
          />

          <div v-if="!isViewMode" class="flex justify-end gap-4">
            <Button
              type="button"
              variant="outline"
              @click="router.visit(route('ccdp-project-sites.index'))"
            >
              Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
              {{ isEditMode ? 'Update CCDP Project Site' : 'Create CCDP Project Site' }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
