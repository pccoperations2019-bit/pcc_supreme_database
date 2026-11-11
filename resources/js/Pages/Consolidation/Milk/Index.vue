<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import MilkConsolidationTable from './Partials/MilkConsolidationTable.vue'

defineProps({
  milkConsolidations: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head title="Milk Consolidation" />
  <DashboardLayout>
    <MilkConsolidationTable :milk-consolidations="milkConsolidations" :filters="filters" />
  </DashboardLayout>
</template>
