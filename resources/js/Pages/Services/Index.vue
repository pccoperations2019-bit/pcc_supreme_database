<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import ServiceTable from './Partials/ServiceTable.vue'

defineProps({
  services: { type: Object, required: true },
  enums: { type: Object, required: true },
  filters: { type: Object, required: false },
  simplifiedCodes: { type: Array, required: false },
})
</script>

<template>
  <Head title="Services" />
  <DashboardLayout>
    <ServiceTable :services="services" :enums="enums" :simplified-codes="simplifiedCodes" />
  </DashboardLayout>
</template>
