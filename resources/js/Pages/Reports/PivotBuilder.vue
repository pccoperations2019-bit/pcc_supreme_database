<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import CustomPivotBuilder from '@/components/CustomPivotBuilder.vue'
import { Head } from '@inertiajs/vue3'

defineProps({
  sources: {
    type: Array,
    default: () => [],
  },

  initialSource: {
    type: String,
    default: 'center',
  },
})
</script>

<template>
  <Head title="Random Generate Report" />

  <DashboardLayout>
    <CustomPivotBuilder :sources="sources" :initial-source="initialSource" />
  </DashboardLayout>
</template>
