<script setup>
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Input } from '@/components/ui/input'

defineProps({
  modelValue: { type: Object, required: true },
  documentTypes: { type: Object, default: () => ({}) },
  complianceStatuses: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
  fileLabel: { type: String, default: 'File *' },
  fileRequired: { type: Boolean, default: true },
})

defineEmits(['update:modelValue', 'file-change'])
</script>

<template>
  <div class="space-y-4">
    <div>
      <Label class="mb-2 block text-sm font-medium"
        >{{ fileLabel.replace(' *', '') }} <span v-if="fileRequired" class="text-red-500">*</span></Label
      >
      <input
        type="file"
        accept=".pdf"
        class="w-full rounded-md border p-2"
        :disabled="disabled"
        @change="$emit('file-change', $event.target.files[0])"
      />
      <p class="text-muted-foreground text-xs">PDF only</p>
    </div>

    <div>
      <Label class="mb-2 block text-sm font-medium"
        >Document Type <span v-if="fileRequired" class="text-red-500">*</span></Label
      >
      <Select
        :model-value="modelValue.document_type"
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', { ...modelValue, document_type: $event })"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select document type" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="(docLabel, value) in documentTypes"
            :key="value"
            :value="value"
          >
            {{ docLabel }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div>
      <Label class="mb-2 block text-sm font-medium">Status</Label>
      <Select
        :model-value="modelValue.compliance_status"
        :disabled="disabled"
        @update:model-value="
          $emit('update:modelValue', {
            ...modelValue,
            compliance_status: $event,
          })
        "
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="(statusLabel, value) in complianceStatuses"
            :key="value"
            :value="value"
          >
            {{ statusLabel }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div>
      <Label class="mb-2 block text-sm font-medium">Registration Number</Label>
      <Input
        :model-value="modelValue.registration_number"
        type="text"
        placeholder="Enter registration number (optional)"
        :disabled="disabled"
        @update:model-value="
          $emit('update:modelValue', {
            ...modelValue,
            registration_number: $event,
          })
        "
      />
    </div>

    <div>
      <Label class="mb-2 block text-sm font-medium">Validity</Label>
      <Input
        :model-value="modelValue.validity"
        type="date"
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', { ...modelValue, validity: $event })"
      />
    </div>

    <div>
      <Label class="mb-2 block text-sm font-medium">Description</Label>
      <textarea
        :value="modelValue.description"
        placeholder="Enter document description (optional)"
        rows="3"
        class="w-full rounded-md border p-2"
        :disabled="disabled"
        @input="$emit('update:modelValue', { ...modelValue, description: $event.target.value })"
      ></textarea>
    </div>
  </div>
</template>
