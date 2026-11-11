<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import ProjectTable from './Partials/ProjectTable.vue'

defineProps({
  projects: { type: Object, required: true },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Projects" />
  <DashboardLayout>
    <ProjectTable :projects="projects" :enums="enums" />
  </DashboardLayout>
</template>
