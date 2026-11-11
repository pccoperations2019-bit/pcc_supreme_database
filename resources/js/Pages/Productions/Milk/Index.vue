<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import MilkProductionTable from './Partials/MilkProductionTable.vue'
defineProps({
  milkProductions: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  years: { type: Array, default: () => [] },
})
</script>
<template>
  <Head title="Milk Production" />
  <DashboardLayout>
    <MilkProductionTable
      :milk-productions="milkProductions"
      :centers="centers"
      :partners="partners"
      :years="years"
    />
  </DashboardLayout>
</template>
