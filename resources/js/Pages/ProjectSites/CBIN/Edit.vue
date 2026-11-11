<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import CBINForm from './Partials/Form.vue'

defineProps({
  projectSite: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
  partners: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  dairyBoxes: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Edit CBIN Project Site" />
  <DashboardLayout>
    <div class="mb-3">
      <h1 class="text-3xl font-bold">Edit CBIN Project Site</h1>
      <p class="text-slate-600">Update the CBIN project site information.</p>
    </div>
    <CBINForm
      :project-site="projectSite"
      :centers="centers"
      :default-center-id="defaultCenterId"
      :disable-center-field="disableCenterField"
      :partners="partners"
      :projects="projects"
      :dairy-boxes="dairyBoxes"
      :enums="enums"
      mode="edit"
    />
  </DashboardLayout>
</template>
