<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import ProductTable from './Partials/ProductTable.vue'

defineProps({
  products: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head title="Products" />
  <DashboardLayout>
    <ProductTable :products="products" :enums="enums" />
  </DashboardLayout>
</template>
