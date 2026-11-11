<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import DairyBoxTable from './Partials/DairyBoxTable.vue'

defineProps({
  dairyBoxes: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] },
  filters: { type: Object, required: false },
})
</script>

<template>
  <Head title="Dairy Boxes" />
  <DashboardLayout>
    <DairyBoxTable
      :dairy-boxes="dairyBoxes"
      :centers="centers"
      :partners="partners"
      :statuses="statuses"
    />
  </DashboardLayout>
</template>
