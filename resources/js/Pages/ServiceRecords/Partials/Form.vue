<script setup>
import { displayCenterLabel } from '@/lib/center'
import { computed, ref, onMounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import { Icon } from '@iconify/vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
import SearchableSelect from '@/components/SearchableSelect.vue'
import PsgcAddressFields from '@/components/PsgcAddressFields.vue'
import { CLIENT_NAME_SUFFIXES, formatClientName, parseClientName } from '@/lib/clientName'
import { usePsgcAddress } from '@/composable/usePsgcAddress'
import { usePsgcCascade } from '@/composable/usePsgcCascade'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import { useAuth } from '@/composable/useAuth'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
  serviceRecord: { type: Object, required: true },
  centers: { type: Array, required: true },
  services: { type: Array, required: true },
  farmers: { type: Array, required: true },
  projects: { type: Array, default: () => [] },
  // The index of the service this record belongs to (see ServiceRecordSections),
  // so leaving the form returns to the list that actually shows the record.
  indexUrl: { type: String, default: '' },
  enums: { type: Object, required: true },
})

const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')
const isViewMode = computed(() => props.mode === 'view')

const normalizeGender = gender => {
  const value = String(gender ?? '')
    .trim()
    .toLowerCase()

  if (value === 'm' || value === 'male') return 'Male'
  if (value === 'f' || value === 'female') return 'Female'
  if (value === 'o' || value === 'other') return 'Other'
  if (value === '') return ''

  return gender
}

const { isCenterScoped, user } = useAuth()

// The imports wrote "M"/"F"/"N/A" into this column, which the select has no item
// for, so it presents them as Male/Female - while the value that is submitted is
// the one stored, unless the officer picks a different one. Rewriting it on every
// save would report a gender change on edits that never went near the field.
const genderTouched = ref(false)

const genderValue = computed({
  get: () => normalizeGender(form.gender),
  set: value => {
    form.gender = value
    genderTouched.value = true
  },
})

const form = useForm({
  center_id: props.serviceRecord?.center_id
    ? String(props.serviceRecord.center_id)
    : isCenterScoped.value && user.value?.center_id
      ? String(user.value.center_id)
      : '',
  service_id: props.serviceRecord?.service_id || '',
  client_name: props.serviceRecord?.client_name || '',
  gender: props.serviceRecord?.gender || '',
  // Filled from the PSGC cascade below; the stored address holds labels, the
  // cascade resolves them to the codes the address selects are driven by.
  barangay: '',
  municipality: '',
  province: '',
  region: '',
  district: '',
  project_id: props.serviceRecord?.project_id ? String(props.serviceRecord.project_id) : '',
  category: props.serviceRecord?.service?.category?.[0] || '',
  sub_category: props.serviceRecord?.service?.sub_category?.[0] || '',
  services: props.serviceRecord?.services || '',
  program: props.serviceRecord?.program || '',
  no_animals_served: props.serviceRecord?.no_animals_served ?? '',
  month_submitted: props.serviceRecord?.month_submitted || '',
  date_conducted: props.serviceRecord?.date_conducted
    ? new Date(props.serviceRecord.date_conducted).toISOString().split('T')[0]
    : '',
})

// `client_name` is one column holding either "Last, First Middle Suffix" or
// "First Middle Last Suffix", so the four name inputs are views onto it rather
// than fields of their own. See `@/lib/clientName` for how a stored name is read.
const clientNameParts = computed(() => parseClientName(form.client_name))

const clientNameField = part =>
  computed({
    get: () => clientNameParts.value[part],
    set: value => {
      form.client_name = formatClientName({ ...clientNameParts.value, [part]: value })
    },
  })

const clientFirstName = clientNameField('firstName')
const clientMiddleName = clientNameField('middleName')
const clientLastName = clientNameField('lastName')

// A select item cannot hold an empty value, so "no suffix" is a value of its own
// that writes back as the empty suffix the column actually stores.
const NO_SUFFIX = '__none__'

const clientSuffix = computed({
  get: () => clientNameParts.value.suffix || NO_SUFFIX,
  set: value => {
    form.client_name = formatClientName({
      ...clientNameParts.value,
      suffix: value === NO_SUFFIX ? '' : value,
    })
  },
})

const addressPsgc = usePsgcAddress()

const { hydrateAddressFields } = usePsgcCascade({
  form,
  psgc: addressPsgc,
  cityField: 'municipality',
  withDistrict: true,
})

const selectedProject = computed(
  () => props.projects.find(p => String(p.id) === String(form.project_id)) || null
)

// The program a service record belongs to is the one of the project it was
// rendered under, so picking a project is what decides it.
const projectTouched = ref(false)

