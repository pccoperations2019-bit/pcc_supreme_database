<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  milkFeedingProgram: { type: Object, required: true },
  centers: { type: Array, required: true },
  partners: { type: Array, required: true },
  defaultProjectId: { type: Number, default: null },
  center: { type: Object, default: null },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Create Milk Feeding Program" />
  <DashboardLayout>
    <Form
      mode="create"
      :milk-feeding-program="milkFeedingProgram"
      :centers="centers"
      :partners="partners"
      :default-project-id="defaultProjectId"
      :center="center"
      :default-center-id="defaultCenterId"
      :disable-center-field="disableCenterField"
      :enums="enums"
    />
  </DashboardLayout>
</template>
