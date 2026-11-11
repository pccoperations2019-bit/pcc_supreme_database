<script setup>
import { computed, defineProps, defineEmits } from 'vue'
import { Pencil } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import DetailCard from '@/components/DetailCard.vue'
import { formatBoolYesNo } from '@/lib/utils'

const props = defineProps({
  farmer: { type: Object, required: true },
  additionalDetails: { type: Object, default: () => ({}) },
  isEditMode: { type: Boolean, default: false },
  actionLabel: { type: String, default: 'Edit' },
})

const emit = defineEmits(['edit'])

const toBool = value => {
  if (value === true || value === false) return value
  if (value === null || value === undefined || value === '') return false
  return ['true', 'yes', 'y', '1', 'on'].includes(String(value).trim().toLowerCase())
}

const manureMethods = computed(() => [
  { label: 'Dry lot/Stock Piling', value: toBool(props.farmer.is_drylot) },
  { label: 'Vermicomposting', value: toBool(props.farmer.is_vermicomposting) },
  { label: 'Pasture/Range/Paddock', value: toBool(props.farmer.is_pasture_range_paddock) },
  { label: 'Daily Spread', value: toBool(props.farmer.is_daily_spread) },
  {
    label: 'Solid Storage – Bulking Agent Addition',
    value: toBool(props.farmer.is_storage_bulking),
  },
  { label: 'Solid Storage covered', value: toBool(props.farmer.is_solid_storage_covered) },
  { label: 'Solid Storage – additives', value: toBool(props.farmer.is_solid_storage_additives) },
  { label: 'Liquid/Slurry', value: toBool(props.farmer.is_liquid_slurry) },
  {
    label: 'Uncovered Anaerobic Lagoon',
    value: toBool(props.farmer.is_uncovered_anaerobic_lagoon),
  },
  {
    label: 'Septic Tank',
    value: toBool(props.additionalDetails.isseptictank),
  },
  { label: 'Anaerobic Digester', value: toBool(props.farmer.is_anaerobic_digester) },
  { label: 'Burned for fuel', value: toBool(props.additionalDetails.isburnedforfuel) },
])

const appliedInOptions = computed(() => [
  { label: 'Rice Field', value: toBool(props.farmer.is_dry_rice_field) },
  { label: 'Forage area', value: toBool(props.additionalDetails.isdryforage) },
  { label: 'Vegetable area', value: toBool(props.farmer.is_dry_vegetable) },
  { label: 'Sold', value: toBool(props.additionalDetails.isdrysold) },
  { label: 'Give away to neighbors', value: toBool(props.additionalDetails.isdryneighbors) },
  { label: 'Others', value: toBool(props.additionalDetails.isdryothers) },
])

const checkedManureMethods = computed(() =>
  manureMethods.value.filter(m => m.value).map(m => m.label)
)

const checkedAppliedIn = computed(() =>
  appliedInOptions.value.filter(o => o.value).map(o => o.label)
)

const otherRemarks = computed(() => {
  const value = props.additionalDetails.dryotherremarks
  return value && !['false', 'FALSE', 'False', 'no', 'No', 'NO'].includes(value) ? value : null
})
</script>

<template>
  <div>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <DetailCard
        title="Manure Management Method"
        :rows="
          checkedManureMethods.length
            ? checkedManureMethods.map(label => ({ label, value: 'Yes' }))
            : [{ label: 'Manure Management Method', value: 'N/A' }]
        "
      />
      <DetailCard
        title="Dry Lot Details"
        :rows="[
          { label: 'Duration of Stocking', value: farmer.duration_of_stocking ?? 'N/A' },
          {
            label: 'Applied in the Field?',
            value: formatBoolYesNo(toBool(farmer.is_applied_in_the_field)),
          },
          {
            label: 'Applied In',
            value: checkedAppliedIn.length ? checkedAppliedIn.join(', ') : 'N/A',
          },
          { label: 'Other Remarks', value: otherRemarks },
        ]"
      />
    </div>
    <div v-if="isEditMode" class="mt-4">
      <Button size="sm" variant="outline" @click="emit('edit', 'waste-management')">
        <Pencil class="mr-2 h-4 w-4" />{{ actionLabel }} Waste Management
      </Button>
    </div>
  </div>
</template>
