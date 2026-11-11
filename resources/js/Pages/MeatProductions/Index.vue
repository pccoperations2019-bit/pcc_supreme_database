<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import MeatProductionTable from './Partials/MeatProductionTable.vue'

defineProps({
  meatProductions: {
    type: Object,
    required: true,
  },
  centers: {
    type: Array,
    default: () => [],
  },
  years: {
    type: Array,
    default: () => [],
  },
  defaultYear: {
    type: Number,
    default: null,
  },
})
</script>

<template>
  <Head title="Meat Production" />

  <DashboardLayout>
    <MeatProductionTable
      :meat-productions="meatProductions"
      :centers="centers"
      :years="years"
      :default-year="defaultYear"
    />
  </DashboardLayout>
</template>
