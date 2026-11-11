<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import CCDPForm from './Partials/Form.vue'

defineProps({
  projectSite: { type: Object, required: true },
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
  <Head title="Edit CCDP Project Site" />
  <DashboardLayout>
    <div class="mb-3">
      <h1 class="text-3xl font-bold">Edit CCDP Project Site</h1>
      <p class="text-slate-600">Update the CCDP project site information.</p>
    </div>
    <CCDPForm
      :project-site="projectSite"
      :centers="centers"
      :default-center-id="defaultCenterId"
      :disable-center-field="disableCenterField"
      :partners="partners"
      :projects="projects"
      :dairy-boxes="dairyBoxes"
      :farmer-orgs="farmerOrgs"
      :enums="enums"
      mode="edit"
    />
  </DashboardLayout>
</template>
