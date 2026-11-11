<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import ProjectSiteForm from './Partials/Form.vue'

defineProps({
  projectSite: { type: Object, required: true },
  program: { type: String, default: '' },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  dairyBoxes: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Create Project Site" />
  <DashboardLayout>
    <div class="mb-3">
      <h1 class="text-3xl font-bold">New Project Site</h1>
      <p class="text-slate-600">Create a new project site.</p>
    </div>
    <ProjectSiteForm
      :project-site="projectSite"
      :program="program"
      :centers="centers"
      :partners="partners"
      :projects="projects"
      :dairy-boxes="dairyBoxes"
      :enums="enums"
      mode="create"
    />
  </DashboardLayout>
</template>
