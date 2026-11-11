<script setup>
import { computed } from 'vue'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import FormField from '@/components/form/FormField.vue'

const props = defineProps({
  modelValue: {
    type: [String, Number, Boolean],
    default: '',
  },
  id: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    default: '',
  },
  error: {
    type: [String, Array],
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Select',
  },
  yesLabel: {
    type: String,
    default: 'Yes',
  },
  noLabel: {
    type: String,
    default: 'No',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const model = computed({
  get: () => {
    const v = props.modelValue
    if (v === true || v === 1 || v === '1' || v === 'true' || v === 'yes' || v === 'y') return '1'
    if (v === false || v === 0 || v === '0' || v === 'false' || v === 'no' || v === 'n') return '0'
    return v ?? ''
  },
  set: value => emit('update:modelValue', value),
})
</script>

<template>
  <FormField :id="id" :label="label" :error="error" :required="required">
    <Select :model-value="model" :disabled="disabled" @update:model-value="model = $event">
      <SelectTrigger class="h-8 w-full">
        <SelectValue :placeholder="placeholder" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value="1">{{ yesLabel }}</SelectItem>
        <SelectItem value="0">{{ noLabel }}</SelectItem>
      </SelectContent>
    </Select>
  </FormField>
</template>
