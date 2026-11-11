<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import ServiceRecordForm from './Partials/Form.vue'

defineProps({
  serviceRecord: { type: Object, required: true },
  centers: { type: Array, required: true },
  services: { type: Array, required: true },
  farmers: { type: Array, required: true },
  projects: { type: Array, default: () => [] },
  indexUrl: { type: String, default: '' },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Edit Service Record" />
  <DashboardLayout>
    <div class="mb-3">
      <h1 class="text-3xl font-bold">Edit Service Record</h1>
      <p class="text-slate-600">Update the service record information.</p>
    </div>
    <ServiceRecordForm
      :service-record="serviceRecord"
      :centers="centers"
      :services="services"
      :farmers="farmers"
      :projects="projects"
      :index-url="indexUrl"
      :enums="enums"
      mode="edit"
    />
  </DashboardLayout>
</template>
