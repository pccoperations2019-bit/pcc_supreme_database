<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { DOCUMENT_STATUS } from '@/lib/contants'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

/**
 * Confirmation dialog for either approval stage. Rejection requires a reason,
 * which the backend also enforces.
 */
const props = defineProps({
  open: { type: Boolean, default: false },
  mode: { type: String, required: true }, // 'approve' | 'reject'
  documentId: { type: Number, required: true },
  documentTitle: { type: String, default: '' },
  reviewStage: { type: String, default: 'center' },
})

const emit = defineEmits(['update:open'])

const reason = ref('')
const submitting = ref(false)
const error = ref('')

watch(
  () => props.open,
  isOpen => {
    if (isOpen) {
      reason.value = ''
      error.value = ''
    }
  }
)

const isApprove = () => props.mode === 'approve'

const close = () => emit('update:open', false)

const confirm = () => {
  error.value = ''

  if (!isApprove() && !reason.value.trim()) {
    error.value = 'Please give a reason so the center knows what to fix.'

    return
  }

  submitting.value = true

  router.post(
    route(isApprove() ? 'documents.approve' : 'documents.reject', props.documentId),
    isApprove() ? {} : { rejection_reason: reason.value.trim() },
    {
      preserveScroll: true,
      onSuccess: () => {
        // The final approval starts processing, so surface it as a live import
        // card; without this the card the upload created was already dismissed
        // when the document entered review.
        if (isApprove() && props.reviewStage === 'agency') {
          window.dispatchEvent(
            new CustomEvent('import-queued', {
              detail: {
                documentId: props.documentId,
                status: DOCUMENT_STATUS.PROCESSING,
                progress: 0,
              },
            })
          )
        }

        close()
      },
      onError: errors => {
        error.value = errors?.rejection_reason || 'Something went wrong. Please try again.'
      },
      onFinish: () => {
        submitting.value = false
      },
    }
  )
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>
          {{ isApprove() ? 'Approve Document?' : 'Reject Document' }}
        </DialogTitle>
        <DialogDescription>
          <template v-if="isApprove()">
            <template v-if="props.reviewStage === 'center'">
              This document will move to agency review. Processing will not start yet.
            </template>
            <template v-else>This document will be approved and processing will begin.</template>
          </template>
          <template v-else> Please provide a reason for rejecting this document. </template>
        </DialogDescription>
      </DialogHeader>

      <div v-if="!isApprove()" class="space-y-2">
        <label for="rejection_reason" class="text-sm font-medium text-gray-700">
          Rejection Reason <span class="text-red-500">*</span>
        </label>
        <Textarea
          id="rejection_reason"
          v-model="reason"
          rows="4"
          placeholder="e.g. The reporting period is wrong - please use Q1 2026."
        />
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <DialogFooter>
        <Button variant="outline" :disabled="submitting" @click="close">Cancel</Button>
        <Button
          :variant="isApprove() ? 'default' : 'destructive'"
          :disabled="submitting"
          @click="confirm"
        >
          <span v-if="submitting" class="mr-2">Please wait…</span>
          {{ isApprove() ? `Approve for ${props.reviewStage} review` : 'Reject Document' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
