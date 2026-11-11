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
  dairyBoxSale: { type: Object, required: true },
  dairyBoxes: { type: Array, default: () => [] },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  defaultCenterId: { type: Number, default: null },
})

const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')
const isViewMode = computed(() => props.mode === 'view')

const dairyBoxOptions = computed(() =>
  props.dairyBoxes.map(db => ({
    value: String(db.id),
    label: db.dairy_box_id
      ? `${db.business_name || 'Unnamed'} (${db.dairy_box_id})`
      : db.business_name || 'Unnamed',
  }))
)

const partnerOptions = computed(() =>
  props.partners.map(p => ({
    value: String(p.id),
    label: p.name,
  }))
)

const initForm = (sale, defaultCenterId) => ({
  dairy_box_id: sale?.dairy_box_id || '',
  center_id: String(sale?.center_id || defaultCenterId || ''),
  partner_id: String(sale?.partner_id || ''),
  year: sale?.year || new Date().getFullYear(),
  month: String(sale?.month || ''),
  total_sales: sale?.total_sales ?? '',
})

const { isCenterScoped } = useAuth()

const form = useForm(initForm(props.dairyBoxSale, props.defaultCenterId))

watch(
  () => props.dairyBoxSale,
  sale => {
    if ((isEditMode.value || isViewMode.value) && sale?.id) {
      const vals = initForm(sale, null)
      Object.assign(form, vals)
    }
  },
  { immediate: true, deep: true }
)

watch(
  () => props.defaultCenterId,
  id => {
    if (isCreateMode.value && (!form.center_id || form.center_id === '') && id) {
      form.center_id = String(id)
    }
  },
  { immediate: true }
)

const onSubmit = () => {
  if (!isCreateMode.value && !isEditMode.value) return

  form.submit(
    isCreateMode.value ? 'post' : 'put',
    isCreateMode.value
      ? route('dairy-box-sales.store')
      : route('dairy-box-sales.update', props.dairyBoxSale.id),
    {
      preserveScroll: true,
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
                  ? 'Dairy Box Sale'
                  : isEditMode
                    ? 'Edit Dairy Box Sale'
                    : 'Create Dairy Box Sale'
              }}
            </CardTitle>
            <CardDescription>Fill in the details of the dairy box sale record</CardDescription>
          </div>
          <div class="flex items-center gap-2">
            <Button
              v-if="isViewMode"
              variant="outline"
              type="button"
              @click="
                router.visit(
                  route('dairy-box-sales.edit', { dairy_box_sale: props.dairyBoxSale.id })
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
              <CardTitle class="text-base">Associations</CardTitle>
              <CardDescription>Center and partner</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div v-if="!isCenterScoped || isViewMode" class="space-y-2">
                <Label>Center <span class="text-red-500">*</span></Label>
                <template v-if="!isViewMode">
                  <Select v-model="form.center_id">
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
                </template>
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{ props.dairyBoxSale.center?.name || 'N/A' }}
                </div>
                <p v-if="form.errors.center_id" class="text-sm text-red-500">
                  {{ form.errors.center_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Partner</Label>
                <SearchableSelect
                  v-if="!isViewMode"
                  v-model="form.partner_id"
                  :options="partnerOptions"
                  placeholder="Search partner..."
                />
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{ props.dairyBoxSale.partner?.name || 'N/A' }}
                </div>
                <p v-if="form.errors.partner_id" class="text-sm text-red-500">
                  {{ form.errors.partner_id }}
                </p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base">Dairy Box</CardTitle>
              <CardDescription>Select the dairy box for this sale</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-1">
              <div class="space-y-2">
                <Label>Dairy Box <span class="text-red-500">*</span></Label>
                <SearchableSelect
                  v-if="!isViewMode"
                  v-model="form.dairy_box_id"
                  :options="dairyBoxOptions"
                  placeholder="Search dairy box..."
                />
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{
                    props.dairyBoxSale.dairy_box?.business_name ||
                    props.dairyBoxSale.dairy_box?.dairy_box_id ||
                    'N/A'
                  }}
                </div>
                <p v-if="form.errors.dairy_box_id" class="text-sm text-red-500">
                  {{ form.errors.dairy_box_id }}
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
                <Select v-if="!isViewMode" v-model="form.month">
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
                  {{ monthsArray.find(m => m.value === props.dairyBoxSale.month)?.label || 'N/A' }}
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
              @click="router.visit(route('dairy-box-sales.index'))"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              class="w-full bg-black text-white hover:bg-black/90 sm:w-auto"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Saving...' : isEditMode ? 'Save Changes' : 'Create Sale' }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
