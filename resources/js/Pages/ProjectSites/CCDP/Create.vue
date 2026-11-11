<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import CCDPForm from './Partials/Form.vue'

defineProps({
  projectSite: { type: Object, required: true },
  program: { type: String, default: '' },
  centers: { type: Array, default: () => [] },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
  partners: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  dairyBoxes: { type: Array, default: () => [] },
  farmerOrgs: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Create CCDP Project Site" />
  <DashboardLayout>
    <CCDPForm
      :project-site="projectSite"
      :program="program"
      :centers="centers"
      :default-center-id="defaultCenterId"
      :disable-center-field="disableCenterField"
      :partners="partners"
      :projects="projects"
      :dairy-boxes="dairyBoxes"
      :farmer-orgs="farmerOrgs"
      :enums="enums"
      mode="create"
    />
  </DashboardLayout>
</template>
