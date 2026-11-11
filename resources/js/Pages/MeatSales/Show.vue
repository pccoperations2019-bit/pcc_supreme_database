<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  meatSale: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head :title="`Meat Sales - ${meatSale.id}`" />
  <DashboardLayout>
    <Form mode="view" :meat-sale="meatSale" :enums="enums" />
  </DashboardLayout>
</template>
