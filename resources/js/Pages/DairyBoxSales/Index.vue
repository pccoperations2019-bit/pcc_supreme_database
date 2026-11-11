<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import DairyBoxSalesTable from './Partials/DairyBoxSalesTable.vue'

defineProps({
  dairyBoxSales: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  years: { type: Array, default: () => [] },
})
</script>

<template>
  <Head title="Dairy Box Sales" />
  <DashboardLayout>
    <DairyBoxSalesTable
      :dairy-box-sales="dairyBoxSales"
      :centers="centers"
      :partners="partners"
      :years="years"
    />
  </DashboardLayout>
</template>
