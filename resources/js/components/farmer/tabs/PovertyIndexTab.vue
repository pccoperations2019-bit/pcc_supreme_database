<script setup>
import { computed, defineProps, defineEmits } from 'vue'
import { Pencil } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import DetailCard from '@/components/DetailCard.vue'
import { label, formatBoolYesNo } from '@/lib/utils'

const props = defineProps({
  farmer: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
  additionalDetails: { type: Object, default: () => ({}) },
  isEditMode: { type: Boolean, default: false },
  actionLabel: { type: String, default: 'Edit' },
})

const emit = defineEmits(['edit'])

const toBooleanLike = value => {
  if (value === true || value === false) return value
  if (value === null || value === undefined || value === '') return false
  return ['true', 'yes', 'y', '1', 'on'].includes(String(value).trim().toLowerCase())
}

const povertyIndexRows = computed(() => [
  {
    label: 'Roof Material Type',
    value: props.additionalDetails.roofmadeof || null,
  },
  {
    label: 'Outer Wall Material Type',
    value: props.additionalDetails.housemadeof || null,
  },
  {
    label: 'House Has Access to Electricity?',
    value: formatBoolYesNo(props.additionalDetails.isaccesstoelectricity),
  },
  {
    label: 'Does your household own a refrigerator or freezer?',
    value: formatBoolYesNo(props.additionalDetails.isownrefrigerator),
  },
  {
    label: 'Does your household own a washing machine?',
    value: formatBoolYesNo(props.additionalDetails.isownwashingmachine),
  },
  {
    label: 'Does your household own a television or TV?',
    value: formatBoolYesNo(props.additionalDetails.isowntelevision),
  },
  ...(toBooleanLike(props.additionalDetails.isowntelevision)
    ? [
        {
          label: 'TV Type',
          value: props.farmer.tv_type ? label(props.enums.tvType, props.farmer.tv_type) : null,
        },
      ]
    : []),
  {
    label: 'Does your household own a radio?',
    value: formatBoolYesNo(props.additionalDetails.isownradio),
  },
])

const waterSourceRows = computed(() => [
  {
    label: 'Dug well with or without pump?',
    value: formatBoolYesNo(props.additionalDetails.isdugwell),
  },
  {
    label: 'Own waterline like Maynilad or MWSS?',
    value: formatBoolYesNo(props.additionalDetails.isownwaterline),
  },
  {
    label: 'Shared water line like Maynilad or MWSS?',
    value: formatBoolYesNo(props.additionalDetails.issharedwaterline),
  },
  {
    label: 'Own deep well with a hand or motor pump?',
    value: formatBoolYesNo(props.additionalDetails.isowndeepwell),
  },
  {
    label: 'Shared deep well with a hand or motor pump?',
    value: formatBoolYesNo(props.additionalDetails.isshareddeepwell),
  },
  { label: 'Others?', value: formatBoolYesNo(props.additionalDetails.isothers) },
  {
    label: 'Other Remarks',
    value:
      props.additionalDetails.otherremarks &&
      !['false', 'FALSE', 'False', 'no', 'No', 'NO'].includes(props.additionalDetails.otherremarks)
        ? props.additionalDetails.otherremarks
        : null,
  },
])
</script>

<template>
  <div>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <DetailCard title="Household Appliances" :rows="povertyIndexRows" />
      <DetailCard title="Water Source" :rows="waterSourceRows" />
    </div>
    <div v-if="isEditMode" class="mt-4">
      <Button size="sm" variant="outline" @click="emit('edit', 'poverty-index')">
        <Pencil class="mr-2 h-4 w-4" />{{ actionLabel }} Poverty Index
      </Button>
    </div>
  </div>
</template>
