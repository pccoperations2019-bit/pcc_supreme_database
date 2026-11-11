<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import GoalSalesTable from './Partials/GoalSalesTable.vue'

defineProps({
  goals: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  availableYears: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  programs: { type: Array, default: () => [] },
})
</script>

<template>
  <Head title="Center Sales Goals" />
  <DashboardLayout>
    <GoalSalesTable
      :goals="goals"
      :centers="centers"
      :available-years="availableYears"
      :filters="filters"
      :programs="programs"
    />
  </DashboardLayout>
</template>
