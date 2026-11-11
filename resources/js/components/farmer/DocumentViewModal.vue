<script setup>
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'

defineProps({
  open: { type: Boolean, required: true },
  document: { type: Object, default: null },
  documentTypes: { type: Object, default: () => ({}) },
})

defineEmits(['update:open', 'download'])
</script>

<template>
  <Dialog :open="open" @update:open="nextOpen => $emit('update:open', nextOpen)">
    <DialogContent
      class="flex max-h-[95dvh] w-full flex-col overflow-hidden rounded-t-2xl sm:max-h-[90vh] sm:max-w-4xl sm:rounded-lg"
    >
      <DialogHeader class="shrink-0">
        <DialogTitle>{{ document?.title }}</DialogTitle>
        <DialogDescription>File preview and download options</DialogDescription>
      </DialogHeader>

      <div v-if="document" class="flex min-h-0 flex-1 flex-col space-y-4 overflow-y-auto">
        <div class="overflow-hidden rounded-lg border" style="height: 50dvh; min-height: 200px">
          <embed
            v-if="document.file?.type?.includes('pdf')"
            :src="`/${document.file.path}#toolbar=0&navpanes=0&scrollbar=0`"
            type="application/pdf"
            class="h-full w-full"
          />

          <div
            v-else-if="document.file?.type?.includes('image')"
            class="flex h-full w-full items-center justify-center bg-gray-50"
          >
            <img
              :src="`/${document.file.path}`"
              :alt="document.title"
              class="max-h-full max-w-full object-contain"
            />
          </div>

          <div v-else class="flex h-full w-full items-center justify-center bg-gray-50">
            <div class="text-center">
              <p class="mb-2 text-gray-500">Preview not available for this file type</p>
              <p class="text-sm text-gray-400">{{ document.file?.type }}</p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 sm:gap-4">
          <div>
            <span class="font-semibold">File Type:</span>
            <span class="ml-2">{{ document.file?.type }}</span>
          </div>
          <div>
            <span class="font-semibold">File Size:</span>
            <span class="ml-2">
              {{
                document.file?.size
                  ? (document.file.size / 1024 / 1024).toFixed(2) + ' MB'
                  : 'Unknown'
              }}
            </span>
          </div>
          <div>
            <span class="font-semibold">Document Type:</span>
            <span class="ml-2">
              {{
                documentTypes?.[document.tags?.find(t => t.key === 'document')?.value] ||
                document.tags?.find(t => t.key === 'document')?.value ||
                '-'
              }}
            </span>
          </div>
          <div>
            <span class="font-semibold">Uploaded By:</span>
            <span class="ml-2">
              {{ document.file?.uploaded_by?.name || document.file?.uploadedBy?.name }}
            </span>
          </div>
        </div>
      </div>

      <DialogFooter class="shrink-0 flex-col gap-2 sm:flex-row sm:gap-0">
        <Button
          v-if="document"
          class="w-full bg-blue-600 hover:bg-blue-700 sm:w-auto"
          @click="$emit('download', document)"
          >Download Document</Button
        >
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
