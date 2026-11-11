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

const addInfrastructure = () => {
  emit('add')
}

const removeInfrastructure = index => {
  emit('remove', index)
}
</script>

<template>
  <Card>
    <CardHeader>
      <div class="flex items-start justify-between">
        <div>
          <CardTitle class="text-base">Infrastructure Documents</CardTitle>
          <CardDescription>Attachments and ownership documentation</CardDescription>
        </div>
        <Button
          v-if="!isViewMode"
          type="button"
          variant="outline"
          size="sm"
          @click="addInfrastructure"
        >
          <Plus class="mr-1 h-4 w-4" /> Add Infrastructure
        </Button>
      </div>
    </CardHeader>
    <CardContent>
      <div v-for="(infra, index) in localValue" :key="index" class="mb-6">
        <div class="mb-4 flex items-center justify-between">
          <Button
            v-if="!isViewMode && localValue.length > 1"
            type="button"
            variant="outline"
            size="sm"
            @click="removeInfrastructure(index)"
          >
            <Trash2 class="h-4 w-4" />
          </Button>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label :for="`infra.${index}.attachment`">Attachment</Label>
            <Input
              v-if="!isViewMode"
              :id="`infra.${index}.attachment`"
              type="file"
              @change="e => (infra.attachment = e.target.files[0])"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ infra.attachment || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`infra.${index}.ownership_document`">Ownership Document</Label>
            <Input
              v-if="!isViewMode"
              :id="`infra.${index}.ownership_document`"
              type="file"
              @change="e => (infra.ownership_document = e.target.files[0])"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ infra.ownership_document || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`infra.${index}.description`">Description</Label>
            <Input
              v-if="!isViewMode"
              :id="`infra.${index}.description`"
              v-model="infra.description"
              placeholder="Enter description"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ infra.description || 'N/A' }}
            </div>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
