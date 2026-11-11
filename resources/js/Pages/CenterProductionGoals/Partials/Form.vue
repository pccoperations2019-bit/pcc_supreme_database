<script setup>
import { displayCenterLabel } from '@/lib/center'
import { computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
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
  defaultCenterId: { type: Number, default: null },
  goal: { type: Object, default: () => ({}) },
  centers: { type: Array, required: true },
  currentYear: { type: Number, default: () => new Date().getFullYear() },
  availableYears: { type: Array, default: () => [] },
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
  programs: { type: Array, default: () => [] },
})

const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')
const isViewMode = computed(() => props.mode === 'view')

const { isCenterScoped } = useAuth()

const form = useForm({
  center_id: '',
  year: '',
  program: '',
  milk_production_goal: '',
  meat_meat_equivalent_goal: '',
  manure_based_goal: '',
  hide_production_goals: '',
})

watch(
  () => props.goal,
  newGoal => {
    if ((props.mode === 'edit' || props.mode === 'view') && newGoal?.id) {
      form.center_id = String(newGoal.center_id || '')
      form.year = String(newGoal.year || '')
      form.program = newGoal.program || ''
      form.milk_production_goal = newGoal.milk_production_goal || ''
      form.meat_meat_equivalent_goal = newGoal.meat_meat_equivalent_goal || ''
      form.manure_based_goal = newGoal.manure_based_goal || ''
      form.hide_production_goals = newGoal.hide_production_goals || ''
    }
  },
  { immediate: true, deep: true }
)

watch(
  () => props.mode,
  newMode => {
    if (newMode === 'create') {
      form.year = String(props.currentYear)
      form.program = props.programs[0]?.value || 'ih'

      // The Center field is hidden for center admins, so seed center_id from the
      // controller's default (their own center) to keep dependent fields working.
      if (!form.center_id && props.defaultCenterId) {
        form.center_id = String(props.defaultCenterId)
      }
    }
  },
  { immediate: true }
)

const onSubmit = () => {
  if (!isCreateMode.value && !isEditMode.value) return

  if (isCreateMode.value) {
    form.post(route('center-production-goals.store'), {
      preserveScroll: true,
    })
  } else {
    form.put(route('center-production-goals.update', props.goal.id), {
      preserveScroll: true,
    })
  }
}

const getCenterName = () => {
  const center = props.centers.find(c => String(c.id) === form.center_id)
  return center ? displayCenterLabel(center.code) : props.goal?.center?.name || 'N/A'
}

const getProgramLabel = () => {
  const program = props.programs.find(p => p.value === form.program)
  return program ? program.label : props.goal?.program || 'N/A'
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <Card>
      <CardHeader>
        <div class="flex items-start justify-between gap-3">
          <div>
            <CardTitle>
              {{
                isViewMode
                  ? 'Production Goal'
                  : isEditMode
                    ? 'Edit Production Goal'
                    : 'Create Production Goal'
              }}
            </CardTitle>
            <CardDescription>Fill in the production goal details for a center</CardDescription>
          </div>
          <div class="flex items-center gap-2">
            <Button
              v-if="isViewMode"
              variant="outline"
              type="button"
              @click="router.visit(route('center-production-goals.edit', props.goal.id))"
            >
              Edit
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Goal Information</CardTitle>
              <CardDescription>Center, program, and year information</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div v-if="!isCenterScoped || isViewMode" class="space-y-2">
                <Label>Center <span class="text-red-500">*</span></Label>
                <Select
                  v-if="!isViewMode"
                  :model-value="form.center_id ? String(form.center_id) : ''"
                  @update:model-value="val => (form.center_id = val)"
                >
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select center" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="center in centers"
                      :key="center.id"
                      :value="String(center.id)"
                    >
                      {{ displayCenterLabel(center.code) }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{ getCenterName() }}
                </div>
                <p v-if="form.errors.center_id" class="text-sm text-red-500">
                  {{ form.errors.center_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Program <span class="text-red-500">*</span></Label>
                <Select
                  v-if="!isViewMode"
                  :model-value="form.program"
                  @update:model-value="val => (form.program = val)"
                >
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select program" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="program in programs"
                      :key="program.value"
                      :value="program.value"
                    >
                      {{ program.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm">
                  {{ getProgramLabel() }}
                </div>
                <p v-if="form.errors.program" class="text-sm text-red-500">
                  {{ form.errors.program }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Year <span class="text-red-500">*</span></Label>
                <Input
                  v-model="form.year"
                  type="number"
                  min="2000"
                  max="2100"
                  :disabled="isViewMode"
                  @input="form.year = String(form.year).slice(0, 4)"
                />
                <p v-if="form.errors.year" class="text-sm text-red-500">
                  {{ form.errors.year }}
                </p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base">Production Goals</CardTitle>
              <CardDescription>Target production amounts for different products</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>Milk Production Goal (kg)</Label>
                <Input
                  v-model="form.milk_production_goal"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  :disabled="isViewMode"
                />
                <p v-if="form.errors.milk_production_goal" class="text-sm text-red-500">
                  {{ form.errors.milk_production_goal }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Meat Production Goal (kg)</Label>
                <Input
                  v-model="form.meat_meat_equivalent_goal"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  :disabled="isViewMode"
                />
                <p v-if="form.errors.meat_meat_equivalent_goal" class="text-sm text-red-500">
                  {{ form.errors.meat_meat_equivalent_goal }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Manure Production Goal (kg)</Label>
                <Input
                  v-model="form.manure_based_goal"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  :disabled="isViewMode"
                />
                <p v-if="form.errors.manure_based_goal" class="text-sm text-red-500">
                  {{ form.errors.manure_based_goal }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Hide Production Goal (₱)</Label>
                <Input
                  v-model="form.hide_production_goals"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  :disabled="isViewMode"
                />
                <p v-if="form.errors.hide_production_goals" class="text-sm text-red-500">
                  {{ form.errors.hide_production_goals }}
                </p>
              </div>
            </CardContent>
          </Card>

          <div v-if="!isViewMode" class="flex justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              @click="router.visit(route('center-production-goals.index'))"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              class="w-full bg-black text-white hover:bg-black/90 sm:w-auto"
              :disabled="form.processing"
            >
              {{
                form.processing
                  ? 'Saving...'
                  : isEditMode
                    ? 'Save Changes'
                    : 'Create Production Goal'
              }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
