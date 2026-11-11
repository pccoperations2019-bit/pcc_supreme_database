<script setup>
import SharedGoalTable from '@/Pages/CenterProductionGoals/Partials/GoalTable.vue'

defineProps({
  goals: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  availableYears: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  programs: { type: Array, default: () => [] },
})
</script>

<template>
  <SharedGoalTable
    :goals="goals"
    :centers="centers"
    :available-years="availableYears"
    :filters="filters"
    :programs="programs"
    force-mode="sales"
  />
</template>
