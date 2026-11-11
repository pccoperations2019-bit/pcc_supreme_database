<script setup>
import { ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import DocumentDetailFields from '@/components/DocumentDetailFields.vue'
import { documentStatusBadgeClass, documentStatusLabels } from '@/lib/document'
import { formatDateTime } from '@/lib/utils'

/**
 * What one uploaded document is, resolved on demand.
 *
 * Fetched per document rather than listed on every row: the facts behind an
 * upload - which kind of document, what it is attached to, how it was tagged -
 * live on the document and on records the list has not loaded, and a reviewer
 * only needs them for the row in front of them.
 */
const props = defineProps({
  open: { type: Boolean, default: false },
  documentId: { type: Number, required: false, default: null },
  documentTitle: { type: String, default: '' },
})

const emit = defineEmits(['update:open'])

const details = ref(null)
const loading = ref(false)
const error = ref('')

const load = async id => {
  loading.value = true
  error.value = ''

  try {
    const response = await fetch(`/xhr/documents/${id}/details`, {
      headers: { Accept: 'application/json' },
    })

    if (!response.ok) {
      throw new Error('Could not load the document details.')
    }

    details.value = await response.json()
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

watch(
  () => props.open,
  isOpen => {
    if (!isOpen) {
      return
    }

    details.value = null
    error.value = ''

    if (props.documentId) {
      load(props.documentId)
    }
  }
)

const getProcessingError = () => {
  if (!details.value?.document?.metadata) return null
  const metadata = details.value.document.metadata
  return metadata.queue_import?.error || metadata.queued_export?.error || null
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>{{ details?.document?.title || documentTitle }}</DialogTitle>
        <DialogDescription>
          What this file was uploaded as.
          <template v-if="details?.document?.status">
            &middot;
            <Badge
              variant="outline"
              class="ml-1 border"
              :class="documentStatusBadgeClass(details.document.status)"
            >
              {{ documentStatusLabels[details.document.status] || details.document.status }}
            </Badge>
          </template>
        </DialogDescription>
      </DialogHeader>

      <div v-if="loading" class="flex items-center gap-2 py-6 text-sm text-slate-500">
        <Icon icon="lucide:loader-circle" class="h-4 w-4 animate-spin" />
        Loading details&hellip;
      </div>

      <p v-else-if="error" class="py-6 text-sm text-red-600">{{ error }}</p>

      <div v-else-if="details">
        <!-- Processing Failed Error -->
        <div
          v-if="details.document?.status === 'failed' && getProcessingError()"
          class="mb-4 rounded-md border border-red-200 bg-red-50 p-4"
        >
          <div class="flex items-start gap-2">
            <Icon icon="lucide:circle-x" class="h-5 w-5 text-red-600 mt-0.5" />
            <div class="flex-1">
              <p class="text-sm font-medium text-red-900 mb-2">Processing Failed</p>
              <p class="text-sm whitespace-pre-line text-red-800 font-mono text-xs">
                {{ getProcessingError() }}
              </p>
              <p v-if="details.document.metadata?.queue_import?.failed_at" class="text-xs text-red-600 mt-2">
                Failed at: {{ formatDateTime(details.document.metadata.queue_import.failed_at) }}
              </p>
              <p v-if="details.document.metadata?.queued_export?.failed_at" class="text-xs text-red-600 mt-2">
                Failed at: {{ formatDateTime(details.document.metadata.queued_export.failed_at) }}
              </p>
            </div>
          </div>
        </div>

        <DocumentDetailFields :details="details" />
      </div>

      <template v-if="details?.document">
        <Separator />
        <dl class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
          <dt class="text-slate-500">Uploaded</dt>
          <dd class="col-span-2 text-gray-900">
            {{ formatDateTime(details.document.created_at) }}
          </dd>
        </dl>
      </template>
    </DialogContent>
  </Dialog>
</template>
