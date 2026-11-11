<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  dairyBoxSale: { type: Object, required: true },
})
</script>

<template>
  <Head :title="`Dairy Box Sale - ${dairyBoxSale.id}`" />
  <DashboardLayout>
    <Form mode="view" :dairy-box-sale="dairyBoxSale" />
  </DashboardLayout>
</template>
