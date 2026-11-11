<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import MilkFeedingProgramForm from './Partials/Form.vue'

defineProps({
  milkFeedingProgram: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Head :title="`Milk Feeding Program - ${milkFeedingProgram.id}`" />
  <DashboardLayout>
    <MilkFeedingProgramForm mode="view" :milk-feeding-program="milkFeedingProgram" :enums="enums" />
  </DashboardLayout>
</template>