const handleProjectChange = value => {
  form.project_id = value
  form.program = selectedProject.value?.program ?? ''
  projectTouched.value = true
}

// What the program reads as: the project's, which is the one the record belongs
// to. A record saved before the program followed the project shows the value it
// has until the project is picked again.
const programValue = computed(() => selectedProject.value?.program ?? form.program)
const programLabel = computed(
  () => props.enums.project_program?.[programValue.value] ?? programValue.value
)

const showServiceModal = ref(false)
const newServiceForm = useForm({
  name: '',
  type: [''],
})

const submit = () => {
  // The address selects work in PSGC codes; service record addresses are stored
  // as the labels the rest of the app reads, so resolve them back on the way out.
  form.transform(data => {
    const payload = {
      ...data,
      region: addressPsgc.getRegionNameByCode(data.region) || data.region,
      province: addressPsgc.getProvinceNameByCode(data.province) || data.province,
      municipality: addressPsgc.getCityNameByCode(data.municipality) || data.municipality,
      barangay: addressPsgc.getBarangayNameByCode(data.barangay) || data.barangay,
    }

    if (!genderTouched.value) {
      payload.gender = props.serviceRecord?.gender ?? ''
    }

    // The program belongs to the project, so it is only written when the project
    // was re-picked. A blank is never written either: the column is NOT NULL, and
    // dropping the key leaves the stored program standing.
    if (!projectTouched.value || !payload.program) {
      delete payload.program
    }

    return payload
  })

  if (isEditMode.value) {
    form.put(route('service-records.update', props.serviceRecord?.id))
  } else {
    form.post(route('service-records.store'))
  }
}

const createNewService = () => {
  newServiceForm.post(route('services.store'), {
    onSuccess: page => {
      showServiceModal.value = false
      newServiceForm.reset()
      form.service_id = String(page.props.service.id)
    },
  })
}

const selectedService = computed(() => {
  if (!form.service_id) return null
  return props.services.find(s => String(s.id) === String(form.service_id))
})

const availableCategories = computed(() => {
  if (!selectedService.value || !selectedService.value.category) return []
  const cats = Array.isArray(selectedService.value.category)
    ? selectedService.value.category
    : JSON.parse(selectedService.value.category || '[]')
  return cats.map(cat => ({ label: cat, value: cat }))
})

const availableSubCategories = computed(() => {
  if (!selectedService.value || !selectedService.value.sub_category) return []
  const subs = Array.isArray(selectedService.value.sub_category)
    ? selectedService.value.sub_category
    : JSON.parse(selectedService.value.sub_category || '[]')
  return subs.map(sub => ({ label: sub, value: sub }))
})

const handleMfoCodeChange = value => {
  if (value === 'other') {
    showServiceModal.value = true
    form.service_id = ''
    form.category = ''
    form.sub_category = ''
  } else if (value) {
    const selected = props.services.find(s => String(s.id) === String(value))
    if (selected) {
      // Categories
      const cats = Array.isArray(selected.category)
        ? selected.category
        : JSON.parse(selected.category || '[]')
      form.category = cats.length > 0 ? cats[0] : ''

      // Subcategories
      const subs = Array.isArray(selected.sub_category)
        ? selected.sub_category
        : JSON.parse(selected.sub_category || '[]')
      form.sub_category = subs.length > 0 ? subs[0] : ''
    } else {
      form.category = ''
      form.sub_category = ''
    }
  } else {
    form.category = ''
    form.sub_category = ''
  }
}

