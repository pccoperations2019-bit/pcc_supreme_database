<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import AuditTable from './Partials/AuditTable.vue'

defineProps({
  activities: { type: Object, required: true },
  filters: { type: Object, required: false, default: () => ({}) },
})
</script>

<template>
  <Head title="Audit Trail" />
  <DashboardLayout>
    <AuditTable :activities="activities" :filters="filters" />
  </DashboardLayout>
</template>
