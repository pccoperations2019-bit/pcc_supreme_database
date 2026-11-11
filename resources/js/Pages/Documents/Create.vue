<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import DocumentUploadForm from './Partials/Form.vue'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
defineProps({
  tagOptions: {
    type: Object,
    required: true,
  },
  templatesRequiringProgram: {
    type: Array,
    default: () => [],
  },
  centerTagValue: {
    type: String,
    default: null,
  },
  allowedFileTypes: {
    type: Object,
    required: false,
    default: () => ({
      extensions: ['pdf', 'xlsx', 'xls', 'csv'],
      accept: '.pdf,.xlsx,.xls,.csv',
      acceptLabel: 'PDF, Excel (.xlsx, .xls) or CSV',
      maxSizeMb: 20,
    }),
  },
})
</script>
<template>
  <Head title="Upload Document" />
  <DashboardLayout>
    <div class="mx-auto max-w-full px-4">
      <Card class="bg-white text-black shadow-sm">
        <CardHeader>
          <CardTitle>Upload Document</CardTitle>
          <CardDescription>Attach and describe a new document for your records.</CardDescription>
        </CardHeader>
        <CardContent>
          <DocumentUploadForm
            :tag-options="tagOptions"
            :templates-requiring-program="templatesRequiringProgram"
            :center-tag-value="centerTagValue"
            :allowed-file-types="allowedFileTypes"
          />
        </CardContent>
      </Card>
    </div>
  </DashboardLayout>
</template>
