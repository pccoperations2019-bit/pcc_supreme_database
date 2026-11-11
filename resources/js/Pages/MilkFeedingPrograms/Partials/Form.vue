<script setup>
import { displayCenterLabel } from '@/lib/center'
import { computed, watch, watchEffect, ref, onMounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import SearchableSelect from '@/components/SearchableSelect.vue'
import PsgcAddressFields from '@/components/PsgcAddressFields.vue'
import { usePsgcAddress } from '@/composable/usePsgcAddress'
import { usePsgcCascade } from '@/composable/usePsgcCascade'
import { useAuth } from '@/composable/useAuth'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { toastFlash } from '@/lib/flashToasts'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'

const route = globalThis.route

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
  milkFeedingProgram: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  defaultProjectId: { type: Number, default: null },
  center: { type: Object, default: null },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
  enums: { type: Object, default: () => ({}) },
})

const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')
const isViewMode = computed(() => props.mode === 'view')

const FUNDER_OTHER_VALUE = '__other__'
const PROCUREMENT_OTHER_VALUE = '__other__'

const { isCenterScoped } = useAuth()

const addressPsgc = usePsgcAddress()
const { getRegionNameByCode, getProvinceNameByCode, getCityNameByCode, getBarangayNameByCode } =
  addressPsgc

const form = useForm({
  project_id: props.milkFeedingProgram.project_id || props.defaultProjectId || null,
  center_id:
    props.milkFeedingProgram.center_id || props.defaultCenterId || props.center?.id || null,
  partner_id: props.milkFeedingProgram.partner_id || null,
  program: props.milkFeedingProgram.program || '',
  year: props.milkFeedingProgram.year || new Date().getFullYear(),
  funded_by: props.milkFeedingProgram.funded_by || '',
  funded_by_other: '',
  region: '',
  province: '',
  city: '',
  barangay: '',
  district: props.milkFeedingProgram.address?.district || props.milkFeedingProgram.district || '',
  division: props.milkFeedingProgram.division || '',
  milk_packs: props.milkFeedingProgram.milk_packs ?? 0,
  total_volume_requirements: props.milkFeedingProgram.total_volume_requirements ?? 0,
  raw_milk_used: props.milkFeedingProgram.raw_milk_used ?? 0,
  whole_milk: props.milkFeedingProgram.whole_milk ?? 0,
  skimmed_milk: props.milkFeedingProgram.skimmed_milk ?? 0,
  sugar: props.milkFeedingProgram.sugar ?? 0,
  milk_type: props.milkFeedingProgram.milk_type || '',
  milk_subcategory: props.milkFeedingProgram.milk_subcategory || '',
  price_per_milk_pack: props.milkFeedingProgram.price_per_milk_pack ?? 0,
  feeding_days: props.milkFeedingProgram.feeding_days ?? 0,
  number_of_beneficiaries: props.milkFeedingProgram.number_of_beneficiaries ?? 0,
  total_contract_amount: props.milkFeedingProgram.total_contract_amount ?? 0,
  service_fee: props.milkFeedingProgram.service_fee ?? 0,
  gross_income: props.milkFeedingProgram.gross_income ?? 0,
  liquidation_date: props.milkFeedingProgram.liquidation_date || '',
  mode_of_procurement: props.milkFeedingProgram.mode_of_procurement || '',
  mode_of_procurement_other: '',
  moa_signing_date: props.milkFeedingProgram.moa_signing_date || '',
  fund_transfer_date: props.milkFeedingProgram.fund_transfer_date || '',
  date_started: props.milkFeedingProgram.date_started || '',
  date_completed: props.milkFeedingProgram.date_completed || '',
})

const { hydrateAddressFields } = usePsgcCascade({
  form,
  psgc: addressPsgc,
  cityField: 'city',
  withDistrict: true,
})

onMounted(async () => {
  await addressPsgc.load()
  if (!props.milkFeedingProgram.id) return

  await hydrateAddressFields({
    address: {
      region: props.milkFeedingProgram.address?.region || props.milkFeedingProgram.region || '',
      province:
        props.milkFeedingProgram.address?.province || props.milkFeedingProgram.province || '',
      city: props.milkFeedingProgram.address?.city || props.milkFeedingProgram.municipality || '',
      barangay: props.milkFeedingProgram.address?.barangay || '',
      district: props.milkFeedingProgram.address?.district || '',
    },
  })
})

