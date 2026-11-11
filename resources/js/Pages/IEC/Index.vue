<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import IECTable from './Partials/IECTable.vue'

defineProps({
  serviceRecords: { type: Object, required: true },
  enums: { type: Object, required: true },
  filters: { type: Object, required: false },
  simplifiedCodes: { type: Array, required: false },
})
</script>

<template>
  <Head title="Information, Education, and Communication" />
  <DashboardLayout>
    <IECTable
      :service-records="serviceRecords"
      :enums="enums"
      :simplified-codes="simplifiedCodes"
    />
  </DashboardLayout>
</template>
