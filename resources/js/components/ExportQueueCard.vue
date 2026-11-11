<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import { toast } from 'vue-sonner'
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
import { Download as DownloadIcon, X, XCircle } from 'lucide-vue-next'
import { DOCUMENT_STATUS } from '@/lib/contants'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'

const STORAGE_KEY = 'report-export-cards'
const exportCards = ref([])
let exportStatusPollers = {}
let durationUpdater = null

const formatBytes = bytes => {
  if (!bytes || bytes <= 0) return 'Preparing file'
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
  exportCards.value.find(c => c.documentId === documentId)?.status === DOCUMENT_STATUS.PROCESSING

const getExportCard = documentId => exportCards.value.find(c => c.documentId === documentId)

const exportProgressLabel = documentId => {
  const card = getExportCard(documentId)
  if (!card) return ''
  if (card.status === DOCUMENT_STATUS.PROCESSED) {
    return card.fileSize ? `${formatBytes(card.fileSize)} ready to download` : 'Ready to download'
  }
  if (card.status === DOCUMENT_STATUS.FAILED || card.status === DOCUMENT_STATUS.DRAFT) {
    return 'Export Failed. Please try again.'
  }
  return 'Export is being generated'
}

const progressValue = documentId => {
  const card = getExportCard(documentId)
  if (!card) return 0
  if (card.status === DOCUMENT_STATUS.PROCESSED) return 100
  if (card.status === DOCUMENT_STATUS.FAILED || card.status === DOCUMENT_STATUS.DRAFT) return 0
  return card?.progress ?? 0
}

const progressText = documentId => {
  const card = getExportCard(documentId)
  if (!card) return ''
  if (card.status === DOCUMENT_STATUS.FAILED) return 'FAILED'
  if (card.status === DOCUMENT_STATUS.PROCESSED) return '100%'
  if (card.status === DOCUMENT_STATUS.FAILED || card.status === DOCUMENT_STATUS.DRAFT) return ''

  const progress = card?.progress ?? 0
  const duration = formatDuration(card.metadata?.queued_export?.queued_at || card.createdAt)
  return duration ? `${progress}% • ${duration}` : `${progress}%`
}

const persistExportCards = () => {
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(exportCards.value))
}

const stopExportPolling = documentId => {
  if (documentId && exportStatusPollers[documentId]) {
    window.clearInterval(exportStatusPollers[documentId])
    delete exportStatusPollers[documentId]
  } else if (!documentId) {
    Object.values(exportStatusPollers).forEach(id => window.clearInterval(id))
    exportStatusPollers = {}
  }
}

const dismissExportCard = documentId => {
  stopExportPolling(documentId)
  exportCards.value = exportCards.value.filter(c => c.documentId !== documentId)
  persistExportCards()
}

const pollQueuedExport = async documentId => {
  try {
    const response = await axios.get(`/reports/exports/${documentId}`)
    const payload = response.data

    const cardIndex = exportCards.value.findIndex(c => c.documentId === documentId)
    if (cardIndex === -1) return

    exportCards.value[cardIndex] = {
      ...exportCards.value[cardIndex],
      status: payload.status,
      progress: payload.progress ?? 0,
      fileSize: payload.file_size,
      fileName: payload.file_name,
      downloadUrl: payload.download_url,
    }
    persistExportCards()

    if (
      payload.status === DOCUMENT_STATUS.PROCESSED ||
      payload.status === DOCUMENT_STATUS.FAILED ||
      payload.status === DOCUMENT_STATUS.DRAFT
    ) {
      stopExportPolling(documentId)
    }
  } catch (error) {
    console.error('Error checking export status:', error)
  }
}

const startExportPolling = documentId => {
  stopExportPolling(documentId)
  pollQueuedExport(documentId)
  exportStatusPollers[documentId] = window.setInterval(() => pollQueuedExport(documentId), 2000)
}

const downloadQueuedExport = async card => {
  if (!card?.downloadUrl) return

  try {
    const response = await axios.get(card.downloadUrl, { responseType: 'blob' })
    const blob = new window.Blob([response.data], { type: response.headers['content-type'] })
    const link = document.createElement('a')
    link.href = window.URL.createObjectURL(blob)
    link.download = card.fileName || `report.${card.format}`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(link.href)
    dismissExportCard(card.documentId)
  } catch (error) {
    console.error('Error downloading queued export:', error)
  }
}

