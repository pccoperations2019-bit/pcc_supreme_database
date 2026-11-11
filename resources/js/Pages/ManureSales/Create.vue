<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  manureSale: { type: Object, required: true },
  centers: { type: Array, required: true },
  defaultProjectId: { type: Number, default: null },
  center: { type: Object, default: null },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Create Manure Sales" />
  <DashboardLayout>
    <Form
      mode="create"
      :manure-sale="manureSale"
      :centers="centers"
      :default-project-id="defaultProjectId"
      :center="center"
      :default-center-id="defaultCenterId"
      :disable-center-field="disableCenterField"
      :enums="enums"
    />
  </DashboardLayout>
</template>
