<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import MilkProductionForm from './Partials/Form.vue'

defineProps({
  milkProduction: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head :title="`Milk Production - ${milkProduction.id}`" />
  <DashboardLayout>
    <MilkProductionForm mode="view" :milk-production="milkProduction" :enums="enums" />
  </DashboardLayout>
</template>
