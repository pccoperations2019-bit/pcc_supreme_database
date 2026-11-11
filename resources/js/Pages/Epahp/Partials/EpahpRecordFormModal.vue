<script setup>
import { displayCenterLabel } from '@/lib/center'
import { computed, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const props = defineProps({
  open: { type: Boolean, default: false },
  table: { type: String, default: null },
  record: { type: Object, default: null },
  centers: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:open'])

const routeNames = {
  gatasang_kalabaw: {
    store: 'epahp.gatasang-kalabaw.store',
    update: 'epahp.gatasang-kalabaw.update',
    param: 'epahpGatasangKalabaw',
  },
  capacity_building: {
    store: 'epahp.capacity-building.store',
    update: 'epahp.capacity-building.update',
    param: 'epahpCapacityBuilding',
  },
}

const titleMap = {
  gatasang_kalabaw: 'Expanded Gatasang Kalabaw',
  capacity_building: 'Capacity Building',
}

const fieldsByTable = {
  gatasang_kalabaw: [
    { key: 'center_id', label: 'Center', type: 'select', required: true },
    { key: 'year', label: 'Year', type: 'number', required: true, step: 1 },
    {
      key: 'number_of_cooperatives',
      label: 'Number of Cooperatives',
      type: 'number',
      required: true,
    },
    {
      key: 'number_of_farmer_recipients',
      label: 'Number of Farmer Recipients',
      type: 'number',
      required: true,
    },
    {
      key: 'number_of_animals_entrusted',
      label: 'Number of Animals Entrusted',
      type: 'number',
      required: true,
    },
    {
      key: 'dswd_counterpart',
      label: 'DSWD Counterpart',
      type: 'number',
      required: false,
      step: 0.01,
    },
  ],
  capacity_building: [
    { key: 'center_id', label: 'Center', type: 'select', required: true },
    { key: 'year', label: 'Year', type: 'number', required: true, step: 1 },
    {
      key: 'number_of_cooperatives',
      label: 'Number of Cooperatives',
      type: 'number',
      required: true,
    },
    {
      key: 'number_of_farmers_trained',
      label: 'Number of Farmers Trained',
      type: 'number',
      required: true,
    },
    { key: 'training_conducted', label: 'Training Conducted', type: 'number', required: true },
    {
      key: 'pcc_budget_allocated',
      label: 'PCC Budget Allocated',
      type: 'number',
      required: false,
      step: 0.01,
    },
  ],
}

const fields = computed(() => (props.table ? fieldsByTable[props.table] || [] : []))

const isEdit = computed(() => !!props.record?.id)

const title = computed(() => (props.table ? titleMap[props.table] || '' : ''))
const dialogTitle = computed(() => `${isEdit.value ? 'Edit' : 'Add'} ${title.value}`)
const dialogDescription = computed(() =>
  isEdit.value
    ? `Update the ${title.value} information below.`
    : `Add a new ${title.value} record below.`
)

const form = reactive({
  center_id: '',
  year: new Date().getFullYear(),
  number_of_cooperatives: 0,
  number_of_farmer_recipients: 0,
  number_of_animals_entrusted: 0,
  dswd_counterpart: '',
  number_of_farmers_trained: 0,
  training_conducted: 0,
  pcc_budget_allocated: '',
})

const processing = ref(false)
const errors = ref({})

const routeInfo = computed(() => (props.table ? routeNames[props.table] : null))

const url = computed(() => {
  if (!routeInfo.value) return null
  return isEdit.value
    ? route(routeInfo.value.update, { [routeInfo.value.param]: props.record.id })
    : route(routeInfo.value.store)
})

const defaultValue = field => {
  if (field.type === 'select') return ''
  if (field.key === 'year') return new Date().getFullYear()
  if (field.type === 'number') return 0
  return ''
}

watch(
  () => [props.open, props.record, props.table],
  () => {
    if (!props.open || !props.table) return

    if (isEdit.value) {
      for (const field of fields.value) {
        const value = props.record[field.key]
        form[field.key] =
          field.type === 'select' ? (value ? String(value) : '') : (value ?? defaultValue(field))
      }
    } else {
      for (const field of fields.value) {
        form[field.key] = defaultValue(field)
      }
    }
    errors.value = {}
  },
  { immediate: true }
)

const close = () => emit('update:open', false)

const submit = () => {
  if (!url.value) return

  const payload = {}
  for (const field of fields.value) {
    payload[field.key] = form[field.key]
  }

  processing.value = true
  errors.value = {}

  const method = isEdit.value ? 'put' : 'post'

  router[method](url.value, payload, {
    preserveScroll: true,
    onSuccess: () => {
      close()
    },
    onError: errs => {
      errors.value = errs
      Object.entries(errs).forEach(([field, message]) => {
        if (field === 'center_id') return
        toast.error(`${field}: ${Array.isArray(message) ? message[0] : message}`)
      })
    },
    onFinish: () => {
      processing.value = false
    },
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="val => emit('update:open', val)">
    <DialogContent class="flex h-fit max-h-[90vh] w-[95vw] max-w-2xl flex-col overflow-hidden p-0">
      <DialogHeader class="shrink-0 border-b p-6">
        <DialogTitle>{{ dialogTitle }}</DialogTitle>
        <DialogDescription>{{ dialogDescription }}</DialogDescription>
      </DialogHeader>

      <form v-if="open" class="flex flex-col overflow-hidden" @submit.prevent="submit">
        <div class="max-h-[60vh] overflow-y-auto p-6">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div v-for="field in fields" :key="field.key" class="space-y-2">
              <Label>
                {{ field.label }}
                <span v-if="field.required" class="text-red-500">*</span>
              </Label>

              <Select
                v-if="field.type === 'select'"
                :model-value="form[field.key] ? String(form[field.key]) : ''"
                @update:model-value="val => (form[field.key] = val)"
              >
                <SelectTrigger class="h-9 w-full">
                  <SelectValue placeholder="Select center" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="center in centers" :key="center.id" :value="String(center.id)">
                    {{ center.code ? displayCenterLabel(center.code) : center.name }}
                  </SelectItem>
                </SelectContent>
              </Select>

              <Input
                v-else
                v-model.number="form[field.key]"
                type="number"
                :step="field.step || 1"
                :min="field.key === 'year' ? 1900 : 0"
              />

              <p v-if="errors[field.key]" class="text-sm text-red-500">
                {{ Array.isArray(errors[field.key]) ? errors[field.key][0] : errors[field.key] }}
              </p>
            </div>
          </div>
        </div>

        <DialogFooter class="bg-muted/20 shrink-0 border-t p-6">
          <Button type="button" variant="outline" @click="close">Cancel</Button>
          <Button
            type="submit"
            :disabled="processing"
            class="bg-black text-white hover:bg-black/90"
          >
            {{ processing ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Record' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
