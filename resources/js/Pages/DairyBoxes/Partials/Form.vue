<script setup>
import { displayCenterLabel } from '@/lib/center'
import { ref, watch, computed, onMounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useDairyBoxDocuments } from '@/composable/useDairyBoxDocuments'
import { toastFlash } from '@/lib/flashToasts'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import {
  cn,
  normalizeProjectInvolvementOptions,
  resolveProjectInvolvement,
  ensureProjectInvolvementOption,
} from '@/lib/utils'
import SearchableSelect from '@/components/SearchableSelect.vue'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import PsgcAddressFields from '@/components/PsgcAddressFields.vue'
import { usePsgcAddress } from '@/composable/usePsgcAddress'
import { usePsgcCascade } from '@/composable/usePsgcCascade'
import { useAuth } from '@/composable/useAuth'

const route = globalThis.route
const FormData = globalThis.FormData

const props = defineProps({
  dairyBox: { type: Object, default: () => ({}) },
  centers: { type: Array, required: true },
  partners: { type: Array, required: true },
  projectInvolvements: { type: Array, default: () => [] },
  enums: { type: Object, default: () => ({}) },
  center: { type: Object, default: null },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
  existingDocuments: { type: Object, default: () => ({}) },
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
})

const isViewMode = computed(() => props.mode === 'view')
const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')

// ── Compliance document upload ──────────────────────────────────────────────
const {
  stageDocument,
  uploadDocument: doUpload,
  updateDocument: doUpdate,
  downloadDocument,
} = useDairyBoxDocuments()

// Saved documents belong to an existing DairyBox; pending documents are submitted with a new one.
const savedDocs = ref({ ...props.existingDocuments })
const pendingDocs = ref({})

// ── Modal state ─────────────────────────────────────────────────────────────
const docModalOpen = ref(false)
const docModalType = ref('') // e.g. 'bir', 'fda-lto'
const docModalLabel = ref('') // e.g. 'BIR Registered'
const docModalUploading = ref(false)
const docModalFile = ref(null)
const docModalFields = ref({
  compliance_status: '',
  registration_number: '',
  validity: '',
  description: '',
})
const documentPreviewOpen = ref(false)
const selectedDocument = ref(null)

const documentForType = type => savedDocs.value[type] || pendingDocs.value[type]

const openDocumentPreview = document => {
  selectedDocument.value = document
  documentPreviewOpen.value = true
}

const openDocModal = (type, label) => {
  const existing = documentForType(type)
  docModalType.value = type
  docModalLabel.value = label
  docModalFile.value = null
  docModalFields.value = {
    compliance_status: existing?.compliance_status ?? '',
    registration_number: existing?.registration_number ?? '',
    validity: existing?.validity ?? '',
    description: existing?.description ?? '',
  }
  docModalOpen.value = true
}

const closeDocModal = () => {
  if (docModalUploading.value) return
  docModalOpen.value = false
}

const submitDocModal = async () => {
  const type = docModalType.value
  const file = docModalFile.value
  const fields = docModalFields.value
  const saved = savedDocs.value[type]
  const pending = pendingDocs.value[type]

  if (!file && !saved?.id && !pending) return

  docModalUploading.value = true
  try {
    if (isCreateMode.value) {
      const staged = file ? await stageDocument(file) : pending
      pendingDocs.value[type] = {
        ...pending,
        ...staged,
        title: file?.name || pending.title,
        document_type: type,
        compliance_status: fields.compliance_status,
        registration_number: fields.registration_number,
        validity: fields.validity,
        description: fields.description,
      }

      toastFlash({ success: 'Document staged. It will be saved with the dairy box.' })
      docModalOpen.value = false
      return
    }

    if (saved?.id) {
      await doUpdate(saved.id, {
        dairyBoxId: props.dairyBox?.id,
        file: file || undefined,
        documentType: type,
        title: file ? file.name : saved.title,
        complianceStatus: fields.compliance_status,
        registrationNumber: fields.registration_number,
        validity: fields.validity,
        description: fields.description,
      })
      savedDocs.value[type] = {
        ...saved,
        compliance_status: fields.compliance_status,
        registration_number: fields.registration_number,
        validity: fields.validity,
        description: fields.description,
        ...(file ? { file_name: file.name, title: file.name } : {}),
      }
    } else {
      // No document of this type exists yet, so there is nothing to update - the
      // first upload for a type has to create one. It used to fall through to a
      // "A DairyBox is required" error, which read as though the page had lost the
      // record it was editing.
      const { document: created } = await doUpload({
        dairyBoxId: props.dairyBox?.id,
        file,
        documentType: type,
        description: fields.description,
        registrationNumber: fields.registration_number,
        validity: fields.validity,
        complianceStatus: fields.compliance_status,
      })

      // Shaped like `existingDocuments`, so the row renders and a second upload
      // takes the update path instead of creating a duplicate.
      savedDocs.value[type] = {
        id: created.id,
        title: file.name,
        description: fields.description,
        compliance_status: fields.compliance_status,
        registration_number: fields.registration_number,
        validity: fields.validity,
        document_type: type,
        file_path: created.file?.path,
        file_name: file.name,
        file_type: file.type,
        file_size: file.size,
      }
    }

    toastFlash({ success: 'Document saved successfully.' })
    docModalOpen.value = false
  } catch (err) {
    toastFlash({ error: err.message || 'Upload failed.' })
  } finally {
    docModalUploading.value = false
  }
}
// ───────────────────────────────────────────────────────────────────────────

