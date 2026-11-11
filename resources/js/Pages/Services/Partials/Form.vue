<script setup>
import { computed, watch, watchEffect, ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { toastFlash } from '@/lib/flashToasts'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
  service: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
})

const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')
const isViewMode = computed(() => props.mode === 'view')

// category/sub_category are JSON arrays in the database, so tolerate legacy string values too.
const toList = value => {
  if (Array.isArray(value)) return value.filter(Boolean)
  if (!value) return []
  try {
    const parsed = JSON.parse(value)
    return Array.isArray(parsed) ? parsed.filter(Boolean) : []
  } catch {
    return [value]
  }
}

const form = useForm({
  simplified_code: props.service.simplified_code || '',
  mfo_code: props.service.mfo_code || '',
  name: props.service.name || '',
  program: props.service.program || '',
  value_chain_segment: props.service.value_chain_segment || '',
  category: toList(props.service.category),
  sub_category: toList(props.service.sub_category),
  type: props.service.type || [],
  description: props.service.description || '',
  is_title: props.service.is_title ?? 0,
})

const newCategory = ref('')
const newSubCategory = ref('')

const addValue = (list, input) => {
  const value = input.value.trim()
  input.value = ''
  if (!value || list.includes(value)) return
  list.push(value)
}

const addCategory = () => addValue(form.category, newCategory)
const addSubCategory = () => addValue(form.sub_category, newSubCategory)

const removeValue = (list, index) => {
  list.splice(index, 1)
}

const fieldError = field => {
  const key = Object.keys(form.errors).find(err => err === field || err.startsWith(`${field}.`))
  return key ? form.errors[key] : ''
}

const submit = () => {
  if (isCreateMode.value) {
    form.post(route('services.store'), {
      onSuccess: () => {
        toastFlash({
          type: 'success',
          message: 'Service created successfully.',
        })
      },
      onError: errors => {
        console.error('Form errors:', errors)
        toastFlash({
          type: 'error',
          message: 'There was an error creating the service.',
        })
      },
    })
  } else if (isEditMode.value) {
    form.put(route('services.update', props.service.id), {
      onSuccess: () => {
        toastFlash({
          type: 'success',
          message: 'Service updated successfully.',
        })
      },
      onError: errors => {
        console.error('Form errors:', errors)
        toastFlash({
          type: 'error',
          message: 'There was an error updating the service.',
        })
      },
    })
  }
}

