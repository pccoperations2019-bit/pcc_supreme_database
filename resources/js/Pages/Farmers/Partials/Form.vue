<script setup>
/* global route, fetch, confirm */
import { computed, ref, inject, watch, onMounted } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { Pencil, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { cn, label, formatBoolYesNo } from '@/lib/utils'
import SearchableSelect from '@/components/SearchableSelect.vue'
import FormField from '@/components/form/FormField.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import BoolSelect from '@/components/form/BoolSelect.vue'
import EnumSelect from '@/components/form/EnumSelect.vue'
import { usePsgcAddress } from '@/composable/usePsgcAddress'
import { usePsgcCascade } from '@/composable/usePsgcCascade'
import { useFarmerDocuments } from '@/composable/useFarmerDocuments'
import { toastFlash } from '@/lib/flashToasts'
import DocumentUploadModal from '@/components/farmer/DocumentUploadModal.vue'
import DocumentEditModal from '@/components/farmer/DocumentEditModal.vue'
import DocumentViewModal from '@/components/farmer/DocumentViewModal.vue'
import PhotoUpload from '@/components/farmer/PhotoUpload.vue'
import AddressFieldGroup from '@/components/farmer/AddressFieldGroup.vue'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
import { buildInitialFormState, buildFarmerFormData } from '@/composable/useFarmerForm'
import { useAuth } from '@/composable/useAuth'
import FarmInformationTab from '@/components/farmer/tabs/FarmInformationTab.vue'
import AnimalInventoryTab from '@/components/farmer/tabs/AnimalInventoryTab.vue'
import SourcesOfIncomeTab from '@/components/farmer/tabs/SourcesOfIncomeTab.vue'
import FeedManagementTab from '@/components/farmer/tabs/FeedManagementTab.vue'
import LivestockTab from '@/components/farmer/tabs/LivestockTab.vue'
import WasteManagementTab from '@/components/farmer/tabs/WasteManagementTab.vue'
import AssetsTab from '@/components/farmer/tabs/AssetsTab.vue'
import PovertyIndexTab from '@/components/farmer/tabs/PovertyIndexTab.vue'
import SocioEconomicTab from '@/components/farmer/tabs/SocioEconomicTab.vue'

const psgc = usePsgcAddress()

const csrfToken = usePage().props.csrf_token

const {
  regions: regionsData,
  provinces: addressProvinces,
  cities: addressCities,
  barangays: addressBarangays,
  load: loadPsgc,
  getRegionNameByCode,
  getProvinceNameByCode,
  getCityNameByCode,
  getBarangayNameByCode,
} = psgc

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['view', 'edit', 'create'].includes(value),
  },
  farmer: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
  documents: { type: Array, default: () => [] },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  projects: { type: Object, default: () => ({}) },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
  class: { type: String, default: '' },
})

const modalState = inject('modalState', null)
const createDrafts = inject('createDrafts', null)
const nextDraftId = inject('nextDraftId', null)
const fieldErrors = ref({})
const isViewMode = computed(() => props.mode === 'view')
const isEditMode = computed(() => props.mode === 'edit')
const isCreateMode = computed(() => props.mode === 'create')
const isFormMode = computed(() => isEditMode.value || isCreateMode.value)

const useDrafts = computed(() => isCreateMode.value && createDrafts)
const displayedDocuments = computed(() =>
  useDrafts.value ? createDrafts.documents : props.documents || []
)

const DETAIL_DRAFT_KEYS = [
  'farming-info',
  'animal-inventory',
  'income-info',
  'feed-management',
  'livestock-info',
  'waste-management',
  'geotag',
  'poverty-index',
  'socio-economic',
]

const draftFarmer = computed(() => {
  if (!useDrafts.value) return props.farmer
  const merged = { ...props.farmer }
  const details = { ...(props.farmer.metadata || {}) }
  DETAIL_DRAFT_KEYS.forEach(type => {
    const draft = createDrafts[type]
    if (!draft) return
    Object.entries(draft).forEach(([key, value]) => {
      if (key === 'additional_details') return
      if (value === null || value === undefined || value === '') return
      merged[key] = value
    })
    if (draft.additional_details && typeof draft.additional_details === 'object') {
      Object.assign(details, draft.additional_details)
    }
  })
  merged.metadata = details
  merged.additional_details = details
  return merged
})