const handleExportQueueUpdate = event => {
  const newCard = event.detail
  const existingIndex = exportCards.value.findIndex(c => c.documentId === newCard.documentId)

  if (existingIndex !== -1) {
    exportCards.value[existingIndex] = { ...exportCards.value[existingIndex], ...newCard }
  } else {
    const cardWithTimestamp = {
      ...newCard,
      createdAt: newCard.createdAt || new Date().toISOString(),
    }
    exportCards.value.push(cardWithTimestamp)
  }
  persistExportCards()

  // Cards flagged `local` were downloaded straight from the browser, so there is
  // no queued Document to poll for status.
  if (
    newCard?.documentId &&
    !newCard.local &&
    newCard.status !== DOCUMENT_STATUS.PROCESSED &&
    newCard.status !== DOCUMENT_STATUS.FAILED &&
    newCard.status !== DOCUMENT_STATUS.DRAFT
  ) {
    startExportPolling(newCard.documentId)
  }
}

onMounted(() => {
  const storedCards = window.localStorage.getItem(STORAGE_KEY)
  if (storedCards) {
    exportCards.value = JSON.parse(storedCards).map(card => ({
      ...card,
      createdAt: card.createdAt || new Date().toISOString(),
    }))

    exportCards.value.forEach(card => {
      if (
        card?.documentId &&
        !card.local &&
        card.status !== DOCUMENT_STATUS.PROCESSED &&
        card.status !== DOCUMENT_STATUS.FAILED &&
        card.status !== DOCUMENT_STATUS.DRAFT
      ) {
        startExportPolling(card.documentId)
      }
    })
  }

  window.addEventListener('report-export-queued', handleExportQueueUpdate)

  // Start duration updater
  durationUpdater = setInterval(() => {
    // Force reactivity update for duration display
    exportCards.value = [...exportCards.value]
  }, 1000)
})

onBeforeUnmount(() => {
  stopExportPolling()
  window.removeEventListener('report-export-queued', handleExportQueueUpdate)
  if (durationUpdater) {
    clearInterval(durationUpdater)
    durationUpdater = null
  }
})
</script>

<template>
  <div v-if="exportCards.length" class="w-full">
    <Item
      v-for="card in exportCards"
      :key="card.documentId"
      variant="outline"
      class="bg-white text-black shadow-lg"
    >
      <ItemMedia variant="icon">
        <Spinner v-if="card.status === DOCUMENT_STATUS.PROCESSING" class="text-black" />
        <XCircle v-else-if="card.status === DOCUMENT_STATUS.FAILED" class="size-4 text-red-600" />
        <DownloadIcon v-else class="size-4" />
      </ItemMedia>
      <ItemContent class="w-full">
        <ItemTitle class="flex items-center justify-between text-black">
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger as-child>
                <span class="max-w-[200px] truncate">{{ card.fileName || 'Export' }}</span>
              </TooltipTrigger>
              <TooltipContent>
                {{ card.fileName || 'Export' }}
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
          <span
            v-if="card.status === DOCUMENT_STATUS.PROCESSING"
            class="text-xs font-normal text-gray-500"
          >
            {{ formatDuration(card.metadata?.queued_export?.queued_at || card.createdAt) }}
          </span>
        </ItemTitle>
        <ItemDescription class="text-black">
          <div class="flex w-full justify-between text-xs">
            <span>{{ exportProgressLabel(card.documentId) }}</span>
            <span>{{
              card.status === DOCUMENT_STATUS.PROCESSING
                ? `${card?.progress ?? 0}%`
                : progressText(card.documentId)
            }}</span>
          </div>
        </ItemDescription>
        <div class="mt-2 w-full">
          <Progress
            :model-value="progressValue(card.documentId)"
            :class="['h-1.5', card.status === DOCUMENT_STATUS.PROCESSING ? 'animate-pulse' : '']"
          />
        </div>
      </ItemContent>
      <ItemActions class="hidden sm:flex">
        <Button
          v-if="card.status === DOCUMENT_STATUS.PROCESSED && card.downloadUrl"
          variant="outline"
          size="sm"
          @click="downloadQueuedExport(card)"
        >
          Download
        </Button>
        <Button
          variant="ghost"
          size="icon"
          class="h-8 w-8"
          @click="dismissExportCard(card.documentId)"
        >
          <X class="h-4 w-4" />
        </Button>
      </ItemActions>
    </Item>
  </div>
</template>
