<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import GoalForm from './Partials/Form.vue'

defineProps({
  centers: { type: Array, default: () => [] },
  defaultCenterId: { type: Number, default: null },
  currentYear: { type: Number, default: () => new Date().getFullYear() },
  availableYears: { type: Array, default: () => [] },
  programs: { type: Array, default: () => [] },
})
</script>

<template>
  <Head title="Create Production Goal" />
  <DashboardLayout>
    <GoalForm
      :centers="centers"
      :default-center-id="defaultCenterId"
      :current-year="currentYear"
      :available-years="availableYears"
      :programs="programs"
      mode="create"
    />
  </DashboardLayout>
</template>
