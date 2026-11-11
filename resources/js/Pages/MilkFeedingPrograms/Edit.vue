<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  milkFeedingProgram: { type: Object, required: true },
  centers: { type: Array, required: true },
  partners: { type: Array, required: true },
  defaultProjectId: { type: Number, default: null },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Edit Milk Feeding Program" />
  <DashboardLayout>
    <Form
      mode="edit"
      :milk-feeding-program="milkFeedingProgram"
      :centers="centers"
      :partners="partners"
      :default-project-id="defaultProjectId"
      :enums="enums"
    />
  </DashboardLayout>
</template>
