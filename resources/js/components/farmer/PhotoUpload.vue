<script setup>
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
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
      <Label for="photo-upload" class="sr-only">Upload Photo</Label>
      <div class="rounded-md bg-gray-100 p-2 text-gray-700">
        <Input
          id="photo-upload"
          type="file"
          accept="image/*"
          class="w-full cursor-pointer bg-transparent text-gray-700 file:mr-2 file:cursor-pointer file:rounded file:border-0 file:bg-gray-300 file:px-3 file:py-1 file:text-gray-700"
          @change="$emit('change', $event.target.files[0])"
        />
      </div>
    </div>
  </div>
</template>