const addressPsgc = usePsgcAddress()

const partnerOptions = computed(() => {
  const centerId = Number(form.center_id || 0)
  if (!centerId) return []
  return props.partners
    .filter(p => Number(p.center_id) === centerId)
    .map(partner => ({
      value: String(partner.id),
      label: partner.name,
    }))
})

const LAND_OWNERSHIP_OTHER_VALUE = '__other__'

const involvementOptions = computed(() =>
  normalizeProjectInvolvementOptions(props.projectInvolvements)
)

const resolvedFundSourceOutlet = computed(() =>
  resolveProjectInvolvement(props.dairyBox?.fund_source_outlet, props.projectInvolvements)
)
const resolvedFundSourceEquipment = computed(() =>
  resolveProjectInvolvement(props.dairyBox?.fund_source_equipment, props.projectInvolvements)
)
const resolvedFundSourceProcessingPlant = computed(() =>
  resolveProjectInvolvement(props.dairyBox?.fund_source_processing_plant, props.projectInvolvements)
)

// Each fund source keeps its own option list so a stored value that is no
// longer an active project involvement still renders instead of the placeholder.
const toInvolvementOptions = values =>
  values.map(inv => ({
    value: inv,
    label: inv,
  }))

const outletOptions = computed(() =>
  toInvolvementOptions(
    ensureProjectInvolvementOption(involvementOptions.value, resolvedFundSourceOutlet.value)
  )
)
const equipmentOptions = computed(() =>
  toInvolvementOptions(
    ensureProjectInvolvementOption(involvementOptions.value, resolvedFundSourceEquipment.value)
  )
)
const processingPlantOptions = computed(() =>
  toInvolvementOptions(
    ensureProjectInvolvementOption(
      involvementOptions.value,
      resolvedFundSourceProcessingPlant.value
    )
  )
)

const normalizeDateForInput = value => {
  if (!value) return ''
  if (typeof value === 'string') return value.slice(0, 10)

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return ''

  return date.toISOString().slice(0, 10)
}

onMounted(async () => {
  await addressPsgc.load()
  if (props.dairyBox?.id) {
    await hydrateAddressFields(props.dairyBox)
  }
})

const logoFile = ref(null)
const logoPreview = ref(props.dairyBox?.logo_url || null)

const { isCenterScoped } = useAuth()

const form = useForm({
  dairy_box_id: '',
  business_name: '',
  manager: '',
  dairy_box_focal: '',
  contact_number: '',
  center_id: props.dairyBox.center_id || props.defaultCenterId || props.center?.id || '',
  partner_id: '',
  status: '',
  launched_date: '',
  kadiwa_integration: '',
  milk_feeding_engagement: '',
  fund_source_outlet: '',
  fund_source_equipment: '',
  fund_source_processing_plant: '',
  land_ownership: '',
  land_ownership_other: '',
  remarks: '',
  start_of_construction: '',
  date_of_completion: '',
  contractor: '',
  date_inaugurated: '',
  metadata: '',
  barangay: '',
  city: '',
  province: '',
  region: '',
  district: '',
  zip_code: '',
  latitude: '',
  longitude: '',
})

const { hydrateAddressFields } = usePsgcCascade({
  form,
  psgc: addressPsgc,
  cityField: 'city',
  // Without this the district is left at whatever the record happens to hold. The
  // select only offers names the PSGC districts table has for the chosen city, so
  // a value it does not recognise falls back to the "Select district" placeholder
  // and looks empty even though the address is complete. The Center and Partner
  // forms both hydrate the district here too.
  withDistrict: true,
})

