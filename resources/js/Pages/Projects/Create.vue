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
  <Head title="Create Project" />
  <DashboardLayout>
    <div class="mb-3">
      <h1 class="text-3xl font-bold">New Project</h1>
      <p class="text-slate-600">Create a new project.</p>
    </div>
    <ProjectForm :project="project" :enums="enums" mode="create" />
  </DashboardLayout>
</template>
