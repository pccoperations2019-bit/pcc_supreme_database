<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'

defineProps({
  monthlyTask: { type: Object, required: true },
  centers: { type: Array, required: true },
  enums: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head :title="`Monthly Task - ${monthlyTask.id}`" />
  <DashboardLayout>
    <Form mode="view" :monthly-task="monthlyTask" :centers="centers" :enums="enums" />
  </DashboardLayout>
</template>
