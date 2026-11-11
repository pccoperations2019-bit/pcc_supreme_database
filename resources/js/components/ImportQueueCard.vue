<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import { useAuth } from '@/composable/useAuth'
import { Button } from '@/components/ui/button'
import {
  Item,
  ItemActions,
  ItemContent,
  ItemDescription,
  ItemMedia,
  ItemTitle,
} from '@/components/ui/item'
import { Spinner } from '@/components/ui/spinner'
import { Progress } from '@/components/ui/progress'
import { Upload as UploadIcon, CheckCircle2, XCircle, X } from 'lucide-vue-next'
import { DOCUMENT_STATUS } from '@/lib/contants'

const { isAgencyAdmin } = useAuth()

const STORAGE_KEY = 'import-queue-cards'
const importCards = ref([])
let importStatusPollers = {}
let durationUpdater = null

const formatBytes = bytes => {
  if (!bytes || bytes <= 0) return 'File uploaded'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const formatDuration = startTime => {
  if (!startTime) return ''

  const now = new Date()
  const start = new Date(startTime)
  const diff = Math.floor((now - start) / 1000) // seconds

  if (diff < 60) return `${diff}s`
  if (diff < 3600) return `${Math.floor(diff / 60)}m`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h`
  return `${Math.floor(diff / 86400)}d`
}

const isProcessing = documentId =>
  importCards.value.find(c => c.documentId === documentId)?.status === DOCUMENT_STATUS.PROCESSING

const getImportCard = documentId => importCards.value.find(c => c.documentId === documentId)

const importProgressLabel = documentId => {
  const card = getImportCard(documentId)
  if (!card) return ''
  if (card.status === DOCUMENT_STATUS.PROCESSED) {
    return `${formatBytes(card.fileSize)} imported successfully`
  }
  if (card.status === DOCUMENT_STATUS.FAILED) {
    return 'Import failed. Please try again.'
  }
  if (card.status === DOCUMENT_STATUS.DRAFT) {
    return 'Draft saved. Submit it for review to start the import.'
  }
  if (card.status === DOCUMENT_STATUS.PENDING_REVIEW) {
    return 'Submitted for review.'
  }
  if (card.status === DOCUMENT_STATUS.PENDING_CENTER_REVIEW) {
    return 'Submitted for center review.'
  }
  if (card.status === DOCUMENT_STATUS.PENDING_AGENCY_REVIEW) {
    return 'Submitted for agency review.'
  }
  if (card.status === DOCUMENT_STATUS.REJECTED) {
    return 'Changes requested. Open the document to correct and resubmit it.'
  }
  if (
    [DOCUMENT_STATUS.REJECTED_BY_CENTER, DOCUMENT_STATUS.REJECTED_BY_AGENCY].includes(card.status)
  ) {
    return 'Changes requested. Open the document to correct and resubmit it.'
  }
  if (card.status === DOCUMENT_STATUS.APPROVED) {
    return 'Approved. Waiting for processing to start.'
  }
  return 'Import is being processed'
}

/** Only these states will still change on their own, so only poll them. */
const isLiveStatus = status =>
  status === DOCUMENT_STATUS.PROCESSING || status === DOCUMENT_STATUS.APPROVED

/** States where the document is sitting with a reviewer, not being imported. */
const isAwaitingReview = status =>
  [
    DOCUMENT_STATUS.DRAFT,
    DOCUMENT_STATUS.PENDING_REVIEW,
    DOCUMENT_STATUS.PENDING_CENTER_REVIEW,
    DOCUMENT_STATUS.PENDING_AGENCY_REVIEW,
    DOCUMENT_STATUS.REJECTED,
    DOCUMENT_STATUS.REJECTED_BY_CENTER,
    DOCUMENT_STATUS.REJECTED_BY_AGENCY,
  ].includes(status)

const isUnderReview = status =>
  [
    DOCUMENT_STATUS.PENDING_REVIEW,
    DOCUMENT_STATUS.PENDING_CENTER_REVIEW,
    DOCUMENT_STATUS.PENDING_AGENCY_REVIEW,
  ].includes(status)

/** For PCC admins, also poll review states since they can approve documents themselves */
const shouldPoll = (status, userIsAgencyAdmin) =>
  isLiveStatus(status) || (userIsAgencyAdmin && isAwaitingReview(status))

const isPolling = documentId => Boolean(importStatusPollers[documentId])

/** Hide review status cards; the Documents list is the review queue. */
const visibleCards = computed(() => {
  return importCards.value.filter(card => {
    // Keep processing/approved/processed/failed cards visible for everyone
    if (
      isLiveStatus(card.status) ||
      card.status === DOCUMENT_STATUS.PROCESSED ||
      card.status === DOCUMENT_STATUS.FAILED
    ) {
      return true
    }
    return !isUnderReview(card.status)
  })
})

/**
 * Insert or update a card in place and persist it. Card status is
 * localStorage-backed, so anything that changes it must go through here or the
 * card renders stale until it is dismissed.
 */
const patchCard = (documentId, patch) => {
  const cardIndex = importCards.value.findIndex(c => c.documentId === documentId)
  const updated = { ...(cardIndex === -1 ? {} : importCards.value[cardIndex]), ...patch }

  if (cardIndex === -1) {
    importCards.value.push(updated)
  } else {
    importCards.value.splice(cardIndex, 1, updated)
  }

  persistImportCards()

  // Review states belong in the Documents list, not the import progress card.
  if (isUnderReview(updated.status)) {
    dismissImportCard(documentId)

    return null
  }

  return updated
}

const progressValue = documentId => {
  const card = getImportCard(documentId)
  if (!card) return 0
  if (card.status === DOCUMENT_STATUS.PROCESSED) return 100
  if (!isLiveStatus(card.status)) return 0
  return card?.progress ?? 0
}

const progressText = documentId => {
  const card = getImportCard(documentId)
  if (!card) return ''
  if (card.status === DOCUMENT_STATUS.FAILED) return 'FAILED'
  if (card.status === DOCUMENT_STATUS.PROCESSED) return '100%'
  if (card.status === DOCUMENT_STATUS.FAILED || card.status === DOCUMENT_STATUS.DRAFT) return ''

  const progress = card?.progress ?? 0
  const duration = formatDuration(card.metadata?.queue_import?.queued_at || card.createdAt)
  return duration ? `${progress}% • ${duration}` : `${progress}%`
}

const persistImportCards = () => {
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(importCards.value))
}

const stopImportPolling = documentId => {
  if (documentId && importStatusPollers[documentId]) {
    window.clearInterval(importStatusPollers[documentId])
    delete importStatusPollers[documentId]
  } else if (!documentId) {
    Object.values(importStatusPollers).forEach(id => window.clearInterval(id))
    importStatusPollers = {}
  }
}

const dismissImportCard = documentId => {
  stopImportPolling(documentId)
  importCards.value = importCards.value.filter(c => c.documentId !== documentId)
  persistImportCards()
}

const pollQueuedImport = async documentId => {
  // The card may have been dismissed while the previous poll was in flight;
  // upserting here would resurrect it.
  if (!getImportCard(documentId)) return

  try {
    const response = await axios.get(`/xhr/documents/${documentId}/status`)
    const payload = response.data

    if (payload.status === undefined) return

    patchCard(documentId, {
      status: payload.status,
      progress: payload.progress ?? 0,
      // Only overwrite file details when the endpoint actually returns them, so
      // a card never loses its name/size to an absent payload field.
      ...(payload.file?.name ? { fileName: payload.file.name } : {}),
      ...(payload.file?.size ? { fileSize: payload.file.size } : {}),
    })

    // patchCard drops the card outright when there is no import to report.
    if (!getImportCard(documentId)) return

    // The server can move a document into (or out of) a live state on its own -
    // e.g. a PCC admin approves one while this tab sits idle - so pick polling
    // up or drop it from whatever the server just reported.
    if (shouldPoll(payload.status, isAgencyAdmin.value)) {
      if (!isPolling(documentId)) startImportPolling(documentId)
    } else {
      stopImportPolling(documentId)
      if (payload.status === DOCUMENT_STATUS.PROCESSED) {
        setTimeout(() => dismissImportCard(documentId), 3000)
      }
    }
  } catch (error) {
    // A card whose document is gone (deleted, or no longer visible to this
    // user) can never resolve, so drop it instead of polling a 404 forever.
    if (error?.response?.status === 404) {
      dismissImportCard(documentId)
      return
    }

    console.error('Error checking import status:', error)
  }
}

const startImportPolling = documentId => {
  stopImportPolling(documentId)
  importStatusPollers[documentId] = window.setInterval(() => pollQueuedImport(documentId), 2000)
}

const handleImportQueueUpdate = event => {
  const newCard = event.detail

  if (!newCard?.documentId) return

  // Routed through patchCard so a fresh upload goes through the same
  // "no import to report yet" dismissal as a restored card.
  const card = patchCard(newCard.documentId, {
    ...newCard,
    createdAt:
      getImportCard(newCard.documentId)?.createdAt || newCard.createdAt || new Date().toISOString(),
  })

  if (card && shouldPoll(newCard.status, isAgencyAdmin.value)) {
    startImportPolling(newCard.documentId)
  }
}

onMounted(() => {
  const storedCards = window.localStorage.getItem(STORAGE_KEY)
  if (storedCards) {
    importCards.value = JSON.parse(storedCards).map(card => ({
      ...card,
      createdAt: card.createdAt || new Date().toISOString(),
    }))

    // localStorage is the source of the card's status, so every restored card is
    // stale by definition: it was written before the document was submitted,
    // approved or processed. Reconcile each one against the server, which also
    // starts polling for any that come back in a live state or review state (for PCC admins).
    importCards.value.forEach(card => {
      if (card?.documentId) {
        pollQueuedImport(card.documentId)
        // Start polling for PCC admin review cards
        if (isAgencyAdmin.value && isAwaitingReview(card.status)) {
          startImportPolling(card.documentId)
        }
      }
    })
  }

  window.addEventListener('import-queued', handleImportQueueUpdate)

  // Start duration updater
  durationUpdater = setInterval(() => {
    // Force reactivity update for duration display
    importCards.value = [...importCards.value]
  }, 1000)
})

onBeforeUnmount(() => {
  stopImportPolling()
  window.removeEventListener('import-queued', handleImportQueueUpdate)
  if (durationUpdater) {
    clearInterval(durationUpdater)
    durationUpdater = null
  }
})
</script>

<template>
  <div v-if="visibleCards.length" class="w-full">
    <Item
      v-for="card in visibleCards"
      :key="card.documentId"
      variant="outline"
      class="bg-white text-black shadow-lg"
    >
      <ItemMedia variant="icon">
        <Spinner v-if="card.status === DOCUMENT_STATUS.PROCESSING" class="text-black" />
        <CheckCircle2
          v-else-if="card.status === DOCUMENT_STATUS.PROCESSED"
          class="size-4 text-slate-600"
        />
        <XCircle
          v-else-if="
            card.status === DOCUMENT_STATUS.FAILED || card.status === DOCUMENT_STATUS.REJECTED
          "
          class="size-4 text-red-600"
        />
        <UploadIcon v-else class="size-4" />
      </ItemMedia>
      <ItemContent class="w-full">
        <ItemTitle class="flex items-center justify-between text-black">
          <span>{{ card.fileName || 'Import' }}</span>
          <span
            v-if="card.status === DOCUMENT_STATUS.PROCESSING"
            class="text-xs font-normal text-gray-500"
          >
            {{ formatDuration(card.metadata?.queue_import?.queued_at || card.createdAt) }}
          </span>
        </ItemTitle>
        <ItemDescription class="text-black">
          <div class="flex w-full justify-between text-xs">
            <span>{{ importProgressLabel(card.documentId) }}</span>
            <span>{{
              card.status === DOCUMENT_STATUS.PROCESSING
                ? `${card?.progress ?? 0}%`
                : progressText(card.documentId)
            }}</span>
          </div>
        </ItemDescription>
        <div v-if="isLiveStatus(card.status)" class="mt-2 w-full">
          <Progress
            :model-value="progressValue(card.documentId)"
            :class="['h-1.5', card.status === DOCUMENT_STATUS.PROCESSING ? 'animate-pulse' : '']"
          />
        </div>
      </ItemContent>
      <ItemActions>
        <Button
          variant="ghost"
          size="icon"
          class="h-8 w-8"
          @click="dismissImportCard(card.documentId)"
        >
          <X class="h-4 w-4" />
        </Button>
      </ItemActions>
    </Item>
  </div>
</template>