const additionalDetailsSource = computed(() => {
  if (useDrafts.value) return draftFarmer.value.additional_details || {}
  if (isFormMode.value) return form.additional_details || {}
  return props.farmer.metadata || props.farmer.additional_details || {}
})

const membershipSource = computed(() => {
  if (useDrafts.value) return draftFarmer.value.membership_in_organization || ''
  if (isFormMode.value) return form.membership_in_organization
  return (
    props.farmer.membership_in_organization ||
    props.farmer.additional_details?.membership_in_organization ||
    ''
  )
})

const organizationNameSource = computed(() => {
  if (useDrafts.value) return draftFarmer.value.name_of_organization || ''
  if (isFormMode.value) return form.name_of_organization
  return (
    props.farmer.name_of_organization || props.farmer.additional_details?.name_of_organization || ''
  )
})

const actionLabelFor = (type, label) => {
  if (useDrafts.value) return createDrafts[type] ? `Edit ${label}` : `Add ${label}`
  return `Edit ${label}`
}

const appendFormValue = (fd, key, value) => {
  if (value === null || value === undefined || value === '') return
  if (typeof globalThis.File !== 'undefined' && value instanceof globalThis.File) {
    fd.append(key, value)
    return
  }
  if (typeof value === 'boolean') {
    fd.append(key, value ? '1' : '0')
    return
  }
  if (Array.isArray(value)) {
    value.forEach((item, index) => appendFormValue(fd, `${key}[${index}]`, item))
    return
  }
  if (typeof value === 'object') {
    Object.entries(value).forEach(([k, v]) => appendFormValue(fd, `${key}[${k}]`, v))
    return
  }
  fd.append(key, value)
}

const calculatedAge = computed(() => {
  const birthday = isFormMode.value ? form.birthday : props.farmer.birthday?.split('T')[0]
  if (!birthday) return null
  const birthDate = new Date(birthday)
  const today = new Date()
  let age = today.getFullYear() - birthDate.getFullYear()
  const monthDiff = today.getMonth() - birthDate.getMonth()
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) age--
  return age
})

const getFullName = farmer => {
  const parts = [farmer.first_name, farmer.middle_name, farmer.last_name].filter(Boolean)
  return parts.join(' ') || 'N/A'
}

const normalizedAddress = address => {
  if (!address) return 'N/A'
  const parts = [
    address.street,
    address.barangay,
    address.city,
    address.province,
    address.region ? label(props.enums.addressRegion, address.region) : null,
  ].filter(Boolean)
  return parts.length > 0 ? parts.join(', ') : 'N/A'
}

const { isCenterScoped } = useAuth()

const form = useForm(buildInitialFormState(props.farmer, props.defaultCenterId))
const { hydrateAddressFields, addressRegionHasProvinces, districtDisabled, districts } =
  usePsgcCascade({
    form,
    psgc,
    cityField: 'municipality',
    withDistrict: true,
  })

onMounted(async () => {
  await loadPsgc()
  await hydrateAddressFields(props.farmer)
})

watch(
  () => props.farmer,
  async newFarmer => {
    console.log('[Form] watch farmer triggered, newFarmer.center_id:', newFarmer.center_id)
    Object.assign(form, buildInitialFormState(newFarmer, props.defaultCenterId))
    console.log('[Form] after Object.assign form.center_id:', form.center_id)
    await hydrateAddressFields(newFarmer)
    photoPreview.value = newFarmer.photo_url || null
    photoFile.value = null
  },
  { deep: true }
)

watch(
  () => form.center_id,
  (newVal, oldVal) => {
    console.log('[Form] form.center_id changed:', oldVal, '->', newVal)
  }
)

const filteredPartners = computed(() => {
  const centerId = Number(form.center_id || 0)
  if (!centerId) return []
  return props.partners.filter(p => Number(p.center_id) === centerId)
})

const partnerOptions = computed(() => {
  const opts = filteredPartners.value.map(p => ({ value: p.id, label: p.name }))

  const currentPartnerId = isFormMode.value ? form.partner_id : props.farmer.partner_id
  const alreadyListed = opts.some(o => String(o.value) === String(currentPartnerId))
  if (currentPartnerId && !alreadyListed && props.farmer.partner) {
    opts.push({ value: props.farmer.partner.id, label: props.farmer.partner.name })
  }

  return opts.length > 0 ? opts : [{ value: null, label: 'None' }]
})

