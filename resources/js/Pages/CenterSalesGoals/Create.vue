<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  centers: { type: Array, default: () => [] },
  currentYear: { type: Number, default: () => new Date().getFullYear() },
  availableYears: { type: Array, default: () => [] },
  programs: { type: Array, default: () => [] },
})
</script>

<template>
  <Head title="Create Sales Goal" />
  <DashboardLayout>
    <Form
      :centers="centers"
      :current-year="currentYear"
      :available-years="availableYears"
      :programs="programs"
      mode="create"
    />
  </DashboardLayout>
</template>
