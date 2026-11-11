<script setup>
import { computed } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'

const props = defineProps({
  options: {
    type: Array,
    default: () => [],
  },
  modelValue: {
    type: Object,
    default: () => ({}),
  },
  otherValue: {
    type: String,
    default: '',
  },
  otherLabel: {
    type: String,
    default: 'Other remarks',
  },
  showOtherField: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'update:otherValue'])

const isOtherChecked = computed(() => {
  const otherOption = props.options.find(opt => opt.value === 'other' || opt.value === 'Others')
  if (!otherOption) return false
  return props.modelValue[otherOption.value] === true
})

const handleCheckboxChange = (value, checked) => {
  const newValue = { ...props.modelValue }
  newValue[value] = checked
  emit('update:modelValue', newValue)
}

const handleOtherInput = event => {
  emit('update:otherValue', event.target.value)
}
</script>

<template>
  <div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <div v-for="option in options" :key="option.value" class="flex items-center space-x-2">
        <Checkbox
          :id="option.value"
          :checked="modelValue[option.value]"
          @update:checked="handleCheckboxChange(option.value, $event)"
        />
        <Label :for="option.value" class="cursor-pointer">
          {{ option.label }}
        </Label>
      </div>
    </div>

    <div v-if="showOtherField && isOtherChecked" class="space-y-2">
      <Label>{{ otherLabel }}</Label>
      <Input :model-value="otherValue" :placeholder="otherLabel" @input="handleOtherInput" />
    </div>
  </div>
</template>
