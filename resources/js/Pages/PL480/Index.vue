<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import PL480Table from './Partials/PL480Table.vue'

defineProps({
  provinces: { type: Array, required: true },
  years: { type: Array, required: true },
  inputData: { type: Object, required: true },
  computedData: { type: Object, required: true },
})
</script>
<template>
  <Head title="PL480" />
  <DashboardLayout>
    <PL480Table
      :provinces="provinces"
      :years="years"
      :input-data="inputData"
      :computed-data="computedData"
    />
  </DashboardLayout>
</template>
