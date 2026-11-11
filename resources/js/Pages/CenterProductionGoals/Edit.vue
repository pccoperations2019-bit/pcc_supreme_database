<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import GoalForm from './Partials/Form.vue'

defineProps({
  goal: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  availableYears: { type: Array, default: () => [] },
  programs: { type: Array, default: () => [] },
})
</script>

<template>
  <Head :title="`Edit Production Goal - ${goal.center?.name} ${goal.year}`" />
  <DashboardLayout>
    <GoalForm
      :goal="goal"
      :centers="centers"
      :available-years="availableYears"
      :programs="programs"
      mode="edit"
    />
  </DashboardLayout>
</template>