onMounted(async () => {
  await addressPsgc.load()
  await hydrateAddressFields(props.serviceRecord)

  // Reapply persisted scalar fields after async address hydration so the edit
  // form retains the record values when the PSGC options finish loading.
  form.center_id = props.serviceRecord?.center_id ? String(props.serviceRecord.center_id) : ''
  form.service_id = props.serviceRecord?.service_id || ''
  form.client_name = props.serviceRecord?.client_name || ''
  form.gender = props.serviceRecord?.gender || ''
  form.project_id = props.serviceRecord?.project_id ? String(props.serviceRecord.project_id) : ''
  form.no_animals_served = props.serviceRecord?.no_animals_served ?? ''
  form.month_submitted = props.serviceRecord?.month_submitted || ''
  form.date_conducted = props.serviceRecord?.date_conducted
    ? new Date(props.serviceRecord.date_conducted).toISOString().slice(0, 10)
    : ''

  if (form.service_id) {
    const selected = props.services.find(s => String(s.id) === String(form.service_id))
    if (selected) {
      // Categories
      const cats = Array.isArray(selected.category)
        ? selected.category
        : JSON.parse(selected.category || '[]')
      if (!form.category && cats.length > 0) {
        form.category = cats[0]
      }

      // Subcategories
      const subs = Array.isArray(selected.sub_category)
        ? selected.sub_category
        : JSON.parse(selected.sub_category || '[]')
      if (!form.sub_category && subs.length > 0) {
        form.sub_category = subs[0]
      }
    }
  }
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <Card>
      <CardHeader>
        <div class="flex items-start justify-between gap-3">
          <div>
            <CardTitle>
              {{
                isViewMode
                  ? 'Service Record'
                  : isEditMode
                    ? 'Edit Service Record'
                    : 'Create Service Record'
              }}
            </CardTitle>
            <CardDescription>Fill in the details of the service record</CardDescription>
          </div>
          <div class="flex items-center gap-2">
            <Button
              v-if="isViewMode"
              variant="outline"
              type="button"
              @click="router.visit(route('service-records.edit', props.serviceRecord?.id))"
            >
              Edit
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <form class="flex flex-col gap-4" @submit.prevent="submit">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Client Information</CardTitle>
              <CardDescription>Client details and demographics</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div v-if="!isCenterScoped || isViewMode" class="space-y-2">
                <Label>Center *</Label>
                <Select v-if="!isViewMode" v-model="form.center_id">
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select center" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="center in centers"
                      :key="center.id"
                      :value="String(center.id)"
                    >
                      {{ displayCenterLabel(center.code) }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    props.serviceRecord.center
                      ? displayCenterLabel(props.serviceRecord.center.code)
                      : 'N/A'
                  }}
                </div>
                <p v-if="form.errors.center_id" class="text-sm text-red-500">
                  {{ form.errors.center_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="client_first_name">First Name *</Label>
                <Input
                  v-if="!isViewMode"
                  id="client_first_name"
                  v-model="clientFirstName"
                  placeholder="Enter first name"
                  required
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ clientNameParts.firstName || 'N/A' }}
                </div>
                <p v-if="form.errors.client_name" class="text-sm text-red-500">
                  {{ form.errors.client_name }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="client_middle_name">Middle Name</Label>
                <Input
                  v-if="!isViewMode"
                  id="client_middle_name"
                  v-model="clientMiddleName"
                  placeholder="Enter middle name"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ clientNameParts.middleName || 'N/A' }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="client_last_name">Last Name</Label>
                <Input
                  v-if="!isViewMode"
                  id="client_last_name"
                  v-model="clientLastName"
                  placeholder="Enter last name"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ clientNameParts.lastName || 'N/A' }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="client_suffix">Suffix</Label>
                <Select v-if="!isViewMode" v-model="clientSuffix">
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="No suffix" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="NO_SUFFIX">No suffix</SelectItem>
                    <SelectItem
                      v-for="suffix in CLIENT_NAME_SUFFIXES"
                      :key="suffix"
                      :value="suffix"
                    >
                      {{ suffix }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ clientNameParts.suffix || 'N/A' }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="gender">Gender</Label>
                <Select v-if="!isViewMode" v-model="genderValue">
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select gender" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="placeholder">Select Gender</SelectItem>
                    <SelectItem value="Male">Male</SelectItem>
                    <SelectItem value="Female">Female</SelectItem>
                    <SelectItem value="Other">Other</SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ normalizeGender(props.serviceRecord?.gender) || 'Not specified' }}
                </div>
                <p v-if="form.errors.gender" class="text-sm text-red-500">
                  {{ form.errors.gender }}
                </p>
              </div>

              <div class="md:col-span-2">
                <PsgcAddressFields
                  :model-value="form"
                  :is-view-mode="isViewMode"
                  city-field="municipality"
                  value-mode="code"
                  :psgc="addressPsgc"
                  @update:model-value="Object.assign(form, $event)"
                />
              </div>

              <div class="space-y-2">
                <Label for="project">Project</Label>
                <Select
                  v-if="!isViewMode"
                  v-model="form.project_id"
                  @update:model-value="handleProjectChange"
                >
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select project" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="project in projects"
                      :key="project.id"
                      :value="String(project.id)"
                    >
                      {{ enums.project_program?.[project.program] ?? project.program }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    enums.project_program?.[selectedProject?.program] ??
                    selectedProject?.program ??
                    'Not specified'
                  }}
                </div>
                <p v-if="form.errors.project_id" class="text-sm text-red-500">
                  {{ form.errors.project_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="program">Program</Label>
                <Input
                  id="program"
                  :model-value="programLabel"
                  disabled
                  placeholder="Select a project to set the program"
                />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base">Service Information</CardTitle>
              <CardDescription>Service details and execution</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>MFO Code</Label>
                <SearchableSelect
                  v-if="!isViewMode"
                  v-model="form.service_id"
                  :options="[
                    ...services.map(service => ({
                      label: service.simplified_code || 'No Code',
                      value: String(service.id),
                    })),
                    { label: '+ Other (Create New Service)', value: 'other' },
                  ]"
                  placeholder="Search or select MFO Code"
                  @update:model-value="handleMfoCodeChange"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.serviceRecord.service?.simplified_code || 'N/A' }}
                </div>
                <p v-if="form.errors.service_id" class="text-sm text-red-500">
                  {{ form.errors.service_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="service_name">Service Name</Label>
                <Input
                  v-if="!isViewMode"
                  id="service_name"
                  :value="selectedService?.name || ''"
                  disabled
                  placeholder="Service name will be selected automatically"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.serviceRecord.service?.name || 'N/A' }}
                </div>
              </div>

              <div class="space-y-2">
                <Label>Category</Label>
                <SearchableSelect
                  v-if="!isViewMode"
                  v-model="form.category"
                  :options="availableCategories"
                  :disabled="!form.service_id"
                  placeholder="Select Category"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    props.serviceRecord.category ||
                    props.serviceRecord.service?.category?.[0] ||
                    'N/A'
                  }}
                </div>
              </div>

              <div class="space-y-2">
                <Label>Sub Category</Label>
                <SearchableSelect
                  v-if="!isViewMode"
                  v-model="form.sub_category"
                  :options="availableSubCategories"
                  :disabled="!form.service_id"
                  placeholder="Select Sub Category"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    props.serviceRecord.sub_category ||
                    props.serviceRecord.service?.sub_category?.[0] ||
                    'N/A'
                  }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="no_animals_served">No. Animals Served *</Label>
                <Input
                  v-if="!isViewMode"
                  id="no_animals_served"
                  v-model="form.no_animals_served"
                  type="number"
                  min="0"
                  placeholder="0"
                  required
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.serviceRecord.no_animals_served || 0 }}
                </div>
                <p v-if="form.errors.no_animals_served" class="text-sm text-red-500">
                  {{ form.errors.no_animals_served }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="date_conducted">Date Conducted *</Label>
                <Input
                  v-if="!isViewMode"
                  id="date_conducted"
                  v-model="form.date_conducted"
                  type="date"
                  required
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    props.serviceRecord.date_conducted
                      ? new Date(props.serviceRecord.date_conducted).toLocaleDateString()
                      : 'N/A'
                  }}
                </div>
                <p v-if="form.errors.date_conducted" class="text-sm text-red-500">
                  {{ form.errors.date_conducted }}
                </p>
              </div>
            </CardContent>
          </Card>

          <!-- Form Actions -->
          <div class="flex justify-end gap-3 border-t border-slate-200 pt-6">
            <Link
              :href="indexUrl || route('service-records.index')"
              as="button"
              type="button"
              class="inline-flex cursor-pointer items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
            >
              Cancel
            </Link>
            <Button
              v-if="!isViewMode"
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center"
            >
              <Icon
                v-if="form.processing"
                icon="lucide:loader-2"
                class="mr-2 h-4 w-4 animate-spin"
              />
              {{ isEditMode ? 'Update Service Record' : 'Create Service Record' }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>

    <!-- Service Creation Modal -->
    <Dialog v-model:open="showServiceModal">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Create New Service</DialogTitle>
          <DialogDescription>
            Add a new service to the system. Fill in the details below.
          </DialogDescription>
        </DialogHeader>
        <form class="space-y-4" @submit.prevent="createNewService">
          <div class="space-y-2">
            <Label for="service_name">Service Name *</Label>
            <Input
              id="service_name"
              v-model="newServiceForm.name"
              placeholder="Enter service name"
              required
            />
            <p v-if="newServiceForm.errors.name" class="text-sm text-red-500">
              {{ newServiceForm.errors.name }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="service_type">Type of Service *</Label>
            <Input
              id="service_type"
              v-model="newServiceForm.type[0]"
              placeholder="Enter type of service"
              required
            />
            <p v-if="newServiceForm.errors.type" class="text-sm text-red-500">
              {{ newServiceForm.errors.type }}
            </p>
          </div>

          <div class="flex justify-end gap-3 pt-4">
            <Button type="button" variant="outline" @click="showServiceModal = false">
              Cancel
            </Button>
            <Button type="submit" :disabled="newServiceForm.processing">
              <Icon
                v-if="newServiceForm.processing"
                icon="lucide:loader-2"
                class="mr-2 h-4 w-4 animate-spin"
              />
              Create Service
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  </div>
</template>
