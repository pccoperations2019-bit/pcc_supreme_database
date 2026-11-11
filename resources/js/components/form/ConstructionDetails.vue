<script setup>
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
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
  modelValue: {
    type: Object,
    default: () => ({
      type: '',
      dairybox_id: null,
      production_id: null,
      date_of_establishment: '',
    }),
  },
  isViewMode: {
    type: Boolean,
    default: false,
  },
  dairyBoxOptions: {
    type: Array,
    default: () => [],
  },
  productionOptions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const localValue = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const constructionTypeOptions = [
  { value: 'processing_infrastructure', label: 'Processing Infrastructure' },
  { value: 'dairybox', label: 'Dairy Box' },
  { value: 'production_site', label: 'Production Site' },
]
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-base">Construction Details</CardTitle>
      <CardDescription>Construction type and related information</CardDescription>
    </CardHeader>
    <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <div class="space-y-2">
        <Label for="construction_type">Construction Type</Label>
        <Select v-if="!isViewMode" v-model="localValue.type">
          <SelectTrigger class="h-9 w-full">
            <SelectValue placeholder="Select construction type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in constructionTypeOptions"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
        <div v-else class="rounded bg-gray-50 p-2 text-sm">
          {{ constructionTypeOptions.find(o => o.value === localValue.type)?.label || 'N/A' }}
        </div>
      </div>

      <div class="space-y-2">
        <Label for="date_of_establishment">Date of Establishment</Label>
        <Input
          v-if="!isViewMode"
          id="date_of_establishment"
          v-model="localValue.date_of_establishment"
          type="date"
        />
        <div v-else class="rounded bg-gray-50 p-2 text-sm">
          {{ localValue.date_of_establishment || 'N/A' }}
        </div>
      </div>

      <!-- Dairy Box section (shown when type is 'dairybox') -->
      <template v-if="localValue.type === 'dairybox'">
        <div class="space-y-2 md:col-span-2">
          <Label for="dairybox_id">Dairy Box</Label>
          <Select v-if="!isViewMode" v-model="localValue.dairybox_id">
            <SelectTrigger class="h-9 w-full">
              <SelectValue placeholder="Select dairy box" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="option in dairyBoxOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>
          <div v-else class="rounded bg-gray-50 p-2 text-sm">
            {{ dairyBoxOptions.find(o => o.value === localValue.dairybox_id)?.label || 'N/A' }}
          </div>
        </div>
      </template>

      <!-- Production Site section (shown when type is 'production_site') -->
      <template v-if="localValue.type === 'production_site'">
        <div class="space-y-2 md:col-span-2">
          <Label for="production_id">Production Site</Label>
          <Select v-if="!isViewMode" v-model="localValue.production_id">
            <SelectTrigger class="h-9 w-full">
              <SelectValue placeholder="Select production site" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="option in productionOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>
          <div v-else class="rounded bg-gray-50 p-2 text-sm">
            {{ productionOptions.find(o => o.value === localValue.production_id)?.label || 'N/A' }}
          </div>
        </div>
      </template>
    </CardContent>
  </Card>
</template>
