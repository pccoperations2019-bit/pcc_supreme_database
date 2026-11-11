<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import SourceForm from './Partials/Form.vue'

defineProps({
  sourceNames: { type: Object, required: true },
  sourceLabels: { type: Object, required: true },
  source: { type: Object, required: true },
})
</script>

<template>
  <Head title="Edit Beneficiary Source" />
  <DashboardLayout>
    <SourceForm
      :source-names="sourceNames"
      :source-labels="sourceLabels"
      :source="source"
      mode="edit"
    />
  </DashboardLayout>
</template>
