<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  dairyBoxSale: { type: Object, required: true },
  dairyBoxes: { type: Array, required: true },
  centers: { type: Array, required: true },
  partners: { type: Array, required: true },
})
</script>

<template>
  <Head title="Edit Dairy Box Sale" />
  <DashboardLayout>
    <Form
      mode="edit"
      :dairy-box-sale="dairyBoxSale"
      :dairy-boxes="dairyBoxes"
      :centers="centers"
      :partners="partners"
    />
  </DashboardLayout>
</template>
