<script setup>
import { ref, computed } from 'vue'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { XCircle, Upload, Check } from 'lucide-vue-next'
import axios from 'axios'

const isDragging = ref(false)
const files = ref([])
const uploading = ref(false)
const progress = ref(0)

const fileList = computed(() => {
  return files.value.map(file => ({
    name: file.name,
    size: formatFileSize(file.size),
    status: 'pending',
  }))
})

function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

function onDragEnter(e) {
  e.preventDefault()
  isDragging.value = true
}

function onDragLeave(e) {
  e.preventDefault()
  isDragging.value = false
}

function onDrop(e) {
  e.preventDefault()
  isDragging.value = false
  const droppedFiles = Array.from(e.dataTransfer.files)
  files.value = [...files.value, ...droppedFiles]
}

function removeFile(index) {
  files.value.splice(index, 1)
}

const recordFile = async key => {
  console.log('File recorded with key:', key)
  try {
    await axios.post(route('files.store'), {
      key: key,
    })
  } catch (error) {
    console.error('Error recording file:', error)
  }
}

const uploadFiles = async (url, files) => {
  try {
    const uploadPromises = Array.from(files).map(async file => {
      return axios
        .put(url, file, {
          headers: {
            'Content-Type': file.type || 'application/octet-stream',
          },
          onUploadProgress: progressEvent => {
            const fileProgress = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            progress.value = Math.round(
              files.reduce((acc, _, index) => acc + fileProgress, 0) / files.length
            )
          },
        })
        .then(() => {
          console.log('File uploaded successfully', url)
          console.log(`Uploaded file: ${file.name}`)
          console.log('Presigned URL used:', url)
          console.log('File key:', file.key)
          recordFile(file.key)
        })
        .finally(() => {
          progress.value = 100
          setTimeout(() => {
            window.location = route('files.index')
          }, 500)
        })
    })

    await Promise.all(uploadPromises)
    return true
  } catch (error) {
    console.error('Upload failed:', error)
    throw error
  }
}

async function handleSubmit() {
  if (!files.value.length) return

  uploading.value = true
  progress.value = 0

  try {
    // Get presigned URLs for each file
    const uploads = await Promise.all(
      files.value.map(async file => {
        // Assuming you have an endpoint to get presigned URL for each file
        const { data } = await axios.post('/xhr/files-presigned-url', {
          filename: file.name,
          contentType: file.type,
        })
        file['key'] = data.key // Store the key for later use
        return { file, presignedUrl: data.url }
      })
    )

    // Upload all files concurrently
    await Promise.all(uploads.map(({ file, presignedUrl }) => uploadFiles(presignedUrl, [file])))

    // Notify success
    console.log('All files uploaded successfully')
  } catch (error) {
    console.error('Upload failed:', error)
  } finally {
    uploading.value = false
  }
}

const overallProgress = computed(() => {
  if (!files.value.length) return 0
  return progress.value
})
</script>

<template>
  <Card class="w-full">
    <CardHeader>
      <CardTitle>File Upload</CardTitle>
      <CardDescription> Drop files here or click to upload </CardDescription>
    </CardHeader>

    <CardContent>
      <div v-if="uploading" class="mt-4">
        <div class="mb-2 h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
          <div
            class="bg-primary h-2.5 rounded-full transition-all duration-300"
            :style="{ width: `${overallProgress}%` }"
          />
        </div>
        <p class="text-muted-foreground text-center text-sm">{{ overallProgress }}% Complete</p>
      </div>
      <div
        class="relative"
        @dragenter.prevent="onDragEnter"
        @dragleave.prevent="onDragLeave"
        @dragover.prevent
        @drop.prevent="onDrop"
      >
        <!-- Fishpond animation when dragging -->
        <div
          v-if="isDragging"
          class="bg-primary/10 border-primary absolute inset-0 z-10 animate-pulse rounded-lg border-2 border-dashed"
        />

        <!-- Upload zone -->
        <div
          class="min-h-[200px] rounded-lg border-2 border-dashed p-6 text-center transition-all duration-200"
          :class="{
            'border-muted-foreground/25 hover:border-muted-foreground/50': !isDragging,
            'border-primary': isDragging,
          }"
        >
          <input
            type="file"
            multiple
            class="hidden"
            @change="e => (files.value = [...files.value, ...Array.from(e.target.files)])"
          />

          <div class="flex flex-col items-center gap-4">
            <Upload class="text-muted-foreground/50 h-12 w-12" />
            <div class="space-y-1">
              <p class="text-muted-foreground text-sm">Drag and drop your files here</p>
              <p class="text-muted-foreground text-xs">or click to browse</p>
            </div>
          </div>
        </div>

        <!-- File list -->
        <div v-if="files.length" class="mt-4 space-y-2">
          <div
            v-for="(file, index) in fileList"
            :key="index"
            class="bg-muted/50 flex items-center justify-between rounded-md p-2"
          >
            <div class="flex items-center space-x-2">
              <span class="text-sm font-medium">{{ file.name }}</span>
              <span class="text-muted-foreground text-xs">{{ file.size }}</span>
            </div>
            <Button variant="ghost" size="icon" @click="removeFile(index)">
              <XCircle class="h-4 w-4" />
            </Button>
          </div>
        </div>

        <!-- Upload button -->
        <Button
          class="mt-4 w-full"
          :disabled="!files.length || uploading"
          @click="() => handleSubmit()"
        >
          <Upload v-if="!uploading" class="mr-2 h-4 w-4" />
          <Check v-else class="mr-2 h-4 w-4" />
          {{ uploading ? 'Uploading...' : 'Upload Files' }}
        </Button>
      </div>
    </CardContent>
  </Card>
</template>

<style scoped>
/* Optional: Add ripple animation for the fishpond effect */
@keyframes ripple {
  0% {
    transform: scale(1);
    opacity: 1;
  }
  100% {
    transform: scale(1.1);
    opacity: 0;
  }
}

.animate-ripple {
  animation: ripple 1s ease-out infinite;
}
</style>
