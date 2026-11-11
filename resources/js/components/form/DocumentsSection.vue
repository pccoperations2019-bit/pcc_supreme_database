<script setup>
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Plus, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  isViewMode: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'add', 'remove'])

const localValue = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const addDocument = () => {
  emit('add')
}

const removeDocument = index => {
  emit('remove', index)
}
</script>

<template>
  <Card>
    <CardHeader>
      <div class="flex items-start justify-between">
        <div>
          <CardTitle class="text-base">Documents</CardTitle>
          <CardDescription>Project-related documentation and attachments</CardDescription>
        </div>
        <Button v-if="!isViewMode" type="button" variant="outline" size="sm" @click="addDocument">
          <Plus class="mr-1 h-4 w-4" /> Add Document
        </Button>
      </div>
    </CardHeader>
    <CardContent>
      <div v-for="(doc, index) in localValue" :key="index" class="mb-6">
        <div class="mb-4 flex items-center justify-between">
          <Button
            v-if="!isViewMode && localValue.length > 1"
            type="button"
            variant="outline"
            size="sm"
            @click="removeDocument(index)"
          >
            <Trash2 class="h-4 w-4" />
          </Button>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label :for="`doc.${index}.document_type`">Document Type</Label>
            <Input
              v-if="!isViewMode"
              :id="`doc.${index}.document_type`"
              v-model="doc.document_type"
              placeholder="e.g. MOA, Contract"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ doc.document_type || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`doc.${index}.file`">File</Label>
            <Input
              v-if="!isViewMode"
              :id="`doc.${index}.file`"
              type="file"
              @change="e => (doc.file = e.target.files[0])"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ doc.file || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2 md:col-span-2">
            <Label :for="`doc.${index}.description`">Description</Label>
            <Input
              v-if="!isViewMode"
              :id="`doc.${index}.description`"
              v-model="doc.description"
              placeholder="Enter document description"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ doc.description || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`doc.${index}.date_uploaded`">Date Uploaded</Label>
            <Input
              v-if="!isViewMode"
              :id="`doc.${index}.date_uploaded`"
              v-model="doc.date_uploaded"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ doc.date_uploaded || 'N/A' }}
            </div>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
