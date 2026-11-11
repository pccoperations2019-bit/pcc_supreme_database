<script setup>
import { displayCenterLabel } from '@/lib/center'
import { computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import SearchableSelect from '@/components/SearchableSelect.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { monthsArray } from '@/enums/MonthEnum'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
import { useAuth } from '@/composable/useAuth'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
  meatSale: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  defaultProjectId: { type: Number, default: null },
  center: { type: Object, default: null },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
  enums: { type: Object, default: () => ({}) },
})

const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')
const isViewMode = computed(() => props.mode === 'view')

const { isCenterScoped } = useAuth()

const form = useForm({
  center_id: props.meatSale.center_id || props.defaultCenterId || props.center?.id || null,
  partner_id: props.meatSale.partner_id || '',
  farmer_id: props.meatSale.farmer_id || '',
  program: props.meatSale.program || '',
  year: props.meatSale.year || new Date().getFullYear(),
  month: props.meatSale.month || '',
  total_sales: props.meatSale.total_sales || '',
})

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

const onSubmit = () => {
  if (!isCreateMode.value && !isEditMode.value) return

  form.submit(
    isCreateMode.value ? 'post' : 'put',
    isCreateMode.value ? route('meat-sales.store') : route('meat-sales.update', props.meatSale.id),
    {
      preserveScroll: true,
      onError: errors => {
        const first = Object.values(errors || {})[0]
        const message = Array.isArray(first) ? first[0] : first
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
              {{ isViewMode ? 'Meat Sales' : isEditMode ? 'Edit Meat Sales' : 'Create Meat Sales' }}
            </CardTitle>
            <CardDescription>Fill in the details of the meat sales record</CardDescription>
          </div>
          <div class="flex items-center gap-2">
            <Button
              v-if="isViewMode"
              variant="outline"
              type="button"
              @click="router.visit(route('meat-sales.edit', { meat_sale: props.meatSale.id }))"
            >
              Edit
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Sales Details</CardTitle>
              <CardDescription>Center and program information</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div v-if="!isCenterScoped || isViewMode" class="space-y-2">
                <Label>Center <span class="text-red-500">*</span></Label>
                <template v-if="!isViewMode">
                  <Select
                    v-if="!disableCenterField && !isCenterScoped && props.centers.length > 1"
                    :model-value="form.center_id ? String(form.center_id) : ''"
                    @update:model-value="val => (form.center_id = val)"
                  >
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
                      props.center
                        ? displayCenterLabel(props.center.code)
                        : centers.length === 1
                          ? displayCenterLabel(centers[0].code)
                          : 'N/A'
                    }}
                  </div>
                </template>
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{ props.meatSale.center?.name || 'N/A' }}
                </div>
                <p v-if="form.errors.center_id" class="text-sm text-red-500">
                  {{ form.errors.center_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Program <span class="text-red-500">*</span></Label>
                <Select
                  v-if="!isViewMode"
                  :model-value="form.program"
                  @update:model-value="val => (form.program = val)"
                >
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select program" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="prog in enums.program" :key="prog.value" :value="prog.value">
                      {{ prog.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{
                    props.enums?.program?.find(p => p.value === props.meatSale.program)?.label ||
                    props.meatSale.program ||
                    'N/A'
                  }}
                </div>
                <p v-if="form.errors.program" class="text-sm text-red-500">
                  {{ form.errors.program }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Partner</Label>
                <SearchableSelect
                  v-if="!isViewMode"
                  v-model="form.partner_id"
                  endpoint="/xhr/partners"
                  placeholder="Search partner..."
                />
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{ props.meatSale.partner?.name || 'N/A' }}
                </div>
                <p v-if="form.errors.partner_id" class="text-sm text-red-500">
                  {{ form.errors.partner_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Farmer</Label>
                <SearchableSelect
                  v-if="!isViewMode"
                  v-model="form.farmer_id"
                  endpoint="/xhr/farmers"
                  placeholder="Search farmer..."
                />
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{
                    props.meatSale.farmer
                      ? `${props.meatSale.farmer.last_name}, ${props.meatSale.farmer.first_name}`
                      : 'N/A'
                  }}
                </div>
                <p v-if="form.errors.farmer_id" class="text-sm text-red-500">
                  {{ form.errors.farmer_id }}
                </p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base">Sales & Timeline</CardTitle>
              <CardDescription>Year, month, and sales information</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div class="space-y-2">
                <Label>Year <span class="text-red-500">*</span></Label>
                <Input
                  v-model="form.year"
                  type="number"
                  min="2000"
                  max="2100"
                  :disabled="isViewMode"
                  @input="form.year = String(form.year).slice(0, 4)"
                />
                <p v-if="form.errors.year" class="text-sm text-red-500">
                  {{ form.errors.year }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Month <span class="text-red-500">*</span></Label>
                <Select
                  v-if="!isViewMode"
                  :model-value="form.month ? String(form.month) : ''"
                  @update:model-value="val => (form.month = val ? Number(val) : null)"
                >
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select month" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="m in monthsArray" :key="m.value" :value="String(m.value)">
                      {{ m.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{ monthsArray.find(m => m.value === props.meatSale.month)?.label || 'N/A' }}
                </div>
                <p v-if="form.errors.month" class="text-sm text-red-500">
                  {{ form.errors.month }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Total Sales <span class="text-red-500">*</span></Label>
                <Input
                  v-model="form.total_sales"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  :disabled="isViewMode"
                />
                <p v-if="form.errors.total_sales" class="text-sm text-red-500">
                  {{ form.errors.total_sales }}
                </p>
              </div>
            </CardContent>
          </Card>

          <div v-if="!isViewMode" class="flex justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              @click="router.visit(route('meat-sales.index'))"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              class="w-full bg-black text-white hover:bg-black/90 sm:w-auto"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Saving...' : isEditMode ? 'Save Changes' : 'Create Sales' }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
