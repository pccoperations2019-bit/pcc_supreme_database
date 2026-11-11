<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import CCDPForm from './Partials/Form.vue'

defineProps({
  projectSite: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
  dairyBoxes: { type: Array, default: () => [] },
  farmerOrgs: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head :title="`CCDP - ${projectSite.name}`" />
  <DashboardLayout>
    <CCDPForm
      mode="view"
      :project-site="projectSite"
      :centers="centers"
      :partners="partners"
      :projects="projects"
      :products="products"
      :dairy-boxes="dairyBoxes"
      :farmer-orgs="farmerOrgs"
      :enums="enums"
    />
  </DashboardLayout>
</template>