const didInitializeOtherFields = ref(false)
watchEffect(() => {
  if (didInitializeOtherFields.value) return
  didInitializeOtherFields.value = true

  const fundedBy = String(props.milkFeedingProgram.funded_by || '')
  if (fundedBy && !Object.prototype.hasOwnProperty.call(props.enums?.fundedBy || {}, fundedBy)) {
    form.funded_by = FUNDER_OTHER_VALUE
    form.funded_by_other = fundedBy
  }

  const procurement = String(props.milkFeedingProgram.mode_of_procurement || '')
  if (
    procurement &&
    !Object.prototype.hasOwnProperty.call(props.enums?.modeOfProcurement || {}, procurement)
  ) {
    form.mode_of_procurement = PROCUREMENT_OTHER_VALUE
    form.mode_of_procurement_other = procurement
  }
})

watch(
  () => form.center_id,
  () => {
    form.partner_id = null
  }
)

const centerOptions = computed(() =>
  props.centers.map(c => ({ value: String(c.id), label: displayCenterLabel(c.code) }))
)

watch(
  () => props.defaultCenterId,
  id => {
    if (isCreateMode.value && (!form.center_id || form.center_id === null) && id) {
      form.center_id = String(id)
    }
  },
  { immediate: true }
)

watch(
  () => props.center,
  center => {
    if (isCreateMode.value && (!form.center_id || form.center_id === null) && center?.id) {
      form.center_id = center.id
    }
  },
  { immediate: true }
)

const filteredPartners = computed(() => {
  const centerId = Number(form.center_id || 0)
  if (!centerId) return []
  return props.partners.filter(p => Number(p.center_id) === centerId)
})

const partnerOptions = computed(() => {
  const opts = filteredPartners.value.map(p => ({ value: p.id, label: `${p.name}` }))
  if (opts.length > 0) return opts
  return [{ value: null, label: 'None' }]
})

