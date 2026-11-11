<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import ProjectSiteForm from './Partials/Form.vue'

defineProps({
  projectSite: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  dairyBoxes: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Edit Project Site" />
  <DashboardLayout>
    <div class="mb-3">
      <h1 class="text-3xl font-bold">Edit Project Site</h1>
      <p class="text-slate-600">Update the project site information.</p>
    </div>
    <ProjectSiteForm
      :project-site="projectSite"
      :centers="centers"
      :partners="partners"
      :projects="projects"
      :dairy-boxes="dairyBoxes"
      :enums="enums"
      mode="edit"
    />
  </DashboardLayout>
</template>
