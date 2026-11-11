<script setup>
import { ref, computed } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { X } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: 'Image',
  },
  accept: {
    type: String,
    default: 'image/*',
  },
})

const emit = defineEmits(['update:modelValue'])

const fileInput = ref(null)
const previewUrl = computed(() => {
  if (props.modelValue && !props.modelValue.startsWith('data:')) {
    return props.modelValue
  }
  return null
})

const handleFileChange = event => {
  const file = event.target.files[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = e => {
    emit('update:modelValue', e.target.result)
  }
  reader.readAsDataURL(file)
}

const handleRemove = () => {
  emit('update:modelValue', '')
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const triggerFileInput = () => {
  fileInput.value?.click()
}
</script>

<template>
  <div class="space-y-2">
    <Label>{{ label }}</Label>

    <div v-if="previewUrl" class="relative inline-block">
      <img :src="previewUrl" :alt="label" class="h-32 w-32 rounded-md border object-cover" />
      <Button
        type="button"
        variant="destructive"
        size="icon"
        class="absolute -top-2 -right-2 h-6 w-6"
        @click="handleRemove"
      >
        <X class="h-4 w-4" />
      </Button>
    </div>

    <div v-else class="flex items-center gap-2">
      <input
        ref="fileInput"
        type="file"
        :accept="accept"
        class="hidden"
        @change="handleFileChange"
      />
      <Button type="button" variant="outline" @click="triggerFileInput"> Upload Image </Button>
      <span v-if="modelValue" class="text-muted-foreground text-sm"> File selected </span>
    </div>
  </div>
</template>
