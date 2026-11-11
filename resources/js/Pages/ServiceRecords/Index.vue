<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import ServiceRecordsTable from './Partials/ServiceRecordsTable.vue'
import FiltersPanel from '@/components/FiltersPanel.vue'

defineProps({
  serviceRecords: { type: Object, required: true },
  centers: { type: Array, required: true },
  services: { type: Array, required: true },
  farmers: { type: Array, required: true },
  enums: { type: Object, required: true },
  filters: { type: Object, required: false },
})
</script>

<template>
  <Head title="Service Records" />
  <DashboardLayout>
    <ServiceRecordsTable
      :service-records="serviceRecords"
      :centers="centers"
      :services="services"
      :farmers="farmers"
      :enums="enums"
    />
  </DashboardLayout>
</template>
