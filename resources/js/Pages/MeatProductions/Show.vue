<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  meatProduction: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head :title="`Meat Production - ${meatProduction.id}`" />
  <DashboardLayout>
    <Form mode="view" :meat-production="meatProduction" :enums="enums" />
  </DashboardLayout>
</template>
