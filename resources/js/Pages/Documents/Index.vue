<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, usePoll, router } from '@inertiajs/vue3'
import { ref, onBeforeUnmount } from 'vue'
import DocumentTable from './Partials/DocumentTable.vue'

const { documents, enums, documentPermissions } = defineProps({
  documents: { type: Object, required: true },
  enums: { type: Object, required: false, default: () => ({}) },
  documentPermissions: { type: Object, required: false, default: () => ({}) },
})
usePoll(2000) // Poll every 10 seconds
</script>

<template>
  <Head title="Documents" />
  <DashboardLayout>
    <DocumentTable
      :documents="documents"
      :enums="enums"
      :document-permissions="documentPermissions"
    />
  </DashboardLayout>
</template>
