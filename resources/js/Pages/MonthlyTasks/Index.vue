<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import MonthlyTaskTable from './Partials/MonthlyTaskTable.vue'

defineProps({
  tasks: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  summary: { type: Object, default: () => ({}) },
  entryRoutes: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head title="Monthly Tasks" />
  <DashboardLayout>
    <MonthlyTaskTable :tasks="tasks" :centers="centers" :filters="filters" :summary="summary" :entryRoutes="entryRoutes" />
  </DashboardLayout>
</template>
