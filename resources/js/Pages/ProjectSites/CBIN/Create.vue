<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import CBINForm from './Partials/Form.vue'

defineProps({
  projectSite: { type: Object, required: true },
  program: { type: String, default: '' },
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
  <Head title="Create CBIN Project Site" />
  <DashboardLayout>
    <CBINForm
      :project-site="projectSite"
      :program="program"
      :centers="centers"
      :default-center-id="defaultCenterId"
      :disable-center-field="disableCenterField"
      :partners="partners"
      :projects="projects"
      :dairy-boxes="dairyBoxes"
      :enums="enums"
      mode="create"
    />
  </DashboardLayout>
</template>
