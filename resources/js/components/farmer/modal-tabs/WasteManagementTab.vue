<script setup>
import BoolSelect from '@/components/form/BoolSelect.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const form = defineModel('form', { type: Object, required: true })

const setAdditionalDetail = (key, value) => {
  const k = String(key || '').toLowerCase()
  form.value.additional_details = { ...(form.value.additional_details || {}), [k]: value }
}

const toggleBoolean = (field, value) => {
  form.value[field] = value
}

const toggleMetadataBoolean = (key, value) => {
  setAdditionalDetail(key, value)
}

const manureMethods = [
  { field: 'is_drylot', label: 'Dry lot/Stock Piling' },
  { field: 'is_vermicomposting', label: 'Vermicomposting' },
  { field: 'is_pasture_range_paddock', label: 'Pasture/Range/Paddock' },
  { field: 'is_daily_spread', label: 'Daily Spread' },
  { field: 'is_storage_bulking', label: 'Solid Storage – Bulking Agent Addition' },
  { field: 'is_solid_storage_covered', label: 'Solid Storage covered' },
  { field: 'is_solid_storage_additives', label: 'Solid Storage – additives' },
  { field: 'is_liquid_slurry', label: 'Liquid/Slurry' },
  { field: 'is_uncovered_anaerobic_lagoon', label: 'Uncovered Anaerobic Lagoon' },
  { metadata: 'isseptictank', label: 'Septic Tank' },
  { field: 'is_anaerobic_digester', label: 'Anaerobic Digester' },
  { metadata: 'isburnedforfuel', label: 'Burned for fuel' },
]

const appliedInOptions = [
  { field: 'is_dry_rice_field', label: 'Rice Field' },
  { metadata: 'isdryforage', label: 'Forage area' },
  { field: 'is_dry_vegetable', label: 'Vegetable area' },
  { metadata: 'isdrysold', label: 'Sold' },
  { metadata: 'isdryneighbors', label: 'Give away to neighbors' },
  { metadata: 'isdryothers', label: 'Others' },
]

const isChecked = option => {
  const source = option.field ? form.value : form.value.additional_details || {}
  return option.field
    ? option.field in form.value && !!form.value[option.field]
    : !!source[option.metadata]
}

const onToggle = (option, checked) => {
  if (option.field) {
    toggleBoolean(option.field, checked)
  } else {
    toggleMetadataBoolean(option.metadata, checked)
  }
}

const otherRemarksValue = form.value.additional_details?.dryotherremarks
  ? form.value.additional_details.dryotherremarks
  : ''
</script>

<template>
  <div class="col-span-1 space-y-6 md:col-span-2">
    <!-- Manure Management Method -->
    <div class="space-y-3 rounded-lg border p-4">
      <h4 class="text-base font-semibold">Manure Management Method</h4>
      <div class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
        <label
          v-for="option in manureMethods"
          :key="option.field || option.metadata"
          class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 transition-colors hover:bg-gray-100"
        >
          <input
            type="checkbox"
            class="h-4 w-4 rounded border-gray-400"
            :checked="isChecked(option)"
            @change="onToggle(option, $event.target.checked)"
          />
          <span class="text-sm">{{ option.label }}</span>
        </label>
      </div>
    </div>

    <!-- Dry Lot Details -->
    <div class="space-y-3 rounded-lg border p-4">
      <h4 class="text-base font-semibold">Dry Lot Details</h4>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div v-if="form.is_drylot" class="space-y-2">
          <Label>Duration of Stocking</Label>
          <Input v-model.number="form.duration_of_stocking" type="number" min="0" />
        </div>
        <div v-if="form.is_drylot" class="space-y-2">
          <BoolSelect
            id="waste-applied-in-field"
            label="Applied in the Field?"
            :model-value="form.is_applied_in_the_field"
            @update:model-value="value => toggleBoolean('is_applied_in_the_field', value)"
          />
        </div>
      </div>

      <div v-if="form.is_drylot && form.is_applied_in_the_field" class="space-y-3">
        <Label>Applied In</Label>
        <div class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
          <label
            v-for="option in appliedInOptions"
            :key="option.field || option.metadata"
            class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 transition-colors hover:bg-gray-100"
          >
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-gray-400"
              :checked="isChecked(option)"
              @change="onToggle(option, $event.target.checked)"
            />
            <span class="text-sm">{{ option.label }}</span>
          </label>
        </div>
        <div v-if="form.additional_details?.isdryothers" class="space-y-2">
          <Label>Other Remarks</Label>
          <Input
            :model-value="otherRemarksValue"
            @update:model-value="value => setAdditionalDetail('dryotherremarks', value)"
          />
        </div>
      </div>
    </div>
  </div>
</template>
