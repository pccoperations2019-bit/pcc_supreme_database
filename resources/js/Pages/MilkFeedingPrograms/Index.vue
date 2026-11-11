<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import MilkFeedingProgramTable from './Partials/MilkFeedingProgramTable.vue'

defineProps({
  milkFeedingPrograms: { type: Object, required: true },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Milk Feeding Programs" />
  <DashboardLayout>
    <MilkFeedingProgramTable :milk-feeding-programs="milkFeedingPrograms" :enums="enums" />
  </DashboardLayout>
</template>
