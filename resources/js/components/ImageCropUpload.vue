<script setup>
import { computed, nextTick, onBeforeUnmount, ref, shallowRef, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

defineProps({
  label: {
    type: String,
    default: 'Profile image',
  },
})

const emit = defineEmits(['change'])

const cropFrame = ref(null)
const cropFile = shallowRef(null)
const sourceUrl = ref('')
const image = shallowRef(null)
const dialogOpen = ref(false)
const cropSize = ref(288)
const zoom = ref(1)
const offsetX = ref(0)
const offsetY = ref(0)
const isDragging = ref(false)
const isCropping = ref(false)
const errorMessage = ref('')
const dragStart = ref({ x: 0, y: 0 })

const imageScale = computed(() => {
  if (!image.value) return 1

  return (
    Math.min(
      cropSize.value / image.value.naturalWidth,
      cropSize.value / image.value.naturalHeight
    ) * zoom.value
  )
})

const scaledWidth = computed(() => (image.value?.naturalWidth || 0) * imageScale.value)
const scaledHeight = computed(() => (image.value?.naturalHeight || 0) * imageScale.value)

const imageStyle = computed(() => ({
  width: `${scaledWidth.value}px`,
  height: `${scaledHeight.value}px`,
  transform: `translate(-50%, -50%) translate(${offsetX.value}px, ${offsetY.value}px)`,
}))

const updateCropSize = () => {
  if (cropFrame.value) {
    cropSize.value = cropFrame.value.clientWidth
  }
}

const clampOffset = () => {
  const maxX = Math.max(0, (scaledWidth.value - cropSize.value) / 2)
  const maxY = Math.max(0, (scaledHeight.value - cropSize.value) / 2)

  offsetX.value = Math.max(-maxX, Math.min(maxX, offsetX.value))
  offsetY.value = Math.max(-maxY, Math.min(maxY, offsetY.value))
}

watch([zoom, cropSize], clampOffset)

watch(dialogOpen, async open => {
  if (!open) {
    cleanupSource()
    isDragging.value = false
    return
  }

  await nextTick()
  updateCropSize()
})

const cleanupSource = () => {
  if (sourceUrl.value) {
    window.URL.revokeObjectURL(sourceUrl.value)
  }

  sourceUrl.value = ''
  image.value = null
  cropFile.value = null
}

const closeCrop = () => {
  dialogOpen.value = false
  isDragging.value = false
  cleanupSource()
}

const handleFileChange = event => {
  const file = event.target.files?.[0]
  event.target.value = ''
  errorMessage.value = ''

  if (!file) return

  if (!file.type.startsWith('image/')) {
    errorMessage.value = 'Choose a valid image file.'
    return
  }

  cleanupSource()
  cropFile.value = file
  sourceUrl.value = window.URL.createObjectURL(file)

  const selectedImage = new window.Image()
  selectedImage.onload = async () => {
    image.value = selectedImage
    zoom.value = 1
    offsetX.value = 0
    offsetY.value = 0
    dialogOpen.value = true
    await nextTick()
    updateCropSize()
  }
  selectedImage.onerror = () => {
    cleanupSource()
    errorMessage.value = 'This image could not be opened. Please choose another image.'
  }
  selectedImage.src = sourceUrl.value
}

const startDragging = event => {
  if (!image.value) return

  event.preventDefault()
  isDragging.value = true
  dragStart.value = {
    x: event.clientX - offsetX.value,
    y: event.clientY - offsetY.value,
  }
  event.currentTarget.setPointerCapture(event.pointerId)
}

const dragImage = event => {
  if (!isDragging.value) return

  offsetX.value = event.clientX - dragStart.value.x
  offsetY.value = event.clientY - dragStart.value.y
  clampOffset()
}

const cropImage = async () => {
  if (!image.value || !cropFile.value) return

  isCropping.value = true
  errorMessage.value = ''

  try {
    const canvas = document.createElement('canvas')
    const outputSize = 512
    const context = canvas.getContext('2d')

    if (!context) {
      throw new Error('Image editing is unavailable in this browser.')
    }

    canvas.width = outputSize
    canvas.height = outputSize
    context.fillStyle = '#ffffff'
    context.fillRect(0, 0, outputSize, outputSize)
    const outputScale = outputSize / cropSize.value
    const imageLeft = (cropSize.value - scaledWidth.value) / 2 + offsetX.value
    const imageTop = (cropSize.value - scaledHeight.value) / 2 + offsetY.value
    context.drawImage(
      image.value,
      imageLeft * outputScale,
      imageTop * outputScale,
      scaledWidth.value * outputScale,
      scaledHeight.value * outputScale
    )

    const blob = await new Promise((resolve, reject) => {
      canvas.toBlob(
        result =>
          result ? resolve(result) : reject(new Error('The cropped image could not be created.')),
        'image/jpeg',
        0.92
      )
    })
    const baseName = cropFile.value.name.replace(/\.[^.]+$/, '') || 'profile-image'

    emit('change', new window.File([blob], `${baseName}-cropped.jpg`, { type: 'image/jpeg' }))
    closeCrop()
  } catch (error) {
    errorMessage.value = error.message || 'The image could not be cropped. Please try again.'
  } finally {
    isCropping.value = false
  }
}

onBeforeUnmount(cleanupSource)
</script>

<template>
  <div class="space-y-2">
    <Label for="profile-image-upload" class="sr-only">Upload {{ label }}</Label>
    <div class="rounded-md bg-gray-100 p-2 text-gray-700">
      <Input
        id="profile-image-upload"
        type="file"
        accept="image/*"
        class="w-full cursor-pointer bg-transparent text-gray-700 file:mr-2 file:cursor-pointer file:rounded file:border-0 file:bg-gray-300 file:px-3 file:py-1 file:text-gray-700"
        @change="handleFileChange"
      />
    </div>
    <p v-if="errorMessage && !dialogOpen" role="alert" class="text-destructive text-sm">
      {{ errorMessage }}
    </p>

    <Dialog v-model:open="dialogOpen">
      <DialogContent class="max-w-lg">
        <DialogHeader>
          <DialogTitle>Adjust {{ label.toLowerCase() }}</DialogTitle>
          <DialogDescription>
            The full image starts in view. Drag it to reposition, then zoom in to crop closer.
          </DialogDescription>
        </DialogHeader>

        <div
          ref="cropFrame"
          class="bg-muted relative mx-auto aspect-square w-full max-w-72 touch-none overflow-hidden rounded-full ring-4 ring-black/10"
          :class="isDragging ? 'cursor-grabbing' : 'cursor-grab'"
          @pointerdown="startDragging"
          @pointermove="dragImage"
          @pointerup="isDragging = false"
          @pointercancel="isDragging = false"
          @lostpointercapture="isDragging = false"
        >
          <img
            v-if="image"
            :src="sourceUrl"
            :alt="`${label} crop preview`"
            :style="imageStyle"
            class="pointer-events-none absolute top-1/2 left-1/2 max-w-none select-none"
            draggable="false"
          />
        </div>

        <div class="space-y-2">
          <label for="profile-image-zoom" class="text-sm font-medium">Zoom</label>
          <input
            id="profile-image-zoom"
            type="range"
            :min="1"
            :max="4"
            :step="0.01"
            :value="zoom"
            aria-label="Zoom image"
            class="accent-primary w-full cursor-pointer"
            @input="zoom = Number($event.target.value)"
          />
        </div>

        <p v-if="errorMessage" role="alert" class="text-destructive text-sm">
          {{ errorMessage }}
        </p>

        <DialogFooter>
          <Button type="button" variant="outline" :disabled="isCropping" @click="closeCrop">
            Cancel
          </Button>
          <Button type="button" :disabled="isCropping" @click="cropImage">
            {{ isCropping ? 'Cropping…' : ' Save image' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
