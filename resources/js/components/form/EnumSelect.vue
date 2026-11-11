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
    type: [String, Number],
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
  options: {
    type: [Object, Array],
    default: () => ({}),
  },
  placeholder: {
    type: String,
    default: 'Select',
  },
  error: {
    type: [String, Array],
    default: '',
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
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const entries = computed(() => {
  if (Array.isArray(props.options)) {
    return props.options.map(option => ({
      value: option.value ?? option.key ?? option.id,
      label: option.label ?? option.value ?? option.key ?? option.id,
    }))
  }

  return Object.entries(props.options || {}).map(([value, label]) => ({
    value,
    label,
  }))
})
</script>

<template>
  <FormField :id="id" :label="label" :error="error" :required="required">
    <Select :model-value="model" :disabled="disabled" @update:model-value="model = $event">
      <SelectTrigger class="h-8 w-full">
        <SelectValue :placeholder="placeholder" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem v-for="option in entries" :key="String(option.value)" :value="option.value">
          {{ option.label }}
        </SelectItem>
      </SelectContent>
    </Select>
  </FormField>
</template>
