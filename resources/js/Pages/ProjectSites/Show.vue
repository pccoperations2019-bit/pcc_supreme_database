<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import ProjectSiteForm from './Partials/Form.vue'

defineProps({
  projectSite: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
  dairyBoxes: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head :title="`Project Site - ${projectSite.name}`" />
  <DashboardLayout>
    <ProjectSiteForm
      mode="view"
      :project-site="projectSite"
      :centers="centers"
      :partners="partners"
      :projects="projects"
      :products="products"
      :dairy-boxes="dairyBoxes"
      :enums="enums"
    />
  </DashboardLayout>
</template>
