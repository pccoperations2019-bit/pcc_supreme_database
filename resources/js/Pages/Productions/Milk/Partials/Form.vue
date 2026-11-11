<script setup>
import { displayCenterLabel } from '@/lib/center'
import { computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { useAuth } from '@/composable/useAuth'
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

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
  milkProduction: { type: Object, required: true },
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

const centerFilter = computed(() => ({ center_id: form.center_id }))
const farmerFilter = computed(() => ({ center_id: form.center_id, partner_id: form.partner_id }))

const form = useForm({
  center_id: props.milkProduction.center_id || props.defaultCenterId || props.center?.id || null,
  partner_id: props.milkProduction.partner_id || '',
  farmer_id: props.milkProduction.farmer_id || '',
  program: props.milkProduction.program || '',
  year: props.milkProduction.year || new Date().getFullYear(),
  month: props.milkProduction.month || '',
  total_volume_produced: props.milkProduction.total_volume_produced || '',
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
    isCreateMode.value
      ? route('milk-productions.store')
      : route('milk-productions.update', props.milkProduction.id),
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
              {{
                isViewMode
                  ? 'Milk Production'
                  : isEditMode
                    ? 'Edit Milk Production'
                    : 'Create Milk Production'
              }}
            </CardTitle>
            <CardDescription>Fill in the details of the milk production record</CardDescription>
          </div>
          <div class="flex items-center gap-2">
            <Button
              v-if="isViewMode"
              variant="outline"
              type="button"
              @click="
                router.visit(
                  route('milk-productions.edit', {
                    milk_production: props.milkProduction.id,
                  })
                )
              "
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
              <CardTitle class="text-base">Production Details</CardTitle>
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
                  {{ props.milkProduction.center?.name || 'N/A' }}
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
                    props.enums?.program?.find(p => p.value === props.milkProduction.program)
                      ?.label ||
                    props.milkProduction.program ||
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
                  :filter-params="centerFilter"
                />
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{ props.milkProduction.partner?.name || 'N/A' }}
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
                  :filter-params="farmerFilter"
                />
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{
                    props.milkProduction.farmer
                      ? `${props.milkProduction.farmer.last_name}, ${props.milkProduction.farmer.first_name}`
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
              <CardTitle class="text-base">Volume & Timeline</CardTitle>
              <CardDescription>Year, month, and volume information</CardDescription>
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
                  {{
                    monthsArray.find(m => m.value === props.milkProduction.month)?.label || 'N/A'
                  }}
                </div>
                <p v-if="form.errors.month" class="text-sm text-red-500">
                  {{ form.errors.month }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Volume <span class="text-red-500">*</span></Label>
                <Input
                  v-model="form.total_volume_produced"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  :disabled="isViewMode"
                />
                <p v-if="form.errors.total_volume_produced" class="text-sm text-red-500">
                  {{ form.errors.total_volume_produced }}
                </p>
              </div>
            </CardContent>
          </Card>

          <div v-if="!isViewMode" class="flex justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              @click="router.visit(route('milk-productions.index'))"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              class="w-full bg-black text-white hover:bg-black/90 sm:w-auto"
              :disabled="form.processing"
            >
              {{
                form.processing ? 'Saving...' : isEditMode ? 'Save Changes' : 'Create Production'
              }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
