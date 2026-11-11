<script setup>
import { Icon } from '@iconify/vue'

defineProps({
  warnings: { type: Array, default: () => [] },
})
</script>

<template>
  <div
    v-if="warnings.length"
    role="status"
    class="rounded-lg border border-slate-300 bg-slate-50 px-4 py-3"
  >
    <p class="flex items-center gap-2 text-sm font-semibold text-slate-900">
      <Icon icon="lucide:triangle-alert" class="h-4 w-4 shrink-0" aria-hidden="true" />
      {{ warnings.length }} value{{ warnings.length === 1 ? '' : 's' }} need a look — figures are
      shown as calculated, nothing was adjusted.
    </p>
    <ul class="mt-2 list-disc space-y-1 pl-6 text-sm text-slate-700">
      <li
        v-for="(warning, index) in warnings"
        :key="`${warning.code}-${warning.row_key}-${warning.column}-${index}`"
      >
        {{ warning.message }}
      </li>
    </ul>
  </div>
</template>
