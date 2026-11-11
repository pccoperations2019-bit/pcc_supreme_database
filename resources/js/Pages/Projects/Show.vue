<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import ProjectForm from './Partials/Form.vue'

defineProps({
  project: { type: Object, required: true },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head :title="`Project - ${project.name}`" />
  <DashboardLayout>
    <ProjectForm mode="view" :project="project" :enums="enums" />
  </DashboardLayout>
</template>
