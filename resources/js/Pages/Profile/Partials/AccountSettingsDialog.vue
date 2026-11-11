<script setup>
import {
  Dialog,
  DialogDescription,
  DialogHeader,
  DialogScrollContent,
  DialogTitle,
} from '@/components/ui/dialog'
import UpdatePasswordForm from './UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './UpdateProfileInformationForm.vue'

defineProps({
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['update:open'])

const close = () => emit('update:open', false)
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogScrollContent
      class="my-4 flex max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-3xl flex-col gap-0 overflow-hidden rounded-lg p-0 md:w-[calc(100%-2rem)]"
    >
      <DialogHeader class="shrink-0 space-y-1 border-b px-4 py-4 pr-12 sm:px-6 sm:pr-14">
        <DialogTitle class="text-xl font-bold">Account Settings</DialogTitle>
        <DialogDescription>Manage your profile and login credentials.</DialogDescription>
      </DialogHeader>

      <!-- Keyed so both forms remount with fresh values each time the modal opens. -->
      <div
        :key="open ? 'open' : 'closed'"
        class="min-h-0 space-y-4 overflow-y-auto overscroll-contain px-4 py-4 sm:px-6 sm:py-6"
      >
        <UpdateProfileInformationForm @saved="close" />
        <UpdatePasswordForm @saved="close" />
      </div>
    </DialogScrollContent>
  </Dialog>
</template>
