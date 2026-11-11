<script setup>
import { Icon } from '@iconify/vue'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

defineProps({
  reason: { type: String, required: true },
})
</script>

<template>
  <Popover>
    <PopoverTrigger as-child>
      <Button
        variant="outline"
        size="sm"
        class="h-7 w-fit border-rose-200 text-xs text-rose-700 hover:bg-rose-50 hover:text-rose-800"
        @click.stop
      >
        <Icon icon="lucide:undo-2" class="mr-1 h-3.5 w-3.5" />
        View reason
      </Button>
    </PopoverTrigger>
    <PopoverContent align="start" class="w-72 space-y-1">
      <p class="text-xs font-medium text-rose-700">Returned for correction</p>
      <p class="text-sm whitespace-pre-wrap text-slate-700">{{ reason }}</p>
    </PopoverContent>
  </Popover>
</template>