const onSubmit = () => {
  if (!isCreateMode.value && !isEditMode.value) return

  form
    .transform(data => ({
      ...data,
      funded_by:
        data.funded_by === FUNDER_OTHER_VALUE
          ? (data.funded_by_other || '').trim()
          : data.funded_by,
      region: getRegionNameByCode(data.region) || data.region,
      province: getProvinceNameByCode(data.province) || data.province,
      municipality: getCityNameByCode(data.city) || data.city,
      barangay: getBarangayNameByCode(data.barangay) || data.barangay,
      district: data.district,
      mode_of_procurement:
        data.mode_of_procurement === PROCUREMENT_OTHER_VALUE
          ? (data.mode_of_procurement_other || '').trim()
          : data.mode_of_procurement,
    }))
    .submit(
      isCreateMode.value ? 'post' : 'put',
      isCreateMode.value
        ? route('milk-feeding-programs.store')
        : route('milk-feeding-programs.update', props.milkFeedingProgram.id),
      {
        preserveScroll: true,
        onError: errors => {
          const first = Object.values(errors || {})[0]
          const message = Array.isArray(first) ? first[0] : first
          toastFlash({ error: message || 'Validation failed. Please check the form.' })
        },
      }
    )
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
                  ? 'Milk Feeding Program'
                  : isEditMode
                    ? 'Edit Milk Feeding Program'
                    : 'Create Milk Feeding Program'
              }}
            </CardTitle>
            <CardDescription>Fill in the details of the milk feeding program</CardDescription>
          </div>
          <div class="flex items-center gap-2">
            <Button
              v-if="isViewMode"
              variant="outline"
              type="button"
              @click="
                router.visit(route('milk-feeding-programs.edit', props.milkFeedingProgram.id))
              "
            >
              Edit
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
          <input v-model="form.project_id" type="hidden" />

          <Card>
            <CardHeader>
              <CardTitle class="text-base">Program Details</CardTitle>
              <CardDescription>Center, supplier, and location details</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div v-if="!isCenterScoped || isViewMode" class="space-y-2">
                <Label>Center *</Label>
                <template v-if="!isViewMode">
                  <SearchableSelect
                    v-if="!disableCenterField && !isCenterScoped && props.centers.length > 1"
                    v-model="form.center_id"
                    :options="centerOptions"
                    placeholder="Select center"
                  />
                  <div v-else class="rounded bg-gray-50 p-2 text-sm">
                    {{
                      props.center
                        ? displayCenterLabel(props.center.code)
                        : centers.length === 1
                          ? displayCenterLabel(centers[0].code)
                          : 'N/A'
                    }}
                  </div>
                </template>
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{ props.milkFeedingProgram.center?.name || 'N/A' }}
                </div>
                <p v-if="form.errors.center_id" class="text-sm text-red-500">
                  {{ form.errors.center_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Supplier</Label>
                <SearchableSelect
                  v-if="!isViewMode"
                  v-model="form.partner_id"
                  :options="partnerOptions"
                  :disabled="!form.center_id"
                  placeholder="Select supplier (optional)"
                />
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{ props.milkFeedingProgram.partner?.name || 'None' }}
                </div>
                <p v-if="form.errors.partner_id" class="text-sm text-red-500">
                  {{ form.errors.partner_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Type *</Label>
                <Select
                  v-if="!isViewMode"
                  :model-value="form.program"
                  @update:model-value="val => (form.program = val)"
                >
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="(labelText, value) in enums.programs"
                      :key="value"
                      :value="value"
                    >
                      {{ labelText }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{ props.enums?.programs?.[props.milkFeedingProgram.program] || 'N/A' }}
                </div>
                <p v-if="form.errors.program" class="text-sm text-red-500">
                  {{ form.errors.program }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Year *</Label>
                <Input
                  v-model="form.year"
                  type="number"
                  min="2000"
                  max="2100"
                  :disabled="isViewMode"
                  @input="form.year = String(form.year).slice(0, 4)"
                />
                <p v-if="form.errors.year" class="text-sm text-red-500">{{ form.errors.year }}</p>
              </div>

              <div class="space-y-2">
                <Label>Funded By</Label>
                <Select
                  v-if="!isViewMode"
                  :model-value="form.funded_by"
                  @update:model-value="val => (form.funded_by = val)"
                >
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select funder" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="(labelText, value) in enums.fundedBy"
                      :key="value"
                      :value="value"
                    >
                      {{ labelText }}
                    </SelectItem>
                    <SelectItem :value="FUNDER_OTHER_VALUE">Other</SelectItem>
                  </SelectContent>
                </Select>
                <Input
                  v-if="!isViewMode && form.funded_by === FUNDER_OTHER_VALUE"
                  v-model="form.funded_by_other"
                  placeholder="Enter funder name"
                />
                <div
                  v-if="isViewMode"
                  class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm"
                >
                  {{
                    props.enums?.fundedBy?.[props.milkFeedingProgram.funded_by] ||
                    props.milkFeedingProgram.funded_by ||
                    'N/A'
                  }}
                </div>
                <p v-if="form.errors.funded_by" class="text-sm text-red-500">
                  {{ form.errors.funded_by }}
                </p>
              </div>

              <div class="md:col-span-2">
                <PsgcAddressFields
                  :model-value="form"
                  :is-view-mode="isViewMode"
                  city-field="city"
                  value-mode="code"
                  :psgc="addressPsgc"
                  @update:model-value="Object.assign(form, $event)"
                />
                <p v-if="form.errors.district" class="mt-2 text-sm text-red-500">
                  {{ form.errors.district }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Division</Label>
                <Input v-model="form.division" :disabled="isViewMode" />
                <p v-if="form.errors.division" class="text-sm text-red-500">
                  {{ form.errors.division }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Mode of Procurement</Label>
                <Select
                  v-if="!isViewMode"
                  :model-value="form.mode_of_procurement"
                  @update:model-value="val => (form.mode_of_procurement = val)"
                >
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select mode" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="(labelText, value) in enums.modeOfProcurement"
                      :key="value"
                      :value="value"
                    >
                      {{ labelText }}
                    </SelectItem>
                    <SelectItem :value="PROCUREMENT_OTHER_VALUE">Other</SelectItem>
                  </SelectContent>
                </Select>
                <Input
                  v-if="!isViewMode && form.mode_of_procurement === PROCUREMENT_OTHER_VALUE"
                  v-model="form.mode_of_procurement_other"
                  placeholder="Enter mode of procurement"
                />
                <div
                  v-if="isViewMode"
                  class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm"
                >
                  {{
                    props.enums?.modeOfProcurement?.[
                      props.milkFeedingProgram.mode_of_procurement
                    ] ||
                    props.milkFeedingProgram.mode_of_procurement ||
                    'N/A'
                  }}
                </div>
                <p v-if="form.errors.mode_of_procurement" class="text-sm text-red-500">
                  {{ form.errors.mode_of_procurement }}
                </p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base">Milk & Volume</CardTitle>
              <CardDescription>Milk packs, requirements, and ingredients</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>Milk Packs</Label>
                <Input v-model="form.milk_packs" type="number" min="0" :disabled="isViewMode" />
              </div>
              <div class="space-y-2">
                <Label>Total Volume Requirements (L)</Label>
                <Input
                  v-model="form.total_volume_requirements"
                  type="number"
                  step="0.01"
                  min="0"
                  :disabled="isViewMode"
                />
              </div>
              <div class="space-y-2">
                <Label>Raw Milk Used in Liters</Label>
                <Input
                  v-model="form.raw_milk_used"
                  type="number"
                  step="0.01"
                  min="0"
                  :disabled="isViewMode"
                />
              </div>
              <div class="space-y-2">
                <Label>Whole Milk (kg)</Label>
                <Input
                  v-model="form.whole_milk"
                  type="number"
                  step="0.01"
                  min="0"
                  :disabled="isViewMode"
                />
              </div>
              <div class="space-y-2">
                <Label>Skimmed Milk (kg)</Label>
                <Input
                  v-model="form.skimmed_milk"
                  type="number"
                  step="0.01"
                  min="0"
                  :disabled="isViewMode"
                />
              </div>
              <div class="space-y-2">
                <Label>Sugar</Label>
                <Input
                  v-model="form.sugar"
                  type="number"
                  step="0.01"
                  min="0"
                  :disabled="isViewMode"
                />
              </div>
              <div class="space-y-2">
                <Label>Milk Product</Label>
                <Select
                  v-if="!isViewMode"
                  :model-value="form.milk_type"
                  @update:model-value="val => (form.milk_type = val)"
                >
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select milk product" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="(labelText, value) in enums.milkType"
                      :key="value"
                      :value="value"
                    >
                      {{ labelText }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div
                  v-if="isViewMode"
                  class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm"
                >
                  {{ props.enums?.milkType?.[props.milkFeedingProgram.milk_type] || 'N/A' }}
                </div>
                <p v-if="form.errors.milk_type" class="text-sm text-red-500">
                  {{ form.errors.milk_type }}
                </p>
              </div>
              <div class="space-y-2">
                <Label>Price</Label>
                <Input
                  v-model="form.price_per_milk_pack"
                  type="number"
                  step="0.01"
                  min="0"
                  :disabled="isViewMode"
                />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base">Beneficiaries & Funds</CardTitle>
              <CardDescription>Feeding days, beneficiaries, and amounts</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div class="space-y-2">
                <Label>Feeding Days</Label>
                <Input v-model="form.feeding_days" type="number" min="0" :disabled="isViewMode" />
              </div>
              <div class="space-y-2">
                <Label>Beneficiaries</Label>
                <Input
                  v-model="form.number_of_beneficiaries"
                  type="number"
                  min="0"
                  :disabled="isViewMode"
                />
              </div>
              <div class="space-y-2">
                <Label>Total Funds Transferred</Label>
                <Input
                  v-model="form.total_contract_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  :disabled="isViewMode"
                />
              </div>
              <div class="space-y-2">
                <Label>Service Fee/ Admin Cost</Label>
                <Input
                  v-model="form.service_fee"
                  type="number"
                  step="0.01"
                  min="0"
                  :disabled="isViewMode"
                />
              </div>
              <div class="space-y-2">
                <Label>Milk Cost</Label>
                <Input
                  v-model="form.gross_income"
                  type="number"
                  step="0.01"
                  min="0"
                  :disabled="isViewMode"
                />
              </div>
              <div class="space-y-2">
                <Label>Date of Liquidation</Label>
                <Input v-model="form.liquidation_date" type="date" :disabled="isViewMode" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base">Timeline</CardTitle>
              <CardDescription>Important dates</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-4">
              <div class="space-y-2">
                <Label>Date of MOA Signing</Label>
                <Input v-model="form.moa_signing_date" type="date" :disabled="isViewMode" />
              </div>
              <div class="space-y-2">
                <Label>Date of Fund Transfer</Label>
                <Input v-model="form.fund_transfer_date" type="date" :disabled="isViewMode" />
              </div>
              <div class="space-y-2">
                <Label>Date Started</Label>
                <Input v-model="form.date_started" type="date" :disabled="isViewMode" />
              </div>
              <div class="space-y-2">
                <Label>Date Completed</Label>
                <Input v-model="form.date_completed" type="date" :disabled="isViewMode" />
              </div>
            </CardContent>
          </Card>

          <div v-if="!isViewMode" class="flex justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              @click="router.visit(route('milk-feeding-programs.index'))"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              class="w-full bg-black text-white hover:bg-black/90 sm:w-auto"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Saving...' : isEditMode ? 'Save Changes' : 'Create Program' }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
