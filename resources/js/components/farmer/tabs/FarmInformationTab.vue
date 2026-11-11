<script setup>
import { computed, defineProps, defineEmits } from 'vue'
import { Pencil } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import DetailCard from '@/components/DetailCard.vue'
import { ANIMAL_OWNERSHIP_TYPES } from '@/composable/useFarmerForm'

const props = defineProps({
  farmer: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
  isEditMode: { type: Boolean, default: false },
  actionLabel: { type: String, default: 'Edit' },
  additionalDetails: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['edit'])

const farmDetailRows = computed(() => [
  { label: 'Farm Name:', value: props.farmer.farm_name },
  { label: 'Proposed Farm Name:', value: props.farmer.proposed_farm_name },
  { label: 'Zone/Street/Sitio/Subdivision:', value: props.farmer.farm_street },
  { label: 'Farm Purok/Zone:', value: props.farmer.farm_purok_zone },
  { label: 'Farm Barangay:', value: props.farmer.farm_barangay },
  { label: 'Farm Municipality/City:', value: props.farmer.farm_municipality_city },
  {
    label: 'Farm District:',
    value: props.enums.addressDistrict?.[props.farmer.farm_district] || props.farmer.farm_district,
  },
  { label: 'Farm Province:', value: props.farmer.farm_province },
  {
    label: 'Farm Region:',
    value: props.enums.addressRegion?.[props.farmer.farm_region] || props.farmer.farm_region,
  },
  {
    label: 'Type of Farm Business Registration:',
    value: (props.farmer.type_of_farm_business_registration || [])
      .map(key => props.enums.farmBusinessRegistration?.[key] || key)
      .join(', '),
  },
  {
    label: 'Status of Registration:',
    value: props.farmer.status_of_registration
      ? props.enums.farmRegistrationStatus?.[props.farmer.status_of_registration] ||
        props.farmer.status_of_registration
      : null,
  },
  { label: 'Registration/Serial/Certification No.:', value: props.farmer.farm_registration_number },
  {
    label: 'RSBSA Registered:',
    value: props.farmer.has_rsbsa ? 'Yes' : props.farmer.has_rsbsa === false ? 'No' : null,
  },
  {
    label: 'RSBSA Registration No.:',
    value: props.farmer.rsbsa_certificate_of_registration,
  },
  { label: 'Year Dairy Carabao Farm Was Established:', value: props.farmer.year_farm_established },
])

const joinList = value => (Array.isArray(value) && value.length ? value.join(', ') : null)

const normValue = value =>
  Array.isArray(value) ? value.join(', ') : value === '' || value == null ? null : value

const forageRows = computed(() => {
  const f = props.additionalDetails.forage || {}
  const rows = [
    { label: 'Total Forage Area (ha):', value: f.total_area },
    { label: 'Forages Grown:', value: joinList(f.forages_grown) },
    { label: 'Other Forages:', value: f.forages_grown_others },
    { label: 'Type of Ownership:', value: joinList(f.ownership) },
    { label: 'Farm Type:', value: joinList(f.farm_type) },
    { label: 'Water Source:', value: joinList(f.water_source) },
    { label: 'Distance from House:', value: f.distance_from_house },
  ].filter(row => row.value !== null && row.value !== undefined && row.value !== '')
  return rows.length ? rows : [{ label: 'Forage Information:', value: 'N/A' }]
})

const cropRows = computed(() => {
  const c = props.additionalDetails.crop || {}
  const rows = [
    { label: 'Total Crop Area (ha):', value: c.total_area },
    { label: 'Crops Grown:', value: joinList(c.crops_grown) },
    { label: 'Other Crops:', value: c.crops_grown_others },
    { label: 'Type of Ownership:', value: joinList(c.ownership) },
    { label: 'Other Ownership:', value: c.ownership_others },
    { label: 'Farm Type:', value: joinList(c.farm_type) },
    { label: 'Water Source:', value: joinList(c.water_source) },
    { label: 'Distance from House:', value: c.distance_from_house },
  ].filter(row => row.value !== null && row.value !== undefined && row.value !== '')
  return rows.length ? rows : [{ label: 'Crop Information:', value: 'N/A' }]
})

const animalShedRows = computed(() => {
  const s = props.additionalDetails.animal_shed || {}
  const norm = v => (v === '' || v == null ? null : v)
  const avail = Array.isArray(s.available)
    ? joinList(s.available)
    : s.available === true
      ? 'Available'
      : s.available === false
        ? 'Not available'
        : norm(s.available)
  return [
    { label: 'Availability:', value: avail },
    {
      label: 'Type of Roof:',
      value: normValue([...(s.roof || []), ...(s.roof_other ? [s.roof_other] : [])]),
    },
    {
      label: 'Type of Posts:',
      value: normValue([...(s.posts || []), ...(s.posts_other ? [s.posts_other] : [])]),
    },
    {
      label: 'Type of Flooring:',
      value: normValue([...(s.flooring || []), ...(s.flooring_other ? [s.flooring_other] : [])]),
    },
    { label: 'Type of Ownership:', value: joinList(s.ownership) },
    { label: 'Water Source:', value: joinList(s.water_source) },
    { label: 'Total Area of Animal Housing (sqm):', value: norm(s.total_area) },
    { label: 'Distance from House:', value: norm(s.distance_from_house) },
    { label: 'GPS Latitude:', value: norm(props.farmer.gps_latitude) },
    { label: 'GPS Longitude:', value: norm(props.farmer.gps_longitude) },
  ]
})

const farmOperationsRows = computed(() => [
  {
    label: 'Management System:',
    value:
      props.enums.managementSystem?.[props.farmer.management_system] ||
      props.farmer.management_system,
  },
  {
    label: 'Feeding Management:',
    value:
      props.enums.feedingManagement?.[props.farmer.feeding_management] ||
      props.farmer.feeding_management,
  },
])

const animalOwnershipRows = computed(() => {
  const ownership = props.farmer.animal_ownership || {}
  const rows = ANIMAL_OWNERSHIP_TYPES.filter(t => ownership[t.key]?.checked).map(t => {
    const count = ownership[t.key]?.count ?? ''
    const specify =
      t.hasSpecify && ownership[t.key]?.specify ? ` (${ownership[t.key].specify})` : ''
    return {
      label: `${t.label}:`,
      value: count === '' || count === null ? '0' : `${count}${specify}`,
    }
  })
  return rows.length ? rows : [{ label: 'Ownership Types:', value: 'N/A' }]
})

const milkProductionRows = computed(() => [
  {
    label: 'Twice-a-day Milking:',
    value: props.farmer.is_twice_a_day_milking
      ? 'Yes'
      : props.farmer.is_twice_a_day_milking === false
        ? 'No'
        : null,
  },
  {
    label: 'Daily Milk Production (min):',
    value: props.farmer.daily_milk_production_min,
  },
  {
    label: 'Daily Milk Production (max):',
    value: props.farmer.daily_milk_production_max,
  },
  {
    label: 'Milk Price per Liter (min):',
    value: props.farmer.milk_price_per_liter_min,
  },
  {
    label: 'Milk Price per Liter (max):',
    value: props.farmer.milk_price_per_liter_max,
  },
])

const SELLING_LOCATION_LABELS = {
  neighborhood: 'Within the neighborhood',
  we_process: 'We process it',
  cooperative: 'Cooperative',
  indian_nationality: 'Indian Nationality',
  restaurant: 'Known restaurant/establishment',
  private_processor: 'Private processor',
  independent_collector: 'Independent Milk Collector',
  others: 'Others',
}

const milkSellingRows = computed(() => {
  const locations = props.farmer.milk_selling_locations || []
  const specifics = [
    props.farmer.milk_selling_restaurant_specify,
    props.farmer.milk_selling_processor_specify,
    props.farmer.milk_selling_collector_name,
    props.farmer.milk_selling_others_specify,
  ].filter(v => v)
  const rows = []
  if (locations.length) {
    rows.push({
      label: 'Where Do You Sell Your Milk Produce?:',
      value: locations
        .map(key => SELLING_LOCATION_LABELS[key] || key)
        .filter(v => v)
        .join(', '),
    })
  }
  specifics.forEach((value, index) => {
    rows.push({ label: `Specify ${index + 1}:`, value })
  })
  return rows.length ? rows : [{ label: 'Where Do You Sell Your Milk Produce?:', value: 'N/A' }]
})

const assistanceRows = computed(() => {
  const lines = (props.farmer.assistance_needed || []).filter(v => v && String(v).trim() !== '')
  return lines.length
    ? lines.map((line, index) => ({ label: `${index + 1}:`, value: line }))
    : [{ label: 'Assistance Needed from Government:', value: 'N/A' }]
})
</script>

<template>
  <div>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <DetailCard title="Farm Details" :rows="farmDetailRows" />
      <DetailCard title="Forage Information" :rows="forageRows" />
      <DetailCard title="Crop Information" :rows="cropRows" />
      <DetailCard title="Animal Shed" :rows="animalShedRows" />
      <DetailCard title="Farm Operations" :rows="farmOperationsRows" />
      <DetailCard title="Number of Animals According to Ownership" :rows="animalOwnershipRows" />
      <DetailCard title="Milk Production & Price" :rows="milkProductionRows" />
      <DetailCard title="Milk Selling" :rows="milkSellingRows" />
      <DetailCard title="Assistance Needed from Government" :rows="assistanceRows" />
    </div>
    <div v-if="isEditMode" class="mt-4 flex flex-wrap gap-2">
      <Button size="sm" variant="outline" @click="emit('edit', 'farming-info')">
        <Pencil class="mr-2 h-4 w-4" />{{ actionLabel }} Farm Details
      </Button>
    </div>
  </div>
</template>
