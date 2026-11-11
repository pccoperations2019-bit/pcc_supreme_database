<script setup>
/* global route */
import { computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useAuth } from '@/composable/useAuth'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'

const props = defineProps({
  source: { type: Object, default: () => ({}) },
  sourceLabels: { type: Object, required: true },
  sourceNames: { type: Object, required: true },
  currentYear: { type: Number, default: () => new Date().getFullYear() },
  mode: { type: String, required: true },
})
const { hasPermission } = useAuth()
const isViewMode = computed(() => props.mode === 'view')
const isEditMode = computed(() => props.mode === 'edit')
const title = computed(() =>
  isViewMode.value
    ? 'Beneficiary Source'
    : isEditMode.value
      ? 'Edit Beneficiary Source'
      : 'Create Beneficiary Source'
)
const form = useForm({
  code: props.source.code ?? '',
  name: props.source.name ?? '',
  year: props.source.year ?? props.currentYear,
  amount: props.source.amount ?? 0,
})
watch(
  () => form.code,
  code => {
    form.name = props.sourceNames[code] ?? ''
  },
  { immediate: true }
)
const onSubmit = () => {
  if (isViewMode.value) return
  if (isEditMode.value) {
    form.put(route('beneficiary-sources.update', props.source.id), { preserveScroll: true })
  } else {
    form.post(route('beneficiary-sources.store'), { preserveScroll: true })
  }
}
</script>

<template>
  <Card>
    <CardHeader>
      <div class="flex items-start justify-between gap-3">
        <div>
          <CardTitle>{{ title }}</CardTitle>
          <CardDescription
            >Funding source and total number of beneficiaries for a year.</CardDescription
          >
        </div>
        <Button
          v-if="isViewMode && hasPermission('manage-beneficiary-sources')"
          variant="outline"
          @click="router.visit(route('beneficiary-sources.edit', source.id))"
          >Edit</Button
        >
      </div>
    </CardHeader>
    <CardContent>
      <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Source Information</CardTitle>
            <CardDescription>Select a funding source to fill in its name.</CardDescription>
          </CardHeader>
          <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="source-code">Funded Type <span class="text-red-500">*</span></Label>
              <Select v-model="form.code" :disabled="isViewMode">
                <SelectTrigger id="source-code" class="w-full">
                  <SelectValue placeholder="Select funded type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="(label, code) in sourceLabels" :key="code" :value="code">
                    {{ label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.code" class="text-sm text-red-500">{{ form.errors.code }}</p>
            </div>
            <div class="space-y-2">
              <Label for="source-name">Funded Name <span class="text-red-500">*</span></Label>
              <Input
                id="source-name"
                v-model="form.name"
                disabled
                placeholder="Select a funded type"
              />
              <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
              <Label for="source-year">Year <span class="text-red-500">*</span></Label>
              <Input
                id="source-year"
                v-model="form.year"
                type="number"
                min="2000"
                max="2100"
                step="1"
                required
                :disabled="isViewMode"
              />
              <p v-if="form.errors.year" class="text-sm text-red-500">{{ form.errors.year }}</p>
            </div>
            <div class="space-y-2">
              <Label for="source-amount">Amount <span class="text-red-500">*</span></Label>
              <Input
                id="source-amount"
                v-model="form.amount"
                type="number"
                min="0"
                max="4294967295"
                step="1"
                required
                :disabled="isViewMode"
              />
              <p v-if="form.errors.amount" class="text-sm text-red-500">{{ form.errors.amount }}</p>
            </div>
          </CardContent>
        </Card>
        <div class="flex justify-end gap-2">
          <Button
            type="button"
            variant="outline"
            @click="router.visit(route('beneficiary-sources.index'))"
            >{{ isViewMode ? 'Back' : 'Cancel' }}</Button
          >
          <Button
            v-if="!isViewMode"
            type="submit"
            class="bg-black text-white hover:bg-black/90"
            :disabled="form.processing"
            >{{
              form.processing
                ? 'Saving...'
                : isEditMode
                  ? 'Save Changes'
                  : 'Create Beneficiary Source'
            }}</Button
          >
        </div>
      </form>
    </CardContent>
  </Card>
</template>
