<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import ManureSalesTable from './Partials/ManureSalesTable.vue'

defineProps({
  manureSales: {
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
  <Head title="Manure Sales" />

  <DashboardLayout>
    <ManureSalesTable
      :manure-sales="manureSales"
      :centers="centers"
      :years="years"
      :default-year="defaultYear"
    />
  </DashboardLayout>
</template>
