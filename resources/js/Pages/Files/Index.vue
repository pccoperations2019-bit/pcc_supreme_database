<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import FileTable from './Partials/FileTable.vue'
defineProps({
  files: { type: Object, required: true },
})
</script>
<template>
  <Head title="Files" />
  <DashboardLayout>
    <FileTable :files="files" />
  </DashboardLayout>
</template>
