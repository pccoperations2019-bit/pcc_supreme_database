<script setup>
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Trash2 } from 'lucide-vue-next'

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

const removeTrainingRecord = index => {
  emit('remove', index)
}
</script>

<template>
  <Card>
    <CardHeader>
      <div class="flex items-start justify-between gap-3">
        <div>
          <CardTitle class="text-base">Training Records</CardTitle>
          <CardDescription>Training conducted and participant information</CardDescription>
        </div>
      </div>
    </CardHeader>
    <CardContent>
      <div v-for="(training, index) in localValue" :key="index" class="mb-6">
        <div class="mb-4 flex items-center justify-between">
          <Button
            v-if="!isViewMode && localValue.length > 1"
            type="button"
            variant="outline"
            size="sm"
            @click="removeTrainingRecord(index)"
          >
            <Trash2 class="h-4 w-4" />
          </Button>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <!-- Project Orientation -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.project_orientation_date`"
              >Project Orientation Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.project_orientation_date`"
              v-model="training.project_orientation_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.project_orientation_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.project_orientation_manual_pax`"
              >Project Orientation Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.project_orientation_manual_pax`"
              v-model.number="training.project_orientation_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.project_orientation_manual_pax || 0 }}
            </div>
          </div>

          <!-- Strategic Planning -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.strategic_planning_date`"
              >Strategic Planning Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.strategic_planning_date`"
              v-model="training.strategic_planning_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.strategic_planning_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.strategic_planning_manual_pax`"
              >Strategic Planning Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.strategic_planning_manual_pax`"
              v-model.number="training.strategic_planning_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.strategic_planning_manual_pax || 0 }}
            </div>
          </div>

          <!-- Social Preparation Training -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.social_preparation_training_date`"
              >Social Preparation Training Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.social_preparation_training_date`"
              v-model="training.social_preparation_training_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.social_preparation_training_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.social_preparation_training_manual_pax`"
              >Social Preparation Training Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.social_preparation_training_manual_pax`"
              v-model.number="training.social_preparation_training_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.social_preparation_training_manual_pax || 0 }}
            </div>
          </div>

          <!-- Forage Production Training -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.forage_production_training_date`"
              >Forage Production Training Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.forage_production_training_date`"
              v-model="training.forage_production_training_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.forage_production_training_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.forage_production_training_manual_pax`"
              >Forage Production Training Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.forage_production_training_manual_pax`"
              v-model.number="training.forage_production_training_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.forage_production_training_manual_pax || 0 }}
            </div>
          </div>

          <!-- Silage Making Training -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.silage_making_training_date`"
              >Silage Making Training Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.silage_making_training_date`"
              v-model="training.silage_making_training_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.silage_making_training_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.silage_making_training_manual_pax`"
              >Silage Making Training Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.silage_making_training_manual_pax`"
              v-model.number="training.silage_making_training_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.silage_making_training_manual_pax || 0 }}
            </div>
          </div>

          <!-- Animal Health Training -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.animal_health_training_date`"
              >Animal Health Training Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.animal_health_training_date`"
              v-model="training.animal_health_training_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.animal_health_training_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.animal_health_training_manual_pax`"
              >Animal Health Training Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.animal_health_training_manual_pax`"
              v-model.number="training.animal_health_training_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.animal_health_training_manual_pax || 0 }}
            </div>
          </div>

          <!-- Breeding Management Training -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.breeding_management_training_date`"
              >Breeding Management Training Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.breeding_management_training_date`"
              v-model="training.breeding_management_training_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.breeding_management_training_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.breeding_management_training_manual_pax`"
              >Breeding Management Training Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.breeding_management_training_manual_pax`"
              v-model.number="training.breeding_management_training_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.breeding_management_training_manual_pax || 0 }}
            </div>
          </div>

          <!-- Milk Handling Training -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.milk_handling_training_date`"
              >Milk Handling Training Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.milk_handling_training_date`"
              v-model="training.milk_handling_training_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.milk_handling_training_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.milk_handling_training_manual_pax`"
              >Milk Handling Training Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.milk_handling_training_manual_pax`"
              v-model.number="training.milk_handling_training_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.milk_handling_training_manual_pax || 0 }}
            </div>
          </div>

          <!-- Milk Processing Training -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.milk_processing_training_date`"
              >Milk Processing Training Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.milk_processing_training_date`"
              v-model="training.milk_processing_training_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.milk_processing_training_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.milk_processing_training_manual_pax`"
              >Milk Processing Training Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.milk_processing_training_manual_pax`"
              v-model.number="training.milk_processing_training_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.milk_processing_training_manual_pax || 0 }}
            </div>
          </div>

          <!-- Animal Husbandry Training -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.animal_husbandry_training_date`"
              >Animal Husbandry Training Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.animal_husbandry_training_date`"
              v-model="training.animal_husbandry_training_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.animal_husbandry_training_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.animal_husbandry_training_manual_pax`"
              >Animal Husbandry Training Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.animal_husbandry_training_manual_pax`"
              v-model.number="training.animal_husbandry_training_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.animal_husbandry_training_manual_pax || 0 }}
            </div>
          </div>

          <!-- Organic Fertilizer Training -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.organic_fertilizer_training_date`"
              >Organic Fertilizer Training Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.organic_fertilizer_training_date`"
              v-model="training.organic_fertilizer_training_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.organic_fertilizer_training_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.organic_fertilizer_training_manual_pax`"
              >Organic Fertilizer Training Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.organic_fertilizer_training_manual_pax`"
              v-model.number="training.organic_fertilizer_training_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.organic_fertilizer_training_manual_pax || 0 }}
            </div>
          </div>

          <!-- Cooperative Management Training -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.cooperative_management_training_date`"
              >Cooperative Management Training Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.cooperative_management_training_date`"
              v-model="training.cooperative_management_training_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.cooperative_management_training_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.cooperative_management_training_manual_pax`"
              >Cooperative Management Training Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.cooperative_management_training_manual_pax`"
              v-model.number="training.cooperative_management_training_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.cooperative_management_training_manual_pax || 0 }}
            </div>
          </div>

          <!-- FLS Launching -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.fls_launching_date`">FLS Launching Date</Label>
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.fls_launching_date`"
              v-model="training.fls_launching_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.fls_launching_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.fls_launching_manual_pax`"
              >FLS Launching Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.fls_launching_manual_pax`"
              v-model.number="training.fls_launching_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.fls_launching_manual_pax || 0 }}
            </div>
          </div>

          <!-- FLS Buffalo Training -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.fls_buffalo_training_date`"
              >FLS Buffalo Training Date</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.fls_buffalo_training_date`"
              v-model="training.fls_buffalo_training_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.fls_buffalo_training_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.fls_buffalo_training_manual_pax`"
              >FLS Buffalo Training Participants</Label
            >
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.fls_buffalo_training_manual_pax`"
              v-model.number="training.fls_buffalo_training_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.fls_buffalo_training_manual_pax || 0 }}
            </div>
          </div>

          <!-- FLS Date -->
          <div class="space-y-2">
            <Label :for="`training_records.${index}.fls_date`">FLS Date</Label>
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.fls_date`"
              v-model="training.fls_date"
              type="date"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.fls_date || 'N/A' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label :for="`training_records.${index}.fls_manual_pax`">FLS Participants</Label>
            <Input
              v-if="!isViewMode"
              :id="`training_records.${index}.fls_manual_pax`"
              v-model.number="training.fls_manual_pax"
              type="number"
              min="0"
            />
            <div v-else class="rounded bg-gray-50 p-2 text-sm">
              {{ training.fls_manual_pax || 0 }}
            </div>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
