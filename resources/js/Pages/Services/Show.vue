<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  service: { type: Object, required: true },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Service Details" />
  <DashboardLayout>
    <Form mode="view" :service="service" :enums="enums" />
  </DashboardLayout>
</template>
