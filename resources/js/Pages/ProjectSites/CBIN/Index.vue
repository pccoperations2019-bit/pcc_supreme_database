<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import CBINTable from './Partials/CBINTable.vue'

defineProps({
  projectSites: { type: Object, required: true },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="CBIN Project Sites" />
  <DashboardLayout>
    <CBINTable :project-sites="projectSites" :enums="enums" />
  </DashboardLayout>
</template>