const cancel = () => {
  router.visit(route('services.index'))
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <Card>
      <CardHeader>
        <CardTitle>
          {{
            isCreateMode ? 'Create New Service' : isEditMode ? 'Edit Service' : 'Service Details'
          }}
        </CardTitle>
        <CardDescription>
          {{
            isCreateMode
              ? 'Fill in the information below to create a new service.'
              : isEditMode
                ? 'Update the service information below.'
                : 'View the service details below.'
          }}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form class="flex flex-col gap-4" @submit.prevent="submit">
          <!-- Service Details Card -->
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Service Details</CardTitle>
              <CardDescription>Basic service information and codes</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="simplified_code">Simplified Code</Label>
                <Input
                  id="simplified_code"
                  v-model="form.simplified_code"
                  placeholder="e.g., 1.1.1"
                  :disabled="isViewMode"
                  :class="{ 'border-red-500': form.errors.simplified_code }"
                />
                <p v-if="form.errors.simplified_code" class="text-sm text-red-600">
                  {{ form.errors.simplified_code }}
                </p>
              </div>
              <div class="space-y-2">
                <Label for="mfo_code">MFO Code</Label>
                <Input
                  id="mfo_code"
                  v-model="form.mfo_code"
                  placeholder="e.g., 1.1"
                  :disabled="isViewMode"
                  :class="{ 'border-red-500': form.errors.mfo_code }"
                />
                <p v-if="form.errors.mfo_code" class="text-sm text-red-600">
                  {{ form.errors.mfo_code }}
                </p>
              </div>
              <div class="space-y-2">
                <Label for="name">Service Name *</Label>
                <Input
                  id="name"
                  v-model="form.name"
                  placeholder="Enter service name"
                  :disabled="isViewMode"
                  :required="!isViewMode"
                  :class="{ 'border-red-500': form.errors.name }"
                />
                <p v-if="form.errors.name" class="text-sm text-red-600">
                  {{ form.errors.name }}
                </p>
              </div>
              <div class="space-y-2">
                <Label for="is_title">MFO Title</Label>
                <Select v-model="form.is_title" :disabled="isViewMode">
                  <SelectTrigger :class="{ 'border-red-500': form.errors.is_title }">
                    <SelectValue placeholder="Select option" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="1"> Yes </SelectItem>
                    <SelectItem :value="0"> No </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.is_title" class="text-sm text-red-600">
                  {{ form.errors.is_title }}
                </p>
              </div>
              <div class="space-y-2">
                <Label for="program">Program</Label>
                <Select v-model="form.program" :disabled="isViewMode">
                  <SelectTrigger :class="{ 'border-red-500': form.errors.program }">
                    <SelectValue placeholder="Select program" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="(label, value) in enums?.program || {}"
                      :key="value"
                      :value="value"
                    >
                      {{ label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.program" class="text-sm text-red-600">
                  {{ form.errors.program }}
                </p>
              </div>
              <div class="space-y-2 md:col-span-2">
                <Label for="category">Category</Label>
                <div v-if="form.category.length" class="flex flex-wrap gap-2">
                  <span
                    v-for="(item, index) in form.category"
                    :key="`${item}-${index}`"
                    class="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-1 text-sm text-slate-700"
                  >
                    {{ item }}
                    <button
                      v-if="!isViewMode"
                      type="button"
                      class="text-slate-500 hover:text-red-600"
                      aria-label="Remove category"
                      @click="removeValue(form.category, index)"
                    >
                      ×
                    </button>
                  </span>
                </div>
                <div v-if="!isViewMode" class="flex gap-2">
                  <Input
                    id="category"
                    v-model="newCategory"
                    placeholder="Type a category and press Enter"
                    :class="{ 'border-red-500': fieldError('category') }"
                    @keydown.enter.prevent="addCategory"
                  />
                  <Button type="button" variant="outline" @click="addCategory"> Add </Button>
                </div>
                <p v-if="fieldError('category')" class="text-sm text-red-600">
                  {{ fieldError('category') }}
                </p>
              </div>
              <div class="space-y-2 md:col-span-2">
                <Label for="sub_category">Sub Category</Label>
                <div v-if="form.sub_category.length" class="flex flex-wrap gap-2">
                  <span
                    v-for="(item, index) in form.sub_category"
                    :key="`${item}-${index}`"
                    class="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-1 text-sm text-slate-700"
                  >
                    {{ item }}
                    <button
                      v-if="!isViewMode"
                      type="button"
                      class="text-slate-500 hover:text-red-600"
                      aria-label="Remove sub category"
                      @click="removeValue(form.sub_category, index)"
                    >
                      ×
                    </button>
                  </span>
                </div>
                <div v-if="!isViewMode" class="flex gap-2">
                  <Input
                    id="sub_category"
                    v-model="newSubCategory"
                    placeholder="Type a sub category and press Enter"
                    :class="{ 'border-red-500': fieldError('sub_category') }"
                    @keydown.enter.prevent="addSubCategory"
                  />
                  <Button type="button" variant="outline" @click="addSubCategory"> Add </Button>
                </div>
                <p v-if="fieldError('sub_category')" class="text-sm text-red-600">
                  {{ fieldError('sub_category') }}
                </p>
              </div>
              <div class="space-y-2 md:col-span-2">
                <Label for="description">Description</Label>
                <Textarea
                  id="description"
                  v-model="form.description"
                  placeholder="Enter service description"
                  rows="3"
                  :disabled="isViewMode"
                  :class="{ 'border-red-500': form.errors.description }"
                />
                <p v-if="form.errors.description" class="text-sm text-red-600">
                  {{ form.errors.description }}
                </p>
              </div>
            </CardContent>
          </Card>

          <!-- Actions -->
          <div class="flex justify-end space-x-4 pt-6">
            <Button type="button" variant="outline" :disabled="form.processing" @click="cancel">
              Cancel
            </Button>
            <Button v-if="!isViewMode" type="submit" :disabled="form.processing">
              {{ isCreateMode ? 'Create Service' : 'Update Service' }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
