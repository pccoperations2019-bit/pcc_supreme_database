<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import MilkSaleTable from '../Partials/MilkSaleTable.vue'

defineProps({
  milkSales: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  years: { type: Array, default: () => [] },
  defaultYear: { type: Number, default: null },
})
</script>

<template>
  <Head title="Milk Sales" />
  <DashboardLayout>
    <MilkSaleTable
      :milk-sales="milkSales"
      :centers="centers"
      :years="years"
      :default-year="defaultYear"
    />
  </DashboardLayout>
</template>
