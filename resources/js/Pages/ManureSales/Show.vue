<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  manureSale: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head :title="`Manure Sales - ${manureSale.id}`" />
  <DashboardLayout>
    <Form mode="view" :manure-sale="manureSale" :enums="enums" />
  </DashboardLayout>
</template>
