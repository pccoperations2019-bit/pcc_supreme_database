<script setup>
import { ref } from 'vue'
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
  farmerName: { type: String, default: '' },
  documentTypes: { type: Object, default: () => ({}) },
  complianceStatuses: { type: Object, default: () => ({}) },
  uploading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:open', 'upload'])

const form = ref({
  document_type: '',
  registration_number: '',
  validity: '',
  description: '',
  compliance_status: '',
})
const file = ref(null)
const errors = ref({ file: '', document_type: '' })

function handleOpen(nextOpen) {
  emit('update:open', nextOpen)
  if (!nextOpen) {
    form.value = {
      document_type: '',
      registration_number: '',
      validity: '',
      description: '',
      compliance_status: '',
    }
    file.value = null
    errors.value = { file: '', document_type: '' }
  }
}

function submit() {
  errors.value = { file: '', document_type: '' }
  let hasError = false
  if (!file.value) {
    errors.value.file = 'File is required.'
    hasError = true
  }
  if (!form.value.document_type) {
    errors.value.document_type = 'Document Type is required.'
    hasError = true
  }
  if (hasError) return
  emit('upload', { ...form.value, file: file.value })
}
</script>

<template>
  <Dialog :open="open" @update:open="handleOpen">
    <DialogContent class="max-h-[95dvh] w-full overflow-y-auto sm:max-h-[90vh] sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>Upload Document</DialogTitle>
        <DialogDescription>Add a new document for {{ farmerName }}</DialogDescription>
      </DialogHeader>

      <DocumentFormFields
        v-model="form"
        :document-types="documentTypes"
        :compliance-statuses="complianceStatuses"
        :disabled="uploading"
        @file-change="
          nextFile => {
            file = nextFile
            if (nextFile) errors.file = ''
          }
        "
        @update:model-value="
          val => {
            if (val.document_type) errors.document_type = ''
          }
        "
      />
      <span v-if="errors.file" class="block text-sm text-red-500">{{ errors.file }}</span>
      <span v-if="errors.document_type" class="block text-sm text-red-500">{{
        errors.document_type
      }}</span>

      <DialogFooter class="flex-col gap-2 sm:flex-row sm:gap-0">
        <Button
          type="button"
          variant="outline"
          @click="handleOpen(false)"
        >
          Cancel
        </Button>
        <Button
          :disabled="uploading"
          class="w-full bg-blue-600 hover:bg-blue-700 sm:w-auto"
          @click="submit"
        >
          {{ uploading ? 'Uploading...' : 'Upload Document' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
