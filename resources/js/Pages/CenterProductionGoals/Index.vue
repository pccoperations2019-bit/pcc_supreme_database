<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import GoalTable from './Partials/GoalTable.vue'

defineProps({
  goals: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  availableYears: { type: Array, default: () => [] },
  filters: { type: Object, required: false },
  programs: { type: Array, default: () => [] },
})
</script>

<template>
  <Head title="Center Production Goals" />
  <DashboardLayout>
    <GoalTable
      :goals="goals"
      :centers="centers"
      :available-years="availableYears"
      :programs="programs"
    />
  </DashboardLayout>
</template>