const centerOptions = computed(() => props.centers.map(c => ({ value: c.id, label: c.name })))

watch(
  () => form.center_id,
  () => {
    if (isCreateMode.value) {
      form.partner_id = null
      clearError('partner_id')
    }
  }
)

const clearError = id => {
  if (fieldErrors.value?.[id]) {
    const copy = { ...fieldErrors.value }
    delete copy[id]
    fieldErrors.value = copy
  }
}

const photoFile = ref(null)
const photoPreview = ref(props.farmer.photo_url || null)

const {
  uploadDocument: uploadFarmerDocument,
  updateDocument: saveFarmerDocument,
  downloadDocument,
} = useFarmerDocuments()

const showUploadModal = ref(false)
const uploading = ref(false)

const showDocumentModal = ref(false)
const selectedDocument = ref(null)

const showEditDocumentModal = ref(false)
const selectedEditDocument = ref(null)

const handlePhotoChange = file => {
  if (file) {
    photoFile.value = file
    photoPreview.value = window.URL.createObjectURL(file)
  }
}

const onSubmit = () => {
  const addressLookups = {
    getBarangayNameByCode,
    getCityNameByCode,
    getProvinceNameByCode,
    getRegionNameByCode,
  }

  if (!form.center_id || form.center_id === '') {
    form.center_id = props.defaultCenterId || props.farmer.center_id || null
  }
  console.log('[Form] onSubmit form.center_id:', form.center_id)

  if (isEditMode.value) {
    router.post(
      route('farmers.update', props.farmer.id),
      buildFarmerFormData(form, {
        method: 'PUT',
        photoFile: photoFile.value,
        addressLookups,
      }),
      {
        preserveScroll: true,
        onSuccess: () => {
          photoFile.value = null
          router.reload()
        },
        onError: errors => {
          fieldErrors.value = errors
        },
      }
    )
  }

  if (isCreateMode.value) {
    const formData = buildFarmerFormData(form, { photoFile: photoFile.value, addressLookups })

    if (createDrafts) {
      DETAIL_DRAFT_KEYS.forEach(type => {
        const draft = createDrafts[type]
        if (!draft) return
        Object.entries(draft).forEach(([key, value]) => {
          if (key === 'additional_details') {
            Object.entries(value || {}).forEach(([detailKey, detailValue]) =>
              appendFormValue(formData, `additional_details[${detailKey}]`, detailValue)
            )
            return
          }
          appendFormValue(formData, key, value)
        })
      })

      createDrafts.documents.forEach((doc, index) => {
        const prefix = `documents[${index}]`
        formData.append(`${prefix}[title]`, doc.title)
        formData.append(`${prefix}[file_name]`, doc.file_name)
        formData.append(`${prefix}[file_key]`, doc.file_key)
        formData.append(`${prefix}[file_type]`, doc.file_type)
        formData.append(`${prefix}[file_size]`, doc.file_size)
        if (doc.description) formData.append(`${prefix}[description]`, doc.description)
        if (doc.registration_number)
          formData.append(`${prefix}[registration_number]`, doc.registration_number)
        if (doc.validity) formData.append(`${prefix}[validity]`, doc.validity)
        if (doc.compliance_status)
          formData.append(`${prefix}[compliance_status]`, doc.compliance_status)
        if (doc.document_type) formData.append(`${prefix}[document_type]`, doc.document_type)
      })
    }

    router.post(route('farmers.store'), formData, {
      preserveScroll: true,
      onSuccess: () => {
        photoFile.value = null
      },
      onError: errors => {
        fieldErrors.value = errors
      },
    })
  }
}

const openEditModal = (type, id = null) => {
  if (!modalState) return

  const lastClosedAt = Number(modalState.value?.lastClosedAt || 0)
  if (lastClosedAt && Date.now() - lastClosedAt < 200) return

  modalState.value = { open: true, type, id, lastClosedAt }
}

const openDocumentModal = document => {
  selectedDocument.value = document
  showDocumentModal.value = true
}

const openEditDocumentModal = document => {
  selectedEditDocument.value = document
  showEditDocumentModal.value = true
}

