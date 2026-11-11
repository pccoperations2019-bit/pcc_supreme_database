<script setup>
import { Icon } from '@iconify/vue'
import { Button } from '@/components/ui/button'

defineProps({
  variant: { type: String, default: 'empty' },
  heading: { type: String, default: '' },
  message: { type: String, default: '' },
  retryable: { type: Boolean, default: false },
})

const emit = defineEmits(['retry'])
</script>

<template>
  <div class="rounded-lg border border-slate-200 px-4 py-12 text-center">
    <div class="flex flex-col items-center justify-center gap-2">
      <Icon
        :icon="variant === 'error' ? 'lucide:triangle-alert' : 'lucide:chart-area'"
        :class="variant === 'error' ? 'h-10 w-10 text-red-500' : 'h-10 w-10 text-slate-300'"
      />
      <h3 class="text-lg font-medium text-slate-900">
        {{
          heading ||
          (variant === 'error'
            ? 'Could not load milk utilization'
            : 'Nothing entered for this period')
        }}
      </h3>
      <p class="text-slate-500">{{ message }}</p>
      <Button
        v-if="variant === 'error' && retryable"
        variant="outline"
        class="mt-2 cursor-pointer"
        @click="emit('retry')"
      >
        <Icon icon="lucide:refresh-cw" class="mr-2 h-4 w-4" />
        Retry
      </Button>
    </div>
  </div>
</template>
