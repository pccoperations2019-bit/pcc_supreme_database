<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import DairyBoxForm from './Partials/Form.vue'

defineProps({
  dairyBox: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  projectInvolvements: { type: Array, default: () => [] },
  enums: { type: Object, default: () => ({}) },
  existingDocuments: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head :title="`Edit Dairy Box - ${dairyBox.business_name || dairyBox.dairy_box_id}`" />
  <DashboardLayout>
    <DairyBoxForm
      mode="edit"
      :dairy-box="dairyBox"
      :centers="centers"
      :partners="partners"
      :project-involvements="projectInvolvements"
      :enums="enums"
      :existing-documents="existingDocuments"
    />
  </DashboardLayout>
</template>
