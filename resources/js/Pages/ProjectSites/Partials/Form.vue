<script setup>
import { displayCenterLabel } from '@/lib/center'
import { computed, ref, watch } from 'vue'
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

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
  projectSite: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  program: { type: String, default: '' },
  dairyBoxes: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})

const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')
const isViewMode = computed(() => props.mode === 'view')

const centerOptions = computed(() => {
  const centers = props.centers || []
  return centers.map(center => ({ value: center.id, label: displayCenterLabel(center.code) }))
})

const filteredPartners = computed(() => {
  const centerId = Number(form.center_id || 0)
  if (!centerId) return []

  return (props.partners || []).filter(partner => Number(partner.center_id) === centerId)
})

const partnerOptions = computed(() => {
  return filteredPartners.value.map(partner => ({
    value: partner.id,
    label: `${partner.name} (${partner.code})`,
  }))
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

const addressPsgc = usePsgcAddress()

const { getRegionNameByCode, getProvinceNameByCode, getCityNameByCode, getBarangayNameByCode } =
  addressPsgc

// Reactive form data
const form = useForm({
  // Main ProjectSite fields
  center_id: props.projectSite?.center_id || '',
  production_partner_id: props.projectSite?.production_partner_id || '',
  project_id: props.projectSite?.project_id || '',

  value_chain_status: props.projectSite?.value_chain_status || '',
  inauguration_date: props.projectSite?.inauguration_date || '',
  staff_name: props.projectSite?.staff_name || '',
  staff_phone: props.projectSite?.staff_phone || '',
  staff_position: props.projectSite?.staff_position || '',

  // Excel form fields
  type_of_contract: props.projectSite?.type_of_contract || '',
  region: props.projectSite?.address?.region || '',
  province: props.projectSite?.address?.province || '',
  municipality: props.projectSite?.address?.city || '',
  barangay: props.projectSite?.address?.barangay || '',
  district: props.projectSite?.address?.district || '',
  latitude: props.projectSite?.address?.latitude || '',
  longitude: props.projectSite?.address?.longitude || '',
  year: props.projectSite?.year || new Date().getFullYear(),
  // Production Details
  productions: props.projectSite?.productions || [
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

  // Processing Infrastructure
  processing_infrastructure: props.projectSite?.processing_infrastructure || [
    {
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
  ],

  // Equipment
  equipment: props.projectSite?.equipment || [
    {
      name_of_equipment: '',
      quantity: '',
      capacity: '',
      unit_cost: '',
      total_cost: '',
      delivery_of_equipment: '',
      date_installed: '',
    },
  ],

  // Processing Staff
  processing_staff: props.projectSite?.processing_staff || [
    {
      staff_name: '',
      contact_number: '',
      number_of_staff: 0,
    },
  ],

  // Marketing Infrastructure (Physical Building Details)
  marketing_infrastructure: props.projectSite?.marketing_infrastructure || [
    {
      region: '',
      province: '',
      municipality: '',
      barangay: '',
      district: '',
      latitude: '',
      longitude: '',
      start_of_construction: '',
      date_of_completion: '',
      contractor: '',
      date_inaugurated: '',
    },
  ],

  // Legal Documents
  legal_documents: props.projectSite?.legal_documents || {
    moa: '',
    dod_usufruct: '',
    management_agreement_contract: '',
    dairy_buffalo_dispersal_agreement: '',
  },

  // Budget Utilization
  budget_utilization: props.projectSite?.budget_utilization || {
    budget_allocated: 0,
    obligated: 0,
    disbursed: 0,
    utilization_percentage: 0,
  },

  // Training Records
  training_records: props.projectSite?.training_records || [
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

watch(
  () => form.center_id,
  (newCenterId, oldCenterId) => {
    if (String(newCenterId || '') !== String(oldCenterId || '')) {
      form.production_partner_id = ''
    }
  }
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

const addProcessingStaff = () => {
  form.processing_staff.push({
    staff_name: '',
    contact_number: '',
    number_of_staff: 0,
  })
}

const removeProcessingStaff = index => {
  form.processing_staff.splice(index, 1)
}

const addMarketingInfrastructure = () => {
  form.marketing_infrastructure.push({
    region: '',
    province: '',
    municipality: '',
    barangay: '',
    district: '',
    latitude: '',
    longitude: '',
    start_of_construction: '',
    date_of_completion: '',
    contractor: '',
    date_inaugurated: '',
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

const convertPsgc = data => {
  const convert = v =>
    getRegionNameByCode(v) ||
    getProvinceNameByCode(v) ||
    getCityNameByCode(v) ||
    getBarangayNameByCode(v) ||
    v
  data.region = convert(data.region)
  data.province = convert(data.province)
  data.municipality = convert(data.municipality)
  data.barangay = convert(data.barangay)
  data.processing_infrastructure?.forEach(item => {
    item.region = convert(item.region)
    item.province = convert(item.province)
    item.municipality = convert(item.municipality)
    item.barangay = convert(item.barangay)
  })
  data.marketing_infrastructure?.forEach(item => {
    item.region = convert(item.region)
    item.province = convert(item.province)
    item.municipality = convert(item.municipality)
    item.barangay = convert(item.barangay)
  })
}

const submit = () => {
  const data = form.data()
  convertPsgc(data)
  if (isEditMode.value) {
    router.put(route('project-sites.update', props.projectSite?.id), data)
  } else {
    router.post(route('project-sites.store'), data)
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
                  ? 'Project Site Details'
                  : isEditMode
                    ? 'Edit Project Site'
                    : 'Create Project Site'
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
              @click="router.visit(route('project-sites.edit', props.projectSite?.id))"
            >
              Edit
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <form class="flex flex-col gap-6" @submit.prevent="submit">
          <!-- Main Form (Excel Structure) -->
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Project Details</CardTitle>
              <CardDescription
                >Project, partner, budget, and production information</CardDescription
              >
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="space-y-2">
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
                <Label for="project_program">Program</Label>
                <Select v-if="!isViewMode" v-model="selectedProgram" :disabled="!!program">
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select program" />
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
                <Label for="type_of_contract">Type of Contract</Label>
                <Input
                  v-if="!isViewMode"
                  id="type_of_contract"
                  v-model="form.type_of_contract"
                  placeholder="e.g. Purchase"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ form.type_of_contract || 'N/A' }}
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

              <div class="space-y-2">
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

              <div class="space-y-2">
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
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
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
                  {{ props.projectSite?.inauguration_date || 'N/A' }}
                </div>
                <p v-if="form.errors.inauguration_date" class="text-sm text-red-500">
                  {{ form.errors.inauguration_date }}
                </p>
              </div>

              <div class="space-y-2">
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

              <div class="space-y-2">
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

              <div class="space-y-2">
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

          <!-- Budget Utilization -->
          <BudgetUtilization v-model="form.budget_utilization" :is-view-mode="isViewMode" />

          <!-- Production (sub-table within Main Form) -->
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
                  <div class="space-y-2 md:col-span-2">
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
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Processing Infrastructure -->
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
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
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
                  <div class="space-y-2">
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
                  <div class="space-y-2">
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
                    <Input
                      v-if="!isViewMode"
                      :id="`pi.${index}.completion_status`"
                      v-model="infra.completion_status"
                      placeholder="e.g. Ongoing"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ infra.completion_status || 'N/A' }}
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
              </div>
            </CardContent>
          </Card>

          <!-- Marketing Infrastructure (Physical Building Details) -->
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
                  <div class="space-y-2 md:col-span-2">
                    <PsgcAddressFields
                      :model-value="form.marketing_infrastructure[index]"
                      :is-view-mode="isViewMode"
                      city-field="municipality"
                      @update:model-value="form.marketing_infrastructure[index] = $event"
                    />
                  </div>
                  <div class="space-y-2">
                    <Label :for="`mk.${index}.latitude`">Latitude</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.latitude`"
                      v-model="mk.latitude"
                      placeholder="e.g. 14.0725"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.latitude || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`mk.${index}.longitude`">Longitude</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.longitude`"
                      v-model="mk.longitude"
                      placeholder="e.g. 121.3251"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.longitude || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`mk.${index}.start_of_construction`">Start of Construction</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.start_of_construction`"
                      v-model="mk.start_of_construction"
                      type="date"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.start_of_construction || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`mk.${index}.date_of_completion`">Date of Completion</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.date_of_completion`"
                      v-model="mk.date_of_completion"
                      type="date"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.date_of_completion || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`mk.${index}.contractor`">Contractor</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.contractor`"
                      v-model="mk.contractor"
                      placeholder="Enter contractor name"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.contractor || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`mk.${index}.date_inaugurated`">Date Inaugurated</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`mk.${index}.date_inaugurated`"
                      v-model="mk.date_inaugurated"
                      type="date"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ mk.date_inaugurated || 'N/A' }}
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Equipment -->
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
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
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

          <!-- Processing Staff -->
          <Card>
            <CardHeader>
              <div class="flex items-start justify-between gap-3">
                <div>
                  <CardTitle class="text-base">Processing Staff</CardTitle>
                  <CardDescription>Staff assigned to processing operations</CardDescription>
                </div>
                <Button
                  v-if="!isViewMode"
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="addProcessingStaff"
                >
                  <Plus class="h-4 w-4" /> Add Staff
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div v-for="(staff, index) in form.processing_staff" :key="index" class="mb-6">
                <div class="mb-4 flex items-center justify-between">
                  <Button
                    v-if="!isViewMode && form.processing_staff.length > 1"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="removeProcessingStaff(index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label :for="`staff.${index}.staff_name`">Name of Staff</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`staff.${index}.staff_name`"
                      v-model="staff.staff_name"
                      placeholder="Enter staff name"
                    />
                    <div v-else class="rounded bg-gray-50 p-2 text-sm">
                      {{ staff.staff_name || 'N/A' }}
                    </div>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`staff.${index}.contact_number`">Contact Number</Label>
                    <Input
                      v-if="!isViewMode"
                      :id="`staff.${index}.contact_number`"
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
                    <Label :for="`staff.${index}.number_of_staff`"
                      >Number of Processing Staff</Label
                    >
                    <Input
                      v-if="!isViewMode"
                      :id="`staff.${index}.number_of_staff`"
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
            </CardContent>
          </Card>

          <!-- Legal Documents -->
          <Card>
            <CardHeader>
              <div>
                <CardTitle class="text-base">Legal Documents</CardTitle>
                <CardDescription>Legal agreements and contracts</CardDescription>
              </div>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="legal_moa">MOA</Label>
                <Select v-if="!isViewMode" v-model="form.legal_documents.moa">
                  <SelectTrigger id="legal_moa" class="h-9 w-full">
                    <SelectValue placeholder="Select" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Yes">Yes</SelectItem>
                    <SelectItem value="No">No</SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ form.legal_documents.moa || 'N/A' }}
                </div>
              </div>
              <div class="space-y-2">
                <Label for="legal_dod">DOD / USUFRUCT</Label>
                <Select v-if="!isViewMode" v-model="form.legal_documents.dod_usufruct">
                  <SelectTrigger id="legal_dod" class="h-9 w-full">
                    <SelectValue placeholder="Select" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Yes">Yes</SelectItem>
                    <SelectItem value="No">No</SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ form.legal_documents.dod_usufruct || 'N/A' }}
                </div>
              </div>
              <div class="space-y-2">
                <Label for="legal_management_agreement">Management Agreement Contract</Label>
                <Select
                  v-if="!isViewMode"
                  v-model="form.legal_documents.management_agreement_contract"
                >
                  <SelectTrigger id="legal_management_agreement" class="h-9 w-full">
                    <SelectValue placeholder="Select" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Yes">Yes</SelectItem>
                    <SelectItem value="No">No</SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ form.legal_documents.management_agreement_contract || 'N/A' }}
                </div>
              </div>
              <div class="space-y-2">
                <Label for="legal_dairy_dispersal">Dairy Buffalo Dispersal Agreement</Label>
                <Select
                  v-if="!isViewMode"
                  v-model="form.legal_documents.dairy_buffalo_dispersal_agreement"
                >
                  <SelectTrigger id="legal_dairy_dispersal" class="h-9 w-full">
                    <SelectValue placeholder="Select" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Yes">Yes</SelectItem>
                    <SelectItem value="No">No</SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ form.legal_documents.dairy_buffalo_dispersal_agreement || 'N/A' }}
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Training Records -->
          <TrainingRecords
            v-model="form.training_records"
            :is-view-mode="isViewMode"
            @add="addTrainingRecord"
            @remove="removeTrainingRecord"
          />

          <div v-if="!isViewMode" class="flex justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              @click="router.visit(route('project-sites.index'))"
            >
              Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
              {{
                form.processing
                  ? 'Saving...'
                  : isCreateMode
                    ? 'Create Project Site'
                    : 'Update Project Site'
              }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
