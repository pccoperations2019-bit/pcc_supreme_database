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
  defaultCenterId: { type: Number, default: null },
})
</script>

<template>
  <Head title="Create Milk Production" />
  <DashboardLayout>
    <MilkProductionForm
      mode="create"
      :milk-production="milkProduction"
      :centers="centers"
      :partners="partners"
      :farmers="farmers"
      :herds="herds"
      :enums="enums"
      :default-center-id="defaultCenterId"
    />
  </DashboardLayout>
</template>
