<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import MilkTradedTable from '../Partials/MilkTradedTable.vue'

defineProps({
  milkTraders: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
})
</script>

<template>
  <Head title="Milk Traded" />
  <DashboardLayout>
    <MilkTradedTable :milk-traders="milkTraders" :centers="centers" :partners="partners" />
  </DashboardLayout>
</template>
