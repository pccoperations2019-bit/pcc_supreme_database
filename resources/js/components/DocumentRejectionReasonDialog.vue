<script setup>
import { Button } from '@/components/ui/button'
import { formatDateTime } from '@/lib/utils'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Icon } from '@iconify/vue'

/**
 * Read-only view of why a document was rejected, so a reviewer can inspect the
 * decision without leaving the table. Mirrors what the show page displays.
 */
defineProps({
  open: { type: Boolean, default: false },
  documentTitle: { type: String, default: '' },
  reason: { type: String, default: '' },
  reviewer: { type: String, default: '' },
  reviewedAt: { type: String, default: '' },
})

const emit = defineEmits(['update:open'])
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Rejection Reason</DialogTitle>
        <DialogDescription>
          <strong>{{ documentTitle }}</strong> was rejected by
          <strong>{{ reviewer || 'a PCC Admin' }}</strong>
          <template v-if="reviewedAt"> on {{ formatDateTime(reviewedAt) }}</template
          >.
        </DialogDescription>
      </DialogHeader>

      <div
        v-if="reason"
        class="rounded-md border border-red-200 bg-red-50 p-3 text-sm whitespace-pre-line text-red-900"
      >
        {{ reason }}
      </div>
      <div v-else class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-500">
        No reason was recorded for this rejection.
      </div>

      <DialogFooter>
        <Button variant="outline" @click="emit('update:open', false)">
          <Icon icon="lucide:x" class="mr-2 h-4 w-4" />
          Close
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
