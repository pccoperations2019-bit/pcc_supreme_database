<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import ManureProductionTable from './Partials/ManureProductionTable.vue'

defineProps({
  manureProductions: {
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
})
</script>

<template>
  <Head title="Manure Production" />

  <DashboardLayout>
    <ManureProductionTable
      :manure-productions="manureProductions"
      :centers="centers"
      :years="years"
    />
  </DashboardLayout>
</template>
