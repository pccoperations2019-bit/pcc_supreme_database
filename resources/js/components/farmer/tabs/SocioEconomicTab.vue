<script setup>
import { computed, defineProps, defineEmits } from 'vue'
import { Pencil } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import DetailCard from '@/components/DetailCard.vue'
import { label, formatBoolYesNo } from '@/lib/utils'

const props = defineProps({
  farmer: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
  membershipSource: { type: [String, null], default: null },
  organizationNameSource: { type: [String, null], default: null },
  isEditMode: { type: Boolean, default: false },
  actionLabel: { type: String, default: 'Edit' },
})

const emit = defineEmits(['edit'])

const householdAndFamilyRows = computed(() => [
  { label: 'Female Count', value: props.farmer.female_count },
  { label: 'Male Count', value: props.farmer.male_count },
  {
    label: 'Family Member Attending School',
    value: props.farmer.family_member_attending_school_count,
  },
  {
    label: 'Are You the Head of the Family',
    value: formatBoolYesNo(props.farmer.are_you_the_head_of_the_family),
  },
  {
    label: 'If No, Highest Educational Attainment of Head of the Family',
    value: props.farmer.if_no_highest_educational_attainment_of_head_of_family,
  },
])

const socioEconomicProfileRows = computed(() => [
  { label: 'PWD Member', value: formatBoolYesNo(props.farmer.is_pwd) },
  { label: '4Ps Member', value: formatBoolYesNo(props.farmer.is_4ps) },
  { label: 'IPS Member', value: formatBoolYesNo(props.farmer.is_ips) },
  { label: 'Indigent Person', value: formatBoolYesNo(props.farmer.is_indigent) },
  { label: 'Indigenous Group', value: props.farmer.indigenous_group },
  {
    label: 'Educational Attainment',
    value: props.farmer.educational_attainment
      ? label(props.enums.educationalAttainment, props.farmer.educational_attainment)
      : null,
  },
  { label: 'Position', value: props.farmer.position },
  {
    label: 'Year Registered as a Member',
    value: props.farmer.year_involvement_in_business,
  },
  {
    label: 'Involved in Carabao Farming',
    value: formatBoolYesNo(props.farmer.involved_in_buffalo_farming),
  },
  {
    label: 'How Many in Family Involved in Carabao Farming',
    value: props.farmer.how_many_in_family_are_involved_in_buffalo_farming,
  },
  {
    label: 'Membership in Organization',
    value: props.membershipSource
      ? label(props.enums.membershipType, props.membershipSource)
      : null,
  },
  { label: 'Name of Organization', value: props.organizationNameSource },
])
</script>

<template>
  <div>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <DetailCard title="Household and Family" :rows="householdAndFamilyRows" />
      <DetailCard title="Socio-Economic Profile" :rows="socioEconomicProfileRows" />
    </div>
    <div v-if="isEditMode" class="mt-4">
      <Button size="sm" variant="outline" @click="emit('edit', 'socio-economic')">
        <Pencil class="mr-2 h-4 w-4" />{{ actionLabel }} Socio-Economic
      </Button>
    </div>
  </div>
</template>
