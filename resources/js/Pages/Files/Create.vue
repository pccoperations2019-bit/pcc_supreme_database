<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import FileUploadForm from './Partials/Form.vue'
</script>
<template>
  <Head title="Upload File" />
  <DashboardLayout>
    <FileUploadForm />
  </DashboardLayout>
</template>