// Initialize form values when editing or viewing
watch(
  () => props.dairyBox,
  async newDairyBox => {
    if ((props.mode === 'edit' || props.mode === 'view') && newDairyBox?.id) {
      form.dairy_box_id = newDairyBox.dairy_box_id || ''
      form.business_name = newDairyBox.business_name || ''
      form.manager = newDairyBox.manager || ''
      form.dairy_box_focal = newDairyBox.dairy_box_focal || ''
      form.contact_number = newDairyBox.contact_number || ''
      form.center_id = String(newDairyBox.center_id || props.defaultCenterId || '')
      form.partner_id = String(newDairyBox.partner_id || '')
      form.status = newDairyBox.status || ''
      form.launched_date = normalizeDateForInput(newDairyBox.launched_date)
      logoPreview.value = newDairyBox.logo_url || null
      logoFile.value = null
      form.kadiwa_integration = newDairyBox.kadiwa_integration || ''
      form.milk_feeding_engagement = newDairyBox.milk_feeding_engagement || ''
      form.fund_source_outlet = resolvedFundSourceOutlet.value
      form.fund_source_equipment = resolvedFundSourceEquipment.value
      form.fund_source_processing_plant = resolvedFundSourceProcessingPlant.value
      const landOwnership = String(newDairyBox.land_ownership || '')
      if (
        landOwnership &&
        !Object.prototype.hasOwnProperty.call(props.enums?.landOwnership || {}, landOwnership)
      ) {
        form.land_ownership = LAND_OWNERSHIP_OTHER_VALUE
        form.land_ownership_other = landOwnership
      } else {
        form.land_ownership = landOwnership
        form.land_ownership_other = ''
      }
      form.remarks = newDairyBox.remarks || ''
      form.start_of_construction = normalizeDateForInput(newDairyBox.start_of_construction)
      form.date_of_completion = normalizeDateForInput(newDairyBox.date_of_completion)
      form.contractor = newDairyBox.contractor || ''
      form.date_inaugurated = normalizeDateForInput(newDairyBox.date_inaugurated)
      form.metadata = newDairyBox.metadata || ''
      // Resolves region/province/city/barangay/district against PSGC, so the
      // district lands on the label the select offers rather than the stored text.
      await hydrateAddressFields(newDairyBox)
      form.zip_code = newDairyBox.address?.zip_code || ''
      form.latitude = newDairyBox.address?.latitude || ''
      form.longitude = newDairyBox.address?.longitude || ''
    }
  },
  { immediate: true, deep: true }
)

// Ensure default center is applied on create when disableCenterField is true
watch(
  () => props.defaultCenterId,
  id => {
    if (isCreateMode.value && (!form.center_id || form.center_id === '') && id) {
      form.center_id = String(id)
    }
  },
  { immediate: true }
)

watch(
  () => props.center,
  center => {
    if (isCreateMode.value && (!form.center_id || form.center_id === '') && center?.id) {
      form.center_id = String(center.id)
    }
  },
  { immediate: true }
)

watch(
  () => form.center_id,
  () => {
    if (isCreateMode.value && form.partner_id) {
      form.partner_id = ''
    }
  }
)
const appendPendingDocuments = formData => {
  Object.values(pendingDocs.value).forEach((doc, index) => {
    const prefix = `documents[${index}]`

    ;[
      'title',
      'file_name',
      'file_key',
      'file_type',
      'file_size',
      'description',
      'registration_number',
      'validity',
      'document_type',
      'compliance_status',
    ].forEach(field => {
      const value = doc[field]
      if (value !== undefined && value !== null && value !== '') {
        formData.append(`${prefix}[${field}]`, value)
      }
    })
  })
}

const submit = () => {
  if (isViewMode.value) return

  const data = form.data()

  const formData = new FormData()
  Object.keys(data).forEach(key => {
    if (key !== 'logo') {
      formData.append(key, data[key])
    }
  })

  if (logoFile.value) {
    formData.append('logo', logoFile.value)
  }

  if (isCreateMode.value) {
    appendPendingDocuments(formData)
    router.post(route('dairy-boxes.store'), formData, {
      preserveScroll: true,
      onSuccess: () => {
        logoFile.value = null
      },
      onError: errors => {
        form.errors = errors
      },
    })
  } else {
    formData.append('_method', 'PUT')
    router.post(route('dairy-boxes.update', props.dairyBox.id), formData, {
      preserveScroll: true,
      onSuccess: () => {
        logoFile.value = null
      },
      onError: errors => {
        form.errors = errors
      },
    })
  }
}

const goToEdit = () => router.visit(route('dairy-boxes.edit', props.dairyBox.id))
const goToView = () => router.visit(route('dairy-boxes.show', props.dairyBox.id))

const handleLogoChange = event => {
  const file = event.target.files[0]
  if (file) {
    logoFile.value = file
    logoPreview.value = window.URL.createObjectURL(file)
  }
}
</script>

