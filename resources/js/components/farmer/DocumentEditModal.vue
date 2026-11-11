<script setup>
import { ref, watch } from 'vue'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import DocumentFormFields from './DocumentFormFields.vue'

const props = defineProps({
  open: { type: Boolean, required: true },
  document: { type: Object, default: null },
  documentTypes: { type: Object, default: () => ({}) },
  complianceStatuses: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:open', 'save'])

const form = ref({
  document_type: '',
  registration_number: '',
  validity: '',
  description: '',
  compliance_status: '',
})
const file = ref(null)

watch(
  () => props.document,
  doc => {
    if (!doc) return

    form.value = {
      document_type: doc.tags?.find(t => t.key === 'document')?.value || '',
      registration_number: doc.registration_number || '',
      validity: doc.validity || '',
      description: doc.description || '',
      compliance_status: doc.compliance_status || '',
    }
    file.value = null
  },
  { immediate: true }
)

function submit() {
  if (!props.document) return

  emit('save', {
    id: props.document.id,
    title: props.document.title,
    ...form.value,
    file: file.value,
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="nextOpen => $emit('update:open', nextOpen)">
    <DialogContent class="max-h-[95dvh] w-full overflow-y-auto sm:max-h-[90vh] sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>Edit Document</DialogTitle>
        <DialogDescription>Update document information</DialogDescription>
      </DialogHeader>

      <template v-if="document">
        <DocumentFormFields
          v-model="form"
          :document-types="documentTypes"
          :compliance-statuses="complianceStatuses"
          file-label="Replace File (Optional)"
          :file-required="false"
          @file-change="nextFile => (file = nextFile)"
        />

        <p v-if="file" class="mt-1 text-sm text-gray-600">New file: {{ file.name }}</p>
      </template>

      <DialogFooter class="flex-col gap-2 sm:flex-row sm:gap-0">
        <Button class="w-full bg-blue-600 hover:bg-blue-700 sm:w-auto" @click="submit"
          >Update Document</Button
        >
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
