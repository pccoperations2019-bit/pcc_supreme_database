<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import CCDPTable from './Partials/CCDPTable.vue'

defineProps({
  projectSites: { type: Object, required: true },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="CCDP Project Sites" />
  <DashboardLayout>
    <CCDPTable :project-sites="projectSites" :enums="enums" />
  </DashboardLayout>
</template>
