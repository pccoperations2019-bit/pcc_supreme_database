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
  rainyValue: {
    type: [Boolean, String],
    default: false,
  },
  dryValue: {
    type: [Boolean, String],
    default: false,
  },
  specificationValue: {
    type: String,
    default: '',
  },
  specificationOptions: {
    type: Object,
    default: () => ({}),
  },
  showSpecification: {
    type: Boolean,
    default: false,
  },
  specificationLabel: {
    type: String,
    default: 'Specification',
  },
})

const emit = defineEmits(['update:rainyValue', 'update:dryValue', 'update:specificationValue'])
</script>

<template>
  <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-3">
    <div class="space-y-2">
      <Label>{{ label }} (Rainy)</Label>
      <Select :model-value="rainyValue" @update:model-value="emit('update:rainyValue', $event)">
        <SelectTrigger><SelectValue placeholder="Select" /></SelectTrigger>
        <SelectContent>
          <SelectItem :value="true">Yes</SelectItem>
          <SelectItem :value="false">No</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div class="space-y-2">
      <Label>{{ label }} (Dry)</Label>
      <Select :model-value="dryValue" @update:model-value="emit('update:dryValue', $event)">
        <SelectTrigger><SelectValue placeholder="Select" /></SelectTrigger>
        <SelectContent>
          <SelectItem :value="true">Yes</SelectItem>
          <SelectItem :value="false">No</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="showSpecification && (rainyValue || dryValue)" class="space-y-2">
      <Label>{{ specificationLabel }}</Label>
      <Select
        :model-value="specificationValue"
        @update:model-value="emit('update:specificationValue', $event)"
      >
        <SelectTrigger>
          <SelectValue :placeholder="`Select ${specificationLabel}`" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="(specLabel, key) in specificationOptions" :key="key" :value="key">
            {{ specLabel }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
  </div>
</template>
