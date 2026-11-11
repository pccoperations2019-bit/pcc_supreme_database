<script setup>
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({
      budget_allocated: 0,
      obligated: 0,
      disbursed: 0,
      utilization_percentage: 0,
    }),
  },
  isViewMode: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const localValue = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const utilizationPercentage = computed(() => {
  if (!localValue.value.budget_allocated || localValue.value.budget_allocated === 0) return 0
  return ((localValue.value.disbursed / localValue.value.budget_allocated) * 100).toFixed(2)
})
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-base">Budget Utilization</CardTitle>
      <CardDescription>Budget allocation and utilization tracking</CardDescription>
    </CardHeader>
    <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <div class="space-y-2">
        <Label for="budget_allocated">Budget Allocated</Label>
        <Input
          v-if="!isViewMode"
          id="budget_allocated"
          v-model.number="localValue.budget_allocated"
          type="number"
          min="0"
          step="0.01"
        />
        <div v-else class="rounded bg-gray-50 p-2 text-sm">
          ₱{{ Number(localValue.budget_allocated || 0).toLocaleString() }}
        </div>
      </div>

      <div class="space-y-2">
        <Label for="obligated">Obligated</Label>
        <Input
          v-if="!isViewMode"
          id="obligated"
          v-model.number="localValue.obligated"
          type="number"
          min="0"
          step="0.01"
        />
        <div v-else class="rounded bg-gray-50 p-2 text-sm">
          ₱{{ Number(localValue.obligated || 0).toLocaleString() }}
        </div>
      </div>

      <div class="space-y-2">
        <Label for="disbursed">Disbursed</Label>
        <Input
          v-if="!isViewMode"
          id="disbursed"
          v-model.number="localValue.disbursed"
          type="number"
          min="0"
          step="0.01"
        />
        <div v-else class="rounded bg-gray-50 p-2 text-sm">
          ₱{{ Number(localValue.disbursed || 0).toLocaleString() }}
        </div>
      </div>

      <div class="space-y-2">
        <Label for="utilization_percentage">Utilization Percentage</Label>
        <div class="rounded bg-gray-50 p-2 text-sm font-medium">{{ utilizationPercentage }}%</div>
      </div>
    </CardContent>
  </Card>
</template>
