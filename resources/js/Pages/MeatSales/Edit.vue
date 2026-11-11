<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  meatSale: { type: Object, required: true },
  centers: { type: Array, required: true },
  defaultProjectId: { type: Number, default: null },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Edit Meat Sales" />
  <DashboardLayout>
    <Form
      mode="edit"
      :meat-sale="meatSale"
      :centers="centers"
      :default-project-id="defaultProjectId"
      :enums="enums"
    />
  </DashboardLayout>
</template>