const handleUploadDocument = async payload => {
  if (!payload?.file || !payload?.document_type) return

  if (useDrafts.value) {
    await stageDraftDocument(payload)
    return
  }

  if (!props.farmer?.id) {
    toastFlash({ error: 'Please save the farmer first before uploading documents.' })
    return
  }

  uploading.value = true

  try {
    await uploadFarmerDocument({
      farmerId: props.farmer.id,
      file: payload.file,
      documentType: payload.document_type,
      description: payload.description,
      registrationNumber: payload.registration_number,
      validity: payload.validity,
      complianceStatus: payload.compliance_status,
    })

    toastFlash({ success: 'Document uploaded successfully.' })
    showUploadModal.value = false
    router.reload()
  } catch (error) {
    toastFlash({ error: `Upload failed: ${error?.message ?? 'Please try again.'}` })
  } finally {
    uploading.value = false
  }
}

const stageDraftDocument = async payload => {
  uploading.value = true

  try {
    const presignedResponse = await fetch('/xhr/files-presigned-url', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify({
        filename: payload.file.name,
        contentType: payload.file.type,
      }),
    })

    if (!presignedResponse.ok) {
      throw new Error(`Failed to get presigned URL: ${presignedResponse.status}`)
    }

    const presignedData = await presignedResponse.json()

    const uploadResponse = await fetch(presignedData.url, {
      method: 'PUT',
      headers: {
        'Content-Type': payload.file.type,
      },
      body: payload.file,
    })

    if (!uploadResponse.ok) {
      throw new Error(`File upload failed: ${uploadResponse.status}`)
    }

    createDrafts.documents.push({
      id: nextDraftId ? nextDraftId() : `draft-${Date.now()}`,
      title: payload.file.name,
      file_name: payload.file.name,
      file_key: presignedData.key,
      file_type: payload.file.type,
      file_size: payload.file.size,
      description: payload.description,
      registration_number: payload.registration_number,
      validity: payload.validity,
      compliance_status: payload.compliance_status,
      document_type: payload.document_type,
      status: 'Pending',
    })

    toastFlash({ success: 'Document staged successfully.' })
    showUploadModal.value = false
  } catch (error) {
    toastFlash({ error: `Upload failed: ${error?.message ?? 'Please try again.'}` })
  } finally {
    uploading.value = false
  }
}

const removeDraftDocument = doc => {
  if (confirm(`Are you sure you want to remove ${doc.title}?`)) {
    createDrafts.documents = createDrafts.documents.filter(item => item.id !== doc.id)
  }
}

const handleUpdateDocument = async payload => {
  try {
    await saveFarmerDocument(payload.id, {
      farmerCode: props.farmer?.code,
      file: payload.file,
      documentType: payload.document_type,
      description: payload.description,
      registrationNumber: payload.registration_number,
      validity: payload.validity,
      title: payload.title,
      complianceStatus: payload.compliance_status,
    })

    toastFlash({ success: 'Document updated successfully.' })
    showEditDocumentModal.value = false
    selectedEditDocument.value = null
    router.reload()
  } catch (error) {
    toastFlash({ error: `Update failed: ${error?.message ?? 'Please try again.'}` })
  }
}
</script>