<template>
  <Card class="w-full rounded-lg">
    <CardHeader class="rounded-t-lg">
      <div class="flex items-center justify-between gap-3">
        <div>
          <CardTitle class="text-xl font-bold">
            {{ dairyBox.business_name || 'Dairy Box' }}
          </CardTitle>
          <CardDescription class="mt-1">
            <span v-if="isCreateMode">Create a new dairy box record.</span>
            <span v-else-if="isEditMode">Update dairy box record.</span>
            <span v-else>View dairy box record.</span>
          </CardDescription>
        </div>
        <div class="flex items-center gap-2">
          <Button v-if="isViewMode" variant="outline" @click="goToEdit">Edit</Button>
          <Button v-if="isEditMode" variant="outline" @click="goToView">View</Button>
        </div>
      </div>
    </CardHeader>
    <CardContent class="p-6">
      <form class="space-y-6" @submit.prevent="submit">
        <!-- Logo + Basic Information Section -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
          <!-- Logo -->
          <div class="flex flex-col rounded-lg bg-gray-100 p-4 lg:col-span-1">
            <Avatar
              :class="cn('m-auto aspect-square h-auto w-full max-w-[280px] md:max-w-[18rem]')"
            >
              <AvatarImage
                :src="logoPreview || dairyBox.logo_url"
                alt="Dairy Box Logo"
                class="object-cover"
              />
              <AvatarFallback>
                <span class="text-4xl font-bold text-gray-400">
                  {{ dairyBox.business_name?.charAt(0) || 'D' }}
                </span>
              </AvatarFallback>
            </Avatar>
            <div v-if="!isViewMode" class="mt-4">
              <Label for="logo-upload" class="sr-only">Upload Logo</Label>
              <div class="rounded-md bg-gray-100 p-2 text-gray-700">
                <Input
                  id="logo-upload"
                  type="file"
                  accept="image/*"
                  class="w-full cursor-pointer bg-transparent text-gray-700 file:mr-2 file:cursor-pointer file:rounded file:border-0 file:bg-gray-300 file:px-3 file:py-1 file:text-gray-700"
                  @change="handleLogoChange"
                />
              </div>
            </div>
          </div>

          <!-- Basic Information Card -->
          <Card class="lg:col-span-4">
            <CardHeader>
              <CardTitle class="text-base">Basic Information</CardTitle>
              <CardDescription>Dairy box identification and business details</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label for="dairy_box_id" class="text-sm font-medium text-gray-700"
                    >Dairy Box ID <span class="text-red-500">*</span></Label
                  >
                  <Input
                    v-if="!isViewMode && isCreateMode"
                    id="dairy_box_id"
                    v-model="form.dairy_box_id"
                    type="text"
                    placeholder="Enter dairy box ID"
                    class="h-10"
                    required
                  />
                  <Input
                    v-else-if="!isViewMode && isEditMode"
                    id="dairy_box_id"
                    v-model="form.dairy_box_id"
                    type="text"
                    placeholder="Enter dairy box ID"
                    class="h-10"
                    disabled
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{ dairyBox.dairy_box_id || 'N/A' }}
                  </div>
                  <div v-if="form.errors.dairy_box_id" class="text-xs text-red-600">
                    {{ form.errors.dairy_box_id }}
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="business_name" class="text-sm font-medium text-gray-700"
                    >Business Name <span class="text-red-500">*</span></Label
                  >
                  <Input
                    v-if="!isViewMode"
                    id="business_name"
                    v-model="form.business_name"
                    type="text"
                    placeholder="Enter business name"
                    class="h-10"
                    required
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{ dairyBox.business_name || 'N/A' }}
                  </div>
                  <div v-if="form.errors.business_name" class="text-xs text-red-600">
                    {{ form.errors.business_name }}
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="manager" class="text-sm font-medium text-gray-700">Manager</Label>
                  <Input
                    v-if="!isViewMode"
                    id="manager"
                    v-model="form.manager"
                    type="text"
                    placeholder="Enter manager name"
                    class="h-10"
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{ dairyBox.manager || 'N/A' }}
                  </div>
                  <div v-if="form.errors.manager" class="text-xs text-red-600">
                    {{ form.errors.manager }}
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="dairy_box_focal" class="text-sm font-medium text-gray-700"
                    >Dairy Box Focal</Label
                  >
                  <Input
                    v-if="!isViewMode"
                    id="dairy_box_focal"
                    v-model="form.dairy_box_focal"
                    type="text"
                    placeholder="Enter focal person"
                    class="h-10"
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{ dairyBox.dairy_box_focal || 'N/A' }}
                  </div>
                  <div v-if="form.errors.dairy_box_focal" class="text-xs text-red-600">
                    {{ form.errors.dairy_box_focal }}
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="contact_number" class="text-sm font-medium text-gray-700"
                    >Contact Number</Label
                  >
                  <Input
                    v-if="!isViewMode"
                    id="contact_number"
                    v-model="form.contact_number"
                    type="text"
                    placeholder="Enter contact number"
                    class="h-10"
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{ dairyBox.contact_number || 'N/A' }}
                  </div>
                  <div v-if="form.errors.contact_number" class="text-xs text-red-600">
                    {{ form.errors.contact_number }}
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label for="status" class="text-sm font-medium text-gray-700"
                    >Status <span class="text-red-500">*</span></Label
                  >
                  <Select v-if="!isViewMode" v-model="form.status">
                    <SelectTrigger id="status" class="h-10">
                      <SelectValue placeholder="Select a status" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="(label, value) in enums.statuses"
                        :key="value"
                        :value="value"
                      >
                        {{ label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{ enums.statuses[dairyBox.status] || dairyBox.status || 'N/A' }}
                  </div>
                  <div v-if="form.errors.status" class="text-xs text-red-600">
                    {{ form.errors.status }}
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="launched_date" class="text-sm font-medium text-gray-700"
                    >Launched Date</Label
                  >
                  <Input
                    v-if="!isViewMode"
                    id="launched_date"
                    v-model="form.launched_date"
                    type="date"
                    class="h-10"
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{
                      dairyBox.launched_date
                        ? new Date(dairyBox.launched_date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                          })
                        : 'N/A'
                    }}
                  </div>
                  <div v-if="form.errors.launched_date" class="text-xs text-red-600">
                    {{ form.errors.launched_date }}
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Associations Section -->
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Associations</CardTitle>
            <CardDescription>Center and partner relationships</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div v-if="!isCenterScoped || isViewMode" class="space-y-2">
                <Label for="center_id" class="text-sm font-medium text-gray-700"
                  >Center <span class="text-red-500">*</span></Label
                >
                <template v-if="!isViewMode">
                  <Select v-if="!disableCenterField && !isCenterScoped" v-model="form.center_id">
                    <SelectTrigger id="center_id" class="h-10">
                      <SelectValue placeholder="Select a center" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="centerOption in centers"
                        :key="centerOption.id"
                        :value="String(centerOption.id)"
                      >
                        {{ displayCenterLabel(centerOption.code) }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{
                      center
                        ? displayCenterLabel(center.code)
                        : centers[0]
                          ? displayCenterLabel(centers[0].code)
                          : 'N/A'
                    }}
                  </div>
                </template>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ dairyBox.center ? displayCenterLabel(dairyBox.center.code) : 'N/A' }}
                </div>
                <div v-if="form.errors.center_id" class="text-xs text-red-600">
                  {{ form.errors.center_id }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="partner_id" class="text-sm font-medium text-gray-700"
                  >Partner <span class="text-red-500">*</span></Label
                >
                <SearchableSelect
                  v-if="!isViewMode"
                  id="partner_id"
                  v-model="form.partner_id"
                  :options="partnerOptions"
                  :disabled="!form.center_id"
                  placeholder="Select a partner"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ dairyBox.partner?.name || 'N/A' }}
                </div>
                <div v-if="form.errors.partner_id" class="text-xs text-red-600">
                  {{ form.errors.partner_id }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Address Section -->
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Address</CardTitle>
            <CardDescription>Location and contact address information</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-4">
              <PsgcAddressFields
                :model-value="form"
                :is-view-mode="isViewMode"
                city-field="city"
                value-mode="code"
                :psgc="addressPsgc"
                @update:model-value="Object.assign(form, $event)"
              />

              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label for="zip_code" class="text-sm font-medium text-gray-700">Zip Code</Label>
                  <Input
                    v-if="!isViewMode"
                    id="zip_code"
                    v-model="form.zip_code"
                    type="text"
                    placeholder="Enter zip code"
                    class="h-10"
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{ dairyBox.address?.zip_code || 'N/A' }}
                  </div>
                  <div v-if="form.errors.zip_code" class="text-xs text-red-600">
                    {{ form.errors.zip_code }}
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label for="latitude" class="text-sm font-medium text-gray-700">Latitude</Label>
                  <Input
                    v-if="!isViewMode"
                    id="latitude"
                    v-model="form.latitude"
                    type="text"
                    placeholder="Enter latitude"
                    class="h-10"
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{ dairyBox.address?.latitude || 'N/A' }}
                  </div>
                  <div v-if="form.errors.latitude" class="text-xs text-red-600">
                    {{ form.errors.latitude }}
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="longitude" class="text-sm font-medium text-gray-700">Longitude</Label>
                  <Input
                    v-if="!isViewMode"
                    id="longitude"
                    v-model="form.longitude"
                    type="text"
                    placeholder="Enter longitude"
                    class="h-10"
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{ dairyBox.address?.longitude || 'N/A' }}
                  </div>
                  <div v-if="form.errors.longitude" class="text-xs text-red-600">
                    {{ form.errors.longitude }}
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Construction Details Section -->
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Construction Details</CardTitle>
            <CardDescription>Construction timeline and contractor information</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="start_of_construction" class="text-sm font-medium text-gray-700"
                  >Start of Construction</Label
                >
                <Input
                  v-if="!isViewMode"
                  id="start_of_construction"
                  v-model="form.start_of_construction"
                  type="date"
                  class="h-10"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    dairyBox.start_of_construction
                      ? new Date(dairyBox.start_of_construction).toLocaleDateString('en-US', {
                          year: 'numeric',
                          month: 'long',
                          day: 'numeric',
                        })
                      : 'N/A'
                  }}
                </div>
                <div v-if="form.errors.start_of_construction" class="text-xs text-red-600">
                  {{ form.errors.start_of_construction }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="date_of_completion" class="text-sm font-medium text-gray-700"
                  >Date of Completion</Label
                >
                <Input
                  v-if="!isViewMode"
                  id="date_of_completion"
                  v-model="form.date_of_completion"
                  type="date"
                  class="h-10"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    dairyBox.date_of_completion
                      ? new Date(dairyBox.date_of_completion).toLocaleDateString('en-US', {
                          year: 'numeric',
                          month: 'long',
                          day: 'numeric',
                        })
                      : 'N/A'
                  }}
                </div>
                <div v-if="form.errors.date_of_completion" class="text-xs text-red-600">
                  {{ form.errors.date_of_completion }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="contractor" class="text-sm font-medium text-gray-700">Contractor</Label>
                <Input
                  v-if="!isViewMode"
                  id="contractor"
                  v-model="form.contractor"
                  type="text"
                  placeholder="Enter contractor name"
                  class="h-10"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ dairyBox.contractor || 'N/A' }}
                </div>
                <div v-if="form.errors.contractor" class="text-xs text-red-600">
                  {{ form.errors.contractor }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="date_inaugurated" class="text-sm font-medium text-gray-700"
                  >Date Inaugurated</Label
                >
                <Input
                  v-if="!isViewMode"
                  id="date_inaugurated"
                  v-model="form.date_inaugurated"
                  type="date"
                  class="h-10"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    dairyBox.date_inaugurated
                      ? new Date(dairyBox.date_inaugurated).toLocaleDateString('en-US', {
                          year: 'numeric',
                          month: 'long',
                          day: 'numeric',
                        })
                      : 'N/A'
                  }}
                </div>
                <div v-if="form.errors.date_inaugurated" class="text-xs text-red-600">
                  {{ form.errors.date_inaugurated }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Compliance and Engagement Section -->
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Compliance & Engagement</CardTitle>
            <CardDescription>Regulatory compliance and program engagement status</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <!-- KADIWA Integration — Yes / No -->
              <div class="space-y-2">
                <Label for="kadiwa_integration" class="text-sm font-medium text-gray-700"
                  >KADIWA Integration</Label
                >
                <Select v-if="!isViewMode" v-model="form.kadiwa_integration">
                  <SelectTrigger id="kadiwa_integration" class="h-10">
                    <SelectValue placeholder="Select" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="yes">Yes</SelectItem>
                    <SelectItem value="no">No</SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm capitalize">
                  {{ dairyBox.kadiwa_integration || 'N/A' }}
                </div>
                <div v-if="form.errors.kadiwa_integration" class="text-xs text-red-600">
                  {{ form.errors.kadiwa_integration }}
                </div>
              </div>

              <!-- Milk Feeding Engagement — Yes / No -->
              <div class="space-y-2">
                <Label for="milk_feeding_engagement" class="text-sm font-medium text-gray-700"
                  >Milk Feeding Engagement</Label
                >
                <Select v-if="!isViewMode" v-model="form.milk_feeding_engagement">
                  <SelectTrigger id="milk_feeding_engagement" class="h-10">
                    <SelectValue placeholder="Select" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="yes">Yes</SelectItem>
                    <SelectItem value="no">No</SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm capitalize">
                  {{ dairyBox.milk_feeding_engagement || 'N/A' }}
                </div>
                <div v-if="form.errors.milk_feeding_engagement" class="text-xs text-red-600">
                  {{ form.errors.milk_feeding_engagement }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Compliance Documents Section -->
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Compliance Documents</CardTitle>
            <CardDescription>BIR, FDA LTO, CSO Accreditation, PhilGeps, and FFEDIS</CardDescription>
          </CardHeader>
          <CardContent>
            <table class="w-full text-sm">
              <thead>
                <tr class="text-muted-foreground border-b text-left text-xs font-medium">
                  <th class="pr-4 pb-2">Document</th>
                  <th class="pr-4 pb-2">Status</th>
                  <th class="pr-4 pb-2">Reg. No.</th>
                  <th class="pr-4 pb-2">Validity</th>
                  <th v-if="!isViewMode" class="pb-2"></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(label, type) in enums.documentTypes || {}"
                  :key="type"
                  class="border-b last:border-0"
                >
                  <td class="py-3 pr-4 font-medium text-gray-800">{{ label }}</td>
                  <td class="py-3 pr-4 text-gray-600">
                    <span v-if="documentForType(type)?.compliance_status">
                      {{
                        enums.complianceStatus?.[documentForType(type).compliance_status] ||
                        documentForType(type).compliance_status
                      }}
                    </span>
                    <span v-else class="text-muted-foreground">—</span>
                  </td>
                  <td class="py-3 pr-4 text-gray-600">
                    <button
                      v-if="documentForType(type)?.file_path"
                      type="button"
                      class="flex items-center space-x-1 text-blue-600 hover:underline"
                      @click="openDocumentPreview(documentForType(type))"
                    >
                      {{ documentForType(type)?.registration_number || '—' }}
                    </button>
                    <span v-else>{{ documentForType(type)?.registration_number || '—' }}</span>
                  </td>
                  <td class="py-3 pr-4 text-gray-600">
                    {{ documentForType(type)?.validity || '—' }}
                  </td>
                  <td v-if="!isViewMode" class="py-3 text-right">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      @click="openDocModal(type, label)"
                    >
                      {{ documentForType(type) ? 'Edit' : 'Upload' }}
                    </Button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="isCreateMode" class="text-muted-foreground mt-3 text-xs">
              Select compliance documents now. They will be saved together with the dairy box when
              you click Create.
            </p>
          </CardContent>
        </Card>

        <!-- Compliance Document Preview Modal -->
        <Dialog v-model:open="documentPreviewOpen">
          <DialogContent
            class="flex max-h-[95dvh] w-full flex-col overflow-hidden rounded-t-2xl sm:max-h-[90vh] sm:max-w-4xl sm:rounded-lg"
          >
            <DialogHeader class="shrink-0">
              <DialogTitle>{{ selectedDocument?.title }}</DialogTitle>
              <DialogDescription>File preview and download options</DialogDescription>
            </DialogHeader>

            <div
              v-if="selectedDocument"
              class="flex min-h-0 flex-1 flex-col space-y-4 overflow-y-auto"
            >
              <div
                class="overflow-hidden rounded-lg border"
                style="height: 50dvh; min-height: 200px"
              >
                <embed
                  v-if="selectedDocument.file_type?.includes('pdf')"
                  :src="`/${selectedDocument.file_path}#toolbar=0&navpanes=0&scrollbar=0`"
                  type="application/pdf"
                  class="h-full w-full"
                />
                <div
                  v-else-if="selectedDocument.file_type?.includes('image')"
                  class="flex h-full w-full items-center justify-center bg-gray-50"
                >
                  <img
                    :src="`/${selectedDocument.file_path}`"
                    :alt="selectedDocument.title"
                    class="max-h-full max-w-full object-contain"
                  />
                </div>
                <div v-else class="flex h-full w-full items-center justify-center bg-gray-50">
                  <div class="text-center">
                    <p class="mb-2 text-gray-500">Preview not available for this file type</p>
                    <p class="text-sm text-gray-400">{{ selectedDocument.file_type }}</p>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 sm:gap-4">
                <div>
                  <span class="font-semibold">File Type:</span>
                  <span class="ml-2">{{ selectedDocument.file_type || 'Unknown' }}</span>
                </div>
                <div>
                  <span class="font-semibold">File Size:</span>
                  <span class="ml-2">
                    {{
                      selectedDocument.file_size
                        ? (selectedDocument.file_size / 1024 / 1024).toFixed(2) + ' MB'
                        : 'Unknown'
                    }}
                  </span>
                </div>
                <div>
                  <span class="font-semibold">Document Type:</span>
                  <span class="ml-2">
                    {{
                      enums.documentTypes?.[selectedDocument.document_type] ||
                      selectedDocument.document_type ||
                      '-'
                    }}
                  </span>
                </div>
                <div>
                  <span class="font-semibold">Uploaded By:</span>
                  <span class="ml-2">{{ selectedDocument.uploaded_by || 'Unknown' }}</span>
                </div>
              </div>
            </div>

            <DialogFooter class="shrink-0 flex-col gap-2 sm:flex-row sm:gap-0">
              <Button
                v-if="selectedDocument"
                class="w-full bg-blue-600 hover:bg-blue-700 sm:w-auto"
                @click="downloadDocument(selectedDocument)"
              >
                Download Document
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- Compliance Document Upload Modal -->
        <Dialog :open="docModalOpen" @update:open="closeDocModal">
          <DialogContent class="max-h-[95dvh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
              <DialogTitle
                >{{ documentForType(docModalType) ? 'Edit' : 'Upload' }} —
                {{ docModalLabel }}</DialogTitle
              >
              <DialogDescription>
                {{
                  isCreateMode
                    ? 'Select a PDF and its details. It will be saved with the dairy box.'
                    : 'Update the document details or replace the file.'
                }}
              </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-2">
              <!-- File input -->
              <div class="space-y-1">
                <Label class="text-sm font-medium">
                  {{
                    documentForType(docModalType) ? 'Replace File (PDF, optional)' : 'File (PDF) *'
                  }}
                </Label>
                <input
                  type="file"
                  accept=".pdf"
                  class="border-input bg-background block w-full rounded-md border px-3 py-1.5 text-sm shadow-sm file:border-0 file:bg-transparent file:text-sm file:font-medium"
                  :disabled="docModalUploading"
                  @change="docModalFile = $event.target.files[0] ?? null"
                />
                <p
                  v-if="documentForType(docModalType)?.file_name"
                  class="text-muted-foreground text-xs"
                >
                  Current: {{ documentForType(docModalType).file_name }}
                </p>
              </div>

              <!-- Status -->
              <div class="space-y-1">
                <Label class="text-sm font-medium">Status</Label>
                <Select
                  :model-value="docModalFields.compliance_status"
                  @update:model-value="docModalFields.compliance_status = $event"
                >
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="(statusLabel, value) in enums.complianceStatus"
                      :key="value"
                      :value="value"
                      >{{ statusLabel }}</SelectItem
                    >
                  </SelectContent>
                </Select>
              </div>

              <!-- Registration Number -->
              <div class="space-y-1">
                <Label class="text-sm font-medium">Registration Number</Label>
                <Input
                  v-model="docModalFields.registration_number"
                  placeholder="Enter registration number (optional)"
                  :disabled="docModalUploading"
                />
              </div>

              <!-- Validity -->
              <div class="space-y-1">
                <Label class="text-sm font-medium">Validity</Label>
                <Input
                  v-model="docModalFields.validity"
                  type="date"
                  :disabled="docModalUploading"
                />
              </div>

              <!-- Description -->
              <div class="space-y-1">
                <Label class="text-sm font-medium">Description</Label>
                <Input
                  v-model="docModalFields.description"
                  placeholder="Optional description"
                  :disabled="docModalUploading"
                />
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                :disabled="docModalUploading"
                @click="closeDocModal"
                >Cancel</Button
              >
              <Button
                type="button"
                :disabled="docModalUploading || (!docModalFile && !documentForType(docModalType))"
                @click="submitDocModal"
              >
                {{
                  docModalUploading
                    ? 'Saving…'
                    : documentForType(docModalType)
                      ? 'Update Document'
                      : 'Upload Document'
                }}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- Land Ownership Section -->
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Land Ownership</CardTitle>
            <CardDescription>Type of land ownership for the dairy box site</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-2">
              <Label for="land_ownership" class="text-sm font-medium text-gray-700"
                >Land Ownership</Label
              >
              <Select v-if="!isViewMode" v-model="form.land_ownership">
                <SelectTrigger id="land_ownership" class="h-10">
                  <SelectValue placeholder="Select ownership" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="(label, value) in enums.landOwnership"
                    :key="value"
                    :value="value"
                  >
                    {{ label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <div v-else class="rounded bg-gray-50 p-2 text-sm">
                {{
                  enums.landOwnership?.[dairyBox.land_ownership] || dairyBox.land_ownership || 'N/A'
                }}
              </div>
              <div v-if="form.errors.land_ownership" class="text-xs text-red-600">
                {{ form.errors.land_ownership }}
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Fund Sources Section -->
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Fund Sources</CardTitle>
            <CardDescription
              >Funding sources for outlet, equipment, and processing plant</CardDescription
            >
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div class="space-y-2">
                <Label for="fund_source_outlet" class="text-sm font-medium text-gray-700"
                  >Fund Source: Outlet</Label
                >
                <Select v-if="!isViewMode" v-model="form.fund_source_outlet">
                  <SelectTrigger id="fund_source_outlet" class="h-10">
                    <SelectValue placeholder="Select a project" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="inv in outletOptions" :key="inv.value" :value="inv.value">
                      {{ inv.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ resolvedFundSourceOutlet || 'N/A' }}
                </div>
                <div v-if="form.errors.fund_source_outlet" class="text-xs text-red-600">
                  {{ form.errors.fund_source_outlet }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="fund_source_equipment" class="text-sm font-medium text-gray-700"
                  >Fund Source: Equipment</Label
                >
                <Select v-if="!isViewMode" v-model="form.fund_source_equipment">
                  <SelectTrigger id="fund_source_equipment" class="h-10">
                    <SelectValue placeholder="Select a project" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="project in equipmentOptions"
                      :key="project.value"
                      :value="project.value"
                    >
                      {{ project.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ resolvedFundSourceEquipment || 'N/A' }}
                </div>
                <div v-if="form.errors.fund_source_equipment" class="text-xs text-red-600">
                  {{ form.errors.fund_source_equipment }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="fund_source_processing_plant" class="text-sm font-medium text-gray-700"
                  >Fund Source: Processing Plant</Label
                >
                <Select v-if="!isViewMode" v-model="form.fund_source_processing_plant">
                  <SelectTrigger id="fund_source_processing_plant" class="h-10">
                    <SelectValue placeholder="Select a project" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="project in processingPlantOptions"
                      :key="project.value"
                      :value="project.value"
                    >
                      {{ project.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ resolvedFundSourceProcessingPlant || 'N/A' }}
                </div>
                <div v-if="form.errors.fund_source_processing_plant" class="text-xs text-red-600">
                  {{ form.errors.fund_source_processing_plant }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Remarks Section -->
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Additional Information</CardTitle>
            <CardDescription>Remarks and notes</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <Label for="remarks" class="text-sm font-medium text-gray-700">Remarks</Label>
              <Textarea
                v-if="!isViewMode"
                id="remarks"
                v-model="form.remarks"
                placeholder="Enter remarks"
                rows="3"
                class="resize-none"
              />
              <div v-else class="rounded bg-gray-50 p-2 text-sm whitespace-pre-wrap">
                {{ dairyBox.remarks || 'N/A' }}
              </div>
              <div v-if="form.errors.remarks" class="text-xs text-red-600">
                {{ form.errors.remarks }}
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Action Buttons -->
        <div class="flex items-center justify-between gap-4 border-t pt-6">
          <div class="text-sm text-gray-600">
            <span v-if="isCreateMode"
              >Fill in required fields and click Create to add a new dairy box record.</span
            >
            <span v-else-if="isEditMode">Make your changes and click Save to update record.</span>
          </div>
          <div v-if="!isViewMode" class="flex items-center gap-3">
            <Button
              type="button"
              variant="outline"
              :disabled="form.processing"
              @click="router.visit(route('dairy-boxes.index'))"
            >
              Cancel
            </Button>
            <Button type="submit" :disabled="form.processing" class="min-w-32">
              <span v-if="form.processing"> Processing... </span>
              <span v-else>
                {{ isCreateMode ? 'Create Dairy Box' : 'Save Changes' }}
              </span>
            </Button>
          </div>
        </div>
      </form>
    </CardContent>
  </Card>
</template>
