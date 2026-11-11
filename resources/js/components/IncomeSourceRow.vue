<script setup>
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  sourceValue: {
    type: String,
    default: '',
  },
  incomeValue: {
    type: [Number, String],
    default: '',
  },
  sourceOptions: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:sourceValue', 'update:incomeValue'])

const handleIncomeInput = event => {
  const value = event.target.value
  emit('update:incomeValue', value === '' ? '' : parseFloat(value))
}
</script>

<template>
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="space-y-2">
      <Label>{{ label }} Source of Income</Label>
      <Select :model-value="sourceValue" @update:model-value="emit('update:sourceValue', $event)">
        <SelectTrigger>
          <SelectValue placeholder="Select source" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="(label, key) in sourceOptions" :key="key" :value="key">
            {{ label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
    <div class="space-y-2">
      <Label>{{ label }} Annual Net Income</Label>
      <Input
        :model-value="incomeValue"
        type="number"
        step="0.01"
        placeholder="0.00"
        @input="handleIncomeInput"
      />
    </div>
  </div>
</template>
