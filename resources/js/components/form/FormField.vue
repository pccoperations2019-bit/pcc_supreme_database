<script setup>
import { computed } from 'vue'
import { Label } from '@/components/ui/label'

const props = defineProps({
  id: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  error: {
    type: [String, Array],
    default: '',
  },
  required: {
    type: Boolean,
    default: false,
  },
  wrapperClass: {
    type: String,
    default: 'space-y-2',
  },
  labelClass: {
    type: String,
    default: 'text-muted-foreground font-medium',
  },
})

const errorMessage = computed(() => {
  if (Array.isArray(props.error)) return props.error[0] || ''
  return props.error || ''
})
</script>

<template>
  <div :class="wrapperClass">
    <Label v-if="label || $slots.label" :for="id" :class="labelClass">
      <slot name="label">
        {{ label }}
        <span v-if="required" class="text-red-500">*</span>
      </slot>
    </Label>

    <slot />

    <p v-if="errorMessage" class="mt-1 text-sm text-red-500">
      {{ errorMessage }}
    </p>
  </div>
</template>