<template>
  <div class="flex flex-col space-y-4">
    <!-- ── Photo + General Info ── -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
      <!-- Photo -->
      <PhotoUpload
        :preview="photoPreview || farmer.photo_url"
        :alt="getFullName(farmer)"
        :editable="isFormMode"
        @change="handlePhotoChange"
      />

      <!-- General Info card -->
      <Card class="lg:col-span-4">
        <CardHeader>
          <CardTitle>General Information</CardTitle>
          <CardDescription>Personal and demographic details</CardDescription>
        </CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="onSubmit">
            <!-- View mode: 2-column card layout -->
            <div v-if="isViewMode" class="grid grid-cols-1 gap-6 md:grid-cols-2">
              <!-- Left column -->
              <dl class="grid grid-cols-1 gap-y-3">
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Full Name</dt>
                  <dd class="font-medium wrap-break-word">{{ getFullName(farmer) }}</dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Nickname</dt>
                  <dd class="font-medium">{{ farmer.nickname || 'N/A' }}</dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Status</dt>
                  <dd class="font-medium">
                    {{ farmer.status ? label(enums.statuses, farmer.status) : 'N/A' }}
                  </dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Gender</dt>
                  <dd class="font-medium">{{ farmer.gender || 'N/A' }}</dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Age</dt>
                  <dd class="font-medium">{{ calculatedAge || 'N/A' }}</dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Birthday</dt>
                  <dd class="font-medium">
                    {{ farmer.birthday ? new Date(farmer.birthday).toLocaleDateString() : 'N/A' }}
                  </dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Civil Status</dt>
                  <dd class="font-medium">
                    {{
                      farmer.civil_status ? label(enums.civilStatus, farmer.civil_status) : 'N/A'
                    }}
                  </dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Spouse Name</dt>
                  <dd class="font-medium">{{ farmer.spouse_name || 'N/A' }}</dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Household Size</dt>
                  <dd class="font-medium">{{ farmer.household_size ?? 'N/A' }}</dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="font-medium">{{ farmer.contact_number || 'N/A' }}</dd>
                </div>
              </dl>

              <!-- Right column -->
              <dl class="grid grid-cols-1 gap-y-3">
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Has Personal Cellphone</dt>
                  <dd class="font-medium">
                    {{ formatBoolYesNo(farmer.has_personal_cellphone) }}
                  </dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Address</dt>
                  <dd class="font-medium wrap-break-word">
                    {{ normalizedAddress(farmer.address) }}
                  </dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Center</dt>
                  <dd class="font-medium">
                    {{ farmer.center ? farmer.center.name : 'N/A' }}
                  </dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Partner</dt>
                  <dd class="font-medium">
                    {{ farmer.partner ? farmer.partner.name : 'N/A' }}
                  </dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Projects</dt>
                  <dd class="font-medium">
                    <div
                      v-if="farmer.projects && farmer.projects.length > 0"
                      class="flex flex-wrap gap-1"
                    >
                      <Badge
                        v-for="project in farmer.projects"
                        :key="project.id"
                        variant="secondary"
                      >
                        {{ project.name }}
                      </Badge>
                    </div>
                    <span v-else>N/A</span>
                  </dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Farmer Code</dt>
                  <dd class="font-medium">{{ farmer.code || 'N/A' }}</dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Operation In Charge</dt>
                  <dd class="font-medium">{{ farmer.operation_in_charge || 'N/A' }}</dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Assisted</dt>
                  <dd class="font-medium">{{ formatBoolYesNo(farmer.is_assisted) }}</dd>
                </div>
                <div class="grid grid-cols-2 gap-x-4">
                  <dt class="text-muted-foreground font-medium">Date Assisted</dt>
                  <dd class="font-medium">
                    {{
                      farmer.date_assisted
                        ? new Date(farmer.date_assisted).toLocaleDateString()
                        : 'N/A'
                    }}
                  </dd>
                </div>
              </dl>
            </div>

            <!-- Edit / Create mode: input grid -->
            <div
              v-if="isFormMode"
              class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2 md:grid-cols-4"
            >
              <FormField
                v-if="!isCenterScoped"
                id="center_id"
                label="Center"
                :error="fieldErrors.center_id"
                required
              >
                <SearchableSelect
                  v-model="form.center_id"
                  :options="centerOptions"
                  placeholder="Select center"
                  :disabled="disableCenterField || isCenterScoped"
                  @update:model-value="clearError('center_id')"
                />
              </FormField>
              <FormField id="partner_id" label="Partner" :error="fieldErrors.partner_id">
                <SearchableSelect
                  v-model="form.partner_id"
                  :options="partnerOptions"
                  :disabled="!form.center_id"
                  placeholder="Select partner (optional)"
                  @update:model-value="clearError('partner_id')"
                />
              </FormField>

              <FormField id="project_ids" label="Projects" :error="fieldErrors.project_ids">
                <Select v-model="form.project_ids" multiple>
                  <SelectTrigger class="h-8 w-full">
                    <SelectValue placeholder="Select projects" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(label, id) in props.projects" :key="id" :value="Number(id)">
                      {{ label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </FormField>

              <FieldInput
                id="code"
                v-model="form.code"
                label="Farmer Code"
                :error="fieldErrors.code"
                required
                :disabled="isEditMode"
                @input="clearError('code')"
              />

              <FieldInput
                id="first_name"
                v-model="form.first_name"
                label="First Name"
                :error="fieldErrors.first_name"
                required
                @input="clearError('first_name')"
              />
              <FieldInput
                id="middle_name"
                v-model="form.middle_name"
                label="Middle Name"
                @input="clearError('middle_name')"
              />
              <FieldInput
                id="last_name"
                v-model="form.last_name"
                label="Last Name"
                :error="fieldErrors.last_name"
                required
                @input="clearError('last_name')"
              />
              <FieldInput
                id="extension_name"
                v-model="form.extension_name"
                label="Extension Name"
                @input="clearError('extension_name')"
              />
              <FieldInput
                id="nickname"
                v-model="form.nickname"
                label="Nickname"
                @input="clearError('nickname')"
              />

              <EnumSelect
                id="status"
                v-model="form.status"
                label="Status"
                placeholder="Select status"
                :options="{ active: 'Active', inactive: 'Inactive' }"
                @update:model-value="clearError('status')"
              />
              <EnumSelect
                id="gender"
                v-model="form.gender"
                label="Gender"
                placeholder="Select gender"
                :options="{ Male: 'Male', Female: 'Female', Other: 'Other' }"
                @update:model-value="clearError('gender')"
              />
              <FieldInput
                id="date_of_interview"
                v-model="form.date_of_interview"
                label="Date of Interview"
                type="date"
                @input="clearError('date_of_interview')"
              />

              <FieldInput
                id="birthday"
                v-model="form.birthday"
                label="Birthday"
                type="date"
                @input="clearError('birthday')"
              />
              <FieldInput
                id="date_of_enrollment"
                v-model="form.date_of_enrollment"
                label="Date of Enrollment"
                type="date"
                @input="clearError('date_of_enrollment')"
              />

              <EnumSelect
                id="civil_status"
                v-model="form.civil_status"
                label="Civil Status"
                placeholder="Select civil status"
                :options="props.enums.civilStatus"
                @update:model-value="clearError('civil_status')"
              />
              <FieldInput
                id="spouse_name"
                v-model="form.spouse_name"
                label="Spouse Name"
                @input="clearError('spouse_name')"
              />
              <FieldInput
                id="household_size"
                v-model="form.household_size"
                label="Household Size"
                type="number"
                min="0"
                @input="clearError('household_size')"
              />
              <FieldInput
                id="contact_number"
                v-model="form.contact_number"
                label="Contact Number"
                type="tel"
                @input="clearError('contact_number')"
              />

              <BoolSelect
                id="has_personal_cellphone"
                v-model="form.has_personal_cellphone"
                label="Has Personal Cellphone"
                @update:model-value="clearError('has_personal_cellphone')"
              />
              <FieldInput
                id="operation_in_charge"
                v-model="form.operation_in_charge"
                label="Operation In Charge"
                @input="clearError('operation_in_charge')"
              />
              <BoolSelect
                id="is_assisted"
                v-model="form.is_assisted"
                label="Assisted"
                @update:model-value="clearError('is_assisted')"
              />
              <FieldInput
                id="date_assisted"
                v-model="form.date_assisted"
                label="Date Assisted"
                type="date"
                @input="clearError('date_assisted')"
              />
              <FieldInput
                id="purok_zone"
                v-model="form.purok_zone"
                label="Purok/Zone"
                @input="clearError('purok_zone')"
              />
              <FieldInput
                id="zip_code"
                v-model="form.zip_code"
                label="ZIP Code"
                :error="fieldErrors.zip_code"
                @input="clearError('zip_code')"
              />

              <!-- Address Fields -->
              <div class="sm:col-span-2 md:col-span-4">
                <AddressFieldGroup
                  :form="form"
                  :errors="fieldErrors"
                  :regions="regionsData"
                  :provinces="addressProvinces"
                  :cities="addressCities"
                  :barangays="addressBarangays"
                  :districts="districts"
                  :district-disabled="districtDisabled"
                  :province-disabled="!form.region || !addressRegionHasProvinces"
                  :city-disabled="addressRegionHasProvinces ? !form.province : !form.region"
                  @clear-error="clearError"
                />
              </div>

              <!-- Submit Buttons — full-width on mobile, auto on sm+ -->
              <div
                class="col-span-1 flex flex-wrap justify-end gap-2 pt-4 sm:col-span-2 md:col-span-4"
              >
                <Button
                  type="submit"
                  :disabled="form.processing"
                  class="w-full bg-black text-white hover:bg-black/90 sm:w-auto"
                >
                  {{
                    form.processing ? 'Saving...' : isCreateMode ? 'Create Farmer' : 'Save Changes'
                  }}
                </Button>
              </div>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>

    <!-- ── Farmer Details ── -->
    <Card>
      <CardHeader>
        <CardTitle>Farmer Details</CardTitle>
        <CardDescription>Socio-demographic, income, poverty, and farm information</CardDescription>
      </CardHeader>
      <CardContent>
        <Tabs default-value="socio-economic" :class="cn('bordered')">
          <TabsList
            class="scrollbar-none flex w-full items-center gap-1 overflow-x-auto whitespace-nowrap"
          >
            <TabsTrigger value="socio-economic" class="shrink-0 grow basis-0 text-xs sm:text-sm"
              >Socio-Economic Information</TabsTrigger
            >
            <TabsTrigger value="income" class="shrink-0 grow basis-0 text-xs sm:text-sm"
              >Sources of Income</TabsTrigger
            >
            <TabsTrigger value="poverty-index" class="shrink-0 grow basis-0 text-xs sm:text-sm"
              >Poverty Probability Index</TabsTrigger
            >
            <TabsTrigger value="farm-information" class="shrink-0 grow basis-0 text-xs sm:text-sm"
              >Farm Information</TabsTrigger
            >
            <TabsTrigger value="animal-inventory" class="shrink-0 grow basis-0 text-xs sm:text-sm"
              >Animal Inventory</TabsTrigger
            >
            <TabsTrigger value="feed-management" class="shrink-0 grow basis-0 text-xs sm:text-sm"
              >Feed Management</TabsTrigger
            >
            <TabsTrigger value="livestock" class="shrink-0 grow basis-0 text-xs sm:text-sm"
              >Livestock</TabsTrigger
            >
            <TabsTrigger value="waste-management" class="shrink-0 grow basis-0 text-xs sm:text-sm"
              >Waste Management</TabsTrigger
            >
            <TabsTrigger value="assets" class="shrink-0 grow basis-0 text-xs sm:text-sm"
              >Assets</TabsTrigger
            >
          </TabsList>

          <!-- Farm Information -->
          <TabsContent value="farm-information" class="space-y-4">
            <FarmInformationTab
              :farmer="draftFarmer"
              :enums="enums"
              :additional-details="additionalDetailsSource"
              :is-edit-mode="isFormMode"
              :action-label="actionLabelFor('farming-info', 'Farm Details')"
              @edit="openEditModal"
            />
          </TabsContent>

          <!-- Animal Inventory -->
          <TabsContent value="animal-inventory" class="space-y-4">
            <AnimalInventoryTab
              :farmer="draftFarmer"
              :is-edit-mode="isFormMode"
              :action-label="actionLabelFor('animal-inventory', 'Animal Inventory')"
              @edit="openEditModal"
            />
          </TabsContent>

          <!-- Sources of Income -->
          <TabsContent value="income" class="space-y-4">
            <SourcesOfIncomeTab
              :farmer="draftFarmer"
              :is-edit-mode="isFormMode"
              :action-label="actionLabelFor('income-info', 'Income Information')"
              @edit="openEditModal"
            />
          </TabsContent>

          <!-- Feed Management -->
          <TabsContent value="feed-management" class="space-y-4">
            <FeedManagementTab
              :additional-details="additionalDetailsSource"
              :is-edit-mode="isFormMode"
              :action-label="actionLabelFor('feed-management', 'Feed Management')"
              @edit="openEditModal"
            />
          </TabsContent>

          <!-- Livestock -->
          <TabsContent value="livestock" class="space-y-4">
            <LivestockTab
              :farmer="draftFarmer"
              :is-edit-mode="isFormMode"
              :action-label="actionLabelFor('livestock-info', 'Livestock Information')"
              @edit="openEditModal"
            />
          </TabsContent>

          <!-- Waste Management -->
          <TabsContent value="waste-management" class="space-y-4">
            <WasteManagementTab
              :farmer="draftFarmer"
              :additional-details="additionalDetailsSource"
              :is-edit-mode="isFormMode"
              :action-label="actionLabelFor('waste-management', 'Waste Management')"
              @edit="openEditModal"
            />
          </TabsContent>

          <!-- Assets -->
          <TabsContent value="assets" class="space-y-4">
            <AssetsTab
              :farmer="draftFarmer"
              :is-edit-mode="isFormMode"
              :action-label="actionLabelFor('geotag', 'Assets')"
              @edit="openEditModal"
            />
          </TabsContent>

          <!-- Poverty Probability Index -->
          <TabsContent value="poverty-index" class="space-y-4">
            <PovertyIndexTab
              :farmer="draftFarmer"
              :enums="enums"
              :additional-details="additionalDetailsSource"
              :is-edit-mode="isFormMode"
              :action-label="actionLabelFor('poverty-index', 'Poverty Index')"
              @edit="openEditModal"
            />
          </TabsContent>

          <!-- Socio-Economic -->
          <TabsContent value="socio-economic" class="space-y-4">
            <SocioEconomicTab
              :farmer="draftFarmer"
              :enums="enums"
              :membership-source="membershipSource"
              :organization-name-source="organizationNameSource"
              :is-edit-mode="isFormMode"
              :action-label="actionLabelFor('socio-economic', 'Socio-Economic')"
              @edit="openEditModal"
            />
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>

    <!-- ── Documents ── -->
    <Card>
      <CardHeader>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <CardTitle>Documents</CardTitle>
            <CardDescription>Uploaded files related to the farmer</CardDescription>
          </div>
          <Button
            v-if="isFormMode"
            class="bg-blue-600 hover:bg-blue-700"
            :disabled="!isCreateMode && !farmer?.id"
            @click="
              () => {
                if (!isCreateMode && !farmer?.id) {
                  toastFlash({ error: 'Please save the farmer first before uploading documents.' })
                  return
                }
                showUploadModal = true
              }
            "
          >
            Add Document
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <div class="overflow-x-auto">
          <Table class="min-w-[600px]">
            <TableHeader>
              <TableRow>
                <TableHead>Document Type</TableHead>
                <TableHead>Registration Number</TableHead>
                <TableHead>Validity</TableHead>
                <TableHead>Uploaded By</TableHead>
                <TableHead>Uploaded At</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="document in displayedDocuments" :key="document.id">
                <TableCell>
                  <span v-if="document.status === 'Pending'" class="mr-2">
                    <Badge class="bg-amber-500">Pending</Badge>
                  </span>
                  <span v-if="document.tags && document.tags.length > 0">
                    {{
                      enums.document?.[document.tags.find(t => t.key === 'document')?.value] ||
                      document.tags.find(t => t.key === 'document')?.value ||
                      '-'
                    }}
                  </span>
                  <span v-else-if="document.document_type">{{ document.document_type }}</span>
                  <span v-else>-</span>
                </TableCell>
                <TableCell>
                  <button
                    v-if="document.file?.path"
                    type="button"
                    class="flex items-center space-x-1 text-blue-600 hover:underline"
                    @click="openDocumentModal(document)"
                  >
                    {{ document.registration_number || '-' }}
                  </button>
                  <span v-else>{{ document.registration_number || '-' }}</span>
                </TableCell>
                <TableCell>{{
                  document.validity ? new Date(document.validity).toLocaleDateString() : '-'
                }}</TableCell>
                <TableCell>{{
                  document.file?.uploaded_by?.name || document.file?.uploadedBy?.name || '-'
                }}</TableCell>
                <TableCell>{{
                  document.file?.created_at
                    ? new Date(document.file.created_at).toLocaleDateString()
                    : '-'
                }}</TableCell>
                <TableCell>
                  <template v-if="document.status === 'Pending'">
                    <Button variant="ghost" size="sm" @click="removeDraftDocument(document)">
                      <Trash2 class="h-4 w-4 text-red-600" />
                    </Button>
                  </template>
                  <template v-else>
                    <Button variant="ghost" size="sm" @click="openEditDocumentModal(document)">
                      <Pencil class="h-4 w-4" />
                    </Button>
                  </template>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
  </div>

  <DocumentViewModal
    v-model:open="showDocumentModal"
    :document="selectedDocument"
    :document-types="enums.document"
    @download="downloadDocument"
  />

  <DocumentUploadModal
    v-model:open="showUploadModal"
    :farmer-name="getFullName(farmer)"
    :document-types="enums.document"
    :compliance-statuses="enums.complianceStatuses"
    :uploading="uploading"
    @upload="handleUploadDocument"
  />

  <DocumentEditModal
    v-model:open="showEditDocumentModal"
    :document="selectedEditDocument"
    :document-types="enums.document"
    :compliance-statuses="enums.complianceStatuses"
    @save="handleUpdateDocument"
  />
</template>
