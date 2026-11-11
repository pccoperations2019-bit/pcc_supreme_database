<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  manureProduction: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head :title="`Manure Production - ${manureProduction.id}`" />
  <DashboardLayout>
    <Form mode="view" :manure-production="manureProduction" :enums="enums" />
  </DashboardLayout>
</template>
