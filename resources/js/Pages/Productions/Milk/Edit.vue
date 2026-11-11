<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import MilkProductionForm from './Partials/Form.vue'

defineProps({
  milkProduction: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  farmers: { type: Array, default: () => [] },
  herds: { type: Array, default: () => [] },
  enums: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head :title="`Edit Milk Production - ${milkProduction.id}`" />
  <DashboardLayout>
    <MilkProductionForm
      mode="edit"
      :milk-production="milkProduction"
      :centers="centers"
      :partners="partners"
      :farmers="farmers"
      :herds="herds"
      :enums="enums"
    />
  </DashboardLayout>
</template>
