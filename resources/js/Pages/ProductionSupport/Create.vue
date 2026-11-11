<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  serviceRecord: { type: Object, required: true },
  service: { type: Object, default: null },
  centers: { type: Array, required: true },
  farmers: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="New Production Support Service Record" />
  <DashboardLayout>
    <Form
      mode="create"
      :service-record="serviceRecord"
      :service="service"
      :centers="centers"
      :farmers="farmers"
      :projects="projects"
      :enums="enums"
    />
  </DashboardLayout>
</template>
