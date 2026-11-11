<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import SourceForm from './Partials/Form.vue'

defineProps({
  sourceNames: { type: Object, required: true },
  sourceLabels: { type: Object, required: true },
  currentYear: { type: Number, default: () => new Date().getFullYear() },
})
</script>

<template>
  <Head title="Create Beneficiary Source" />
  <DashboardLayout>
    <SourceForm
      :source-names="sourceNames"
      :source-labels="sourceLabels"
      :current-year="currentYear"
      mode="create"
    />
  </DashboardLayout>
</template>
