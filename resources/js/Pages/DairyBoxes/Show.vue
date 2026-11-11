<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import DairyBoxForm from './Partials/Form.vue'

defineProps({
  dairyBox: { type: Object, required: true },
  projectInvolvements: { type: Array, default: () => [] },
  enums: { type: Object, default: () => ({}) },
  existingDocuments: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head :title="`Dairy Box - ${dairyBox.business_name || dairyBox.dairy_box_id}`" />
  <DashboardLayout>
    <DairyBoxForm
      mode="view"
      :dairy-box="dairyBox"
      :project-involvements="projectInvolvements"
      :enums="enums"
      :existing-documents="existingDocuments"
    />
  </DashboardLayout>
</template>
