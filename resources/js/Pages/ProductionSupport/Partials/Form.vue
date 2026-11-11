<script setup>
import { displayCenterLabel } from '@/lib/center'
import { computed, onMounted, ref, watch } from 'vue'
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
import { useAuth } from '@/composable/useAuth'
import { usePsgcAddress } from '@/composable/usePsgcAddress'
import { usePsgcCascade } from '@/composable/usePsgcCascade'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
  serviceRecord: { type: Object, required: true },
  /** The pre-locked Service for this controller (simplified_code 1.1.1) */
  service: { type: Object, default: null },
  centers: { type: Array, required: true },
  farmers: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})

const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')
const isViewMode = computed(() => props.mode === 'view')

const { isCenterScoped, user } = useAuth()

const availableCategories = computed(() => {
  if (!props.service?.category) return []
  const cats = Array.isArray(props.service.category)
    ? props.service.category
    : JSON.parse(props.service.category || '[]')
  return cats.map(cat => ({ label: cat, value: cat }))
})

const availableSubCategories = computed(() => {
  if (!props.service?.sub_category) return []
  const subs = Array.isArray(props.service.sub_category)
    ? props.service.sub_category
    : JSON.parse(props.service.sub_category || '[]')
  return subs.map(sub => ({ label: sub, value: sub }))
})

const normalizeGender = gender => {
  const value = String(gender ?? '').trim().toLowerCase()
  if (value === 'm' || value === 'male') return 'Male'
  if (value === 'f' || value === 'female') return 'Female'
  if (value === 'o' || value === 'other') return 'Other'
  return gender || ''
}

const genderTouched = ref(false)

const form = useForm({
  center_id: props.serviceRecord?.center_id
    ? String(props.serviceRecord.center_id)
    : isCenterScoped.value && user.value?.center_id
      ? String(user.value.center_id)
      : '',
  service_id: props.service?.id
    ? String(props.service.id)
    : props.serviceRecord?.service_id
      ? String(props.serviceRecord.service_id)
      : '',
  client_name:
    props.serviceRecord?.client_name ||
    [props.serviceRecord?.farmer?.first_name, props.serviceRecord?.farmer?.last_name]
      .filter(Boolean)
      .join(' '),
  gender: props.serviceRecord?.gender || '',
  barangay: '',
  municipality: '',
  province: '',
  region: '',
  district: '',
  project_id: props.serviceRecord?.project_id ? String(props.serviceRecord.project_id) : '',
  category: props.serviceRecord?.category || props.serviceRecord?.service?.category?.[0] || '',
  sub_category:
    props.serviceRecord?.sub_category || props.serviceRecord?.service?.sub_category?.[0] || '',
  program: props.serviceRecord?.program || '',
  no_animals_served: props.serviceRecord?.no_animals_served ?? '',
  date_conducted: props.serviceRecord?.date_conducted
    ? new Date(props.serviceRecord.date_conducted).toISOString().split('T')[0]
    : '',
})

const addressPsgc = computed({
  get: () => ({
    region: form.region,
    province: form.province,
    municipality: form.municipality,
    barangay: form.barangay,
    district: form.district,
  }),
  set: value => {
    form.region = value.region ?? ''
    form.province = value.province ?? ''
    form.municipality = value.municipality ?? ''
    form.barangay = value.barangay ?? ''
    form.district = value.district ?? ''
  },
})

const psgcAddress = usePsgcAddress()
const { hydrateAddressFields } = usePsgcCascade({
  form,
  psgc: psgcAddress,
  cityField: 'municipality',
  withDistrict: true,
})

const genderValue = computed({
  get: () => normalizeGender(form.gender),
  set: value => {
    genderTouched.value = true
    form.gender = value
  },
})

watch(
  () => props.serviceRecord,
  record => {
    if (!record) return

    form.center_id = record.center_id ? String(record.center_id) : ''
    form.client_name =
      record.client_name ||
      [record.farmer?.first_name, record.farmer?.last_name].filter(Boolean).join(' ')
    form.gender = record.gender || ''
    form.project_id = record.project_id ? String(record.project_id) : ''
    form.category = record.category || record.service?.category?.[0] || form.category
    form.sub_category = record.sub_category || record.service?.sub_category?.[0] || form.sub_category
    form.program = record.program || ''
    form.no_animals_served = record.no_animals_served ?? ''
    form.date_conducted = record.date_conducted
      ? new Date(record.date_conducted).toISOString().split('T')[0]
      : ''

    if (psgcAddress.regions.value.length && record.address) {
      hydrateAddressFields(record)
    }
  },
  { immediate: true }
)

const selectedProject = computed(
  () => props.projects.find(p => String(p.id) === String(form.project_id)) || null
)

const handleProjectChange = value => {
  form.project_id = value
  const project = props.projects.find(p => String(p.id) === String(value))
  form.program = project?.program ?? ''
}

onMounted(async () => {
  await psgcAddress.load()
  await hydrateAddressFields(props.serviceRecord)

  // Pre-fill category/sub_category from the locked service if not already set
  if (!form.category && availableCategories.value.length > 0) {
    form.category = availableCategories.value[0].value
  }
  if (!form.sub_category && availableSubCategories.value.length > 0) {
    form.sub_category = availableSubCategories.value[0].value
  }
})

