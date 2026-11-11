<script setup>
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

defineProps({
  type: {
    type: String,
    default: 'button',
  },
  variant: {
    type: String,
    default: 'default',
  },
  size: {
    type: String,
    default: 'default',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  className: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <Button
    :type="type"
    :variant="variant"
    :size="size"
    :disabled="disabled || loading"
    :class="cn('relative', className)"
  >
    <span :class="cn(loading && 'opacity-0')">
      <slot />
    </span>

    <span v-if="loading" class="absolute inset-0 flex items-center justify-center">
      <svg
        class="h-5 w-5 animate-spin"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path
          class="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        />
      </svg>
    </span>
  </Button>
</template>
