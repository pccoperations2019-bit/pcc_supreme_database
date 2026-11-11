<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import MeatSalesTable from './Partials/MeatSalesTable.vue'

defineProps({
  meatSales: {
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
  <Head title="Meat Sales" />

  <DashboardLayout>
    <MeatSalesTable
      :meat-sales="meatSales"
      :centers="centers"
      :years="years"
      :default-year="defaultYear"
    />
  </DashboardLayout>
</template>