const submit = () => {
  form.transform(data => ({
    ...data,
    gender: genderTouched.value ? data.gender : (props.serviceRecord?.gender ?? ''),
    region: psgcAddress.getRegionNameByCode(data.region) || data.region,
    province: psgcAddress.getProvinceNameByCode(data.province) || data.province,
    municipality: psgcAddress.getCityNameByCode(data.municipality) || data.municipality,
    barangay: psgcAddress.getBarangayNameByCode(data.barangay) || data.barangay,
  }))

  if (isEditMode.value) {
    router.put(route('production-support.update', props.serviceRecord?.id), form)
  } else {
    router.post(route('production-support.store'), form)
  }
}

const getProgramBadgeColor = program => {
  const colors = {
    AI: 'bg-blue-100 text-blue-800',
    BPS: 'bg-green-100 text-green-800',
    NDDP: 'bg-yellow-100 text-yellow-800',
  }
  return colors[program] || 'bg-gray-100 text-gray-800'
}
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
                  ? 'Production Support Service Record'
                  : isEditMode
                    ? 'Edit Service Record'
                    : 'New Service Record'
              }}
            </CardTitle>
            <CardDescription>
              {{
                isViewMode
                  ? 'View the details of this service record.'
                  : isEditMode
                    ? 'Update the service record information below.'
                    : 'Fill in the information below to create a new service record.'
              }}
            </CardDescription>
          </div>
          <div v-if="isViewMode" class="flex items-center gap-2">
            <Button
              variant="outline"
              type="button"
              @click="router.visit(route('production-support.edit', props.serviceRecord?.id))"
            >
              Edit
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <form class="flex flex-col gap-4" @submit.prevent="submit">
          <!-- Service Information (locked) -->
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Service Information</CardTitle>
              <CardDescription
                >Service details — pre-filled based on Production Support (1.1.1)</CardDescription
              >
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>MFO Code</Label>
                <Input
                  :model-value="
                    service?.simplified_code || serviceRecord?.service?.simplified_code || '1.1.1'
                  "
                  disabled
                  class="bg-slate-50"
                />
              </div>

              <div class="space-y-2">
                <Label>Service Name</Label>
                <Input
                  :model-value="service?.name || serviceRecord?.service?.name || ''"
                  disabled
                  class="bg-slate-50"
                  placeholder="No service name found for 1.1.1"
                />
              </div>

              <div class="space-y-2">
                <Label>Category</Label>
                <SearchableSelect
                  v-if="!isViewMode"
                  v-model="form.category"
                  :options="availableCategories"
                  :disabled="availableCategories.length === 0"
                  placeholder="Select Category"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ serviceRecord?.category || serviceRecord?.service?.category?.[0] || 'N/A' }}
                </div>
                <p v-if="form.errors.category" class="text-sm text-red-500">
                  {{ form.errors.category }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Sub Category</Label>
                <SearchableSelect
                  v-if="!isViewMode"
                  v-model="form.sub_category"
                  :options="availableSubCategories"
                  :disabled="availableSubCategories.length === 0"
                  placeholder="Select Sub Category"
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    serviceRecord?.sub_category ||
                    serviceRecord?.service?.sub_category?.[0] ||
                    'N/A'
                  }}
                </div>
                <p v-if="form.errors.sub_category" class="text-sm text-red-500">
                  {{ form.errors.sub_category }}
                </p>
              </div>
            </CardContent>
          </Card>

          <!-- Client Information -->
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
                    serviceRecord?.center ? displayCenterLabel(serviceRecord.center.code) : 'N/A'
                  }}
                </div>
                <p v-if="form.errors.center_id" class="text-sm text-red-500">
                  {{ form.errors.center_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="client_name">Name of Client *</Label>
                <Input
                  v-if="!isViewMode"
                  id="client_name"
                  v-model="form.client_name"
                  placeholder="Enter client name"
                  required
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ serviceRecord?.client_name || 'N/A' }}
                </div>
                <p v-if="form.errors.client_name" class="text-sm text-red-500">
                  {{ form.errors.client_name }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="gender">Gender</Label>
                <Select v-if="!isViewMode" v-model="genderValue">
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select gender" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Male">Male</SelectItem>
                    <SelectItem value="Female">Female</SelectItem>
                    <SelectItem value="Other">Other</SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ normalizeGender(serviceRecord?.gender) || 'Not specified' }}
                </div>
                <p v-if="form.errors.gender" class="text-sm text-red-500">
                  {{ form.errors.gender }}
                </p>
              </div>

              <div class="md:col-span-2">
                <PsgcAddressFields
                  v-model="addressPsgc"
                  :is-view-mode="isViewMode"
                  city-field="municipality"
                  value-mode="code"
                  :psgc="psgcAddress"
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
                    <SelectItem v-for="p in projects" :key="p.id" :value="String(p.id)">
                      {{ enums.project_program?.[p.program] ?? p.program }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    enums.project_program?.[selectedProject?.program] ??
                    selectedProject?.program ??
                    enums.project_program?.[serviceRecord?.project?.program] ??
                    serviceRecord?.project?.program ??
                    'Not specified'
                  }}
                </div>
                <p v-if="form.errors.project_id" class="text-sm text-red-500">
                  {{ form.errors.project_id }}
                </p>
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
                  {{ serviceRecord?.no_animals_served || 0 }}
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
                    serviceRecord?.date_conducted
                      ? new Date(serviceRecord.date_conducted).toLocaleDateString()
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
              :href="route('production-support.index')"
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
  </div>
</template>
