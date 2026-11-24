<script setup>
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import ImageCropUpload from '@/components/ImageCropUpload.vue'
import { cn } from '@/lib/utils'

defineProps({
  preview: { type: String, default: null },
  alt: { type: String, default: '' },
  editable: { type: Boolean, default: false },
})

defineEmits(['change'])
</script>

<template>
  <div class="flex flex-col rounded-lg bg-gray-100 p-4 lg:col-span-1">
    <Avatar :class="cn('m-auto aspect-square h-auto w-full max-w-[280px] md:max-w-[18rem]')">
      <AvatarImage :src="preview" :alt="alt" class="object-cover" />
      <AvatarFallback>
        <img src="/images/farmer.png" alt="Placeholder Image" class="h-full w-full object-cover" />
      </AvatarFallback>
    </Avatar>

    <div v-if="editable" class="mt-4">
      <ImageCropUpload label="Farmer photo" @change="$emit('change', $event)" />
    </div>
  </div>
</template>
