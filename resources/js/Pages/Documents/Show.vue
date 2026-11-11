<script setup>
import { computed, ref } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Icon } from '@iconify/vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { formatDate, formatDateTime } from '@/lib/utils'
import {
  documentStatusBadgeClass,
  documentStatusLabels,
  isEditableByUploader,
} from '@/lib/document'
import DocumentReviewDialog from '@/components/DocumentReviewDialog.vue'
import DocumentDetailFields from '@/components/DocumentDetailFields.vue'

const props = defineProps({
  document: { type: Object, required: true },
  details: { type: Object, required: false, default: () => ({}) },
  documentPermissions: { type: Object, required: false, default: () => ({}) },
})

const status = computed(() => props.document.status?.value || props.document.status || 'draft')
const canReviewCenter = computed(() => props.documentPermissions?.can_review_center === true)
const canReviewAgency = computed(() => props.documentPermissions?.can_review_agency === true)
// Keyed on the reviewer *role*, not the can_review permission: a PCC
// Administrator reviews documents but uploads are auto-submitted like center admins.
const canEdit = computed(
  () => props.documentPermissions?.can_edit === true && isEditableByUploader(status.value)
)

const reviewDialog = ref({ open: false, mode: 'approve', stage: 'center' })
const openReview = mode => {
  reviewDialog.value = {
    open: true,
    mode,
    stage: status.value === 'pending_agency_review' ? 'agency' : 'center',
  }
}

const submitForReview = () => {
  router.post(route('documents.submit', props.document.id), {}, { preserveScroll: true })
}

const deleteDocument = () => {
  if (confirm(`Are you sure you want to delete "${props.document.title}"?`)) {
    router.delete(route('documents.destroy', props.document.id))
  }
}
</script>

<template>
  <Head :title="document.title" />

  <DashboardLayout>
    <div class="mx-auto max-w-4xl space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <Link :href="route('documents.index')" class="text-gray-500 hover:text-gray-700">
            <Icon icon="lucide:arrow-left" class="h-5 w-5" />
          </Link>
          <div>
            <h1 class="text-2xl font-bold text-gray-900">
              {{ document.title }}
            </h1>
            <p v-if="document.description" class="mt-1 text-sm text-gray-600">
              {{ document.description }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <Badge variant="outline" :class="`${documentStatusBadgeClass(status)} border`">
            {{ documentStatusLabels[status] || status }}
          </Badge>

          <template v-if="canReviewCenter && status === 'pending_center_review'">
            <Button
              variant="outline"
              class="border-emerald-300 text-emerald-700 hover:border-emerald-400 hover:text-emerald-800"
              @click="openReview('approve')"
            >
              <Icon icon="lucide:check" class="mr-2 h-4 w-4" />
              Approve Document
            </Button>
            <Button
              variant="outline"
              class="border-red-300 text-red-600 hover:border-red-400 hover:text-red-700"
              @click="openReview('reject')"
            >
              <Icon icon="lucide:x" class="mr-2 h-4 w-4" />
              Reject Document
            </Button>
          </template>

          <template v-if="canEdit">
            <Link
              :href="route('documents.edit', document.id)"
              as="button"
              class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
            >
              <Icon icon="lucide:pencil" class="mr-2 h-4 w-4" />
              Edit
            </Link>
          </template>

          <Button v-if="canEdit" @click="submitForReview">
            <Icon icon="lucide:send" class="mr-2 h-4 w-4" />
            {{
              ['rejected', 'rejected_by_center', 'rejected_by_agency'].includes(status)
                ? 'Resubmit for Review'
                : 'Submit for Review'
            }}
          </Button>

          <Button
            v-if="documentPermissions.can_delete === true"
            variant="outline"
            class="border-red-300 text-red-600 hover:border-red-400 hover:text-red-700"
            @click="deleteDocument"
          >
            <Icon icon="lucide:trash-2" class="mr-2 h-4 w-4" />
            Delete
          </Button>
        </div>
      </div>

      <div v-if="canReviewAgency && status === 'pending_agency_review'" class="flex gap-2">
        <Button
          variant="outline"
          class="border-emerald-300 text-emerald-700 hover:border-emerald-400 hover:text-emerald-800"
          @click="openReview('approve')"
        >
          <Icon icon="lucide:check" class="mr-2 h-4 w-4" />
          Approve and Process
        </Button>
        <Button
          variant="outline"
          class="border-red-300 text-red-600 hover:border-red-400 hover:text-red-700"
          @click="openReview('reject')"
        >
          <Icon icon="lucide:x" class="mr-2 h-4 w-4" />
          Reject
        </Button>
      </div>

      <!-- Review outcome: why it came back, or who approved it -->
      <Card
        v-if="
          ['rejected', 'rejected_by_center', 'rejected_by_agency'].includes(status) &&
          document.rejection_reason
        "
        class="border-red-200 bg-red-50"
      >
        <CardHeader>
          <CardTitle class="flex items-center gap-2 text-red-800">
            <Icon icon="lucide:circle-alert" class="h-5 w-5" />
            Changes Requested
          </CardTitle>
          <CardDescription class="text-red-700">
            Sent back by {{ document.reviewed_by?.name || 'a PCC admin' }} on
            {{ formatDateTime(document.reviewed_at) }}.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p class="text-sm whitespace-pre-line text-red-900">
            {{ document.rejection_reason }}
          </p>
        </CardContent>
      </Card>

      <Card v-if="['approved', 'processing', 'processed'].includes(status)">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Icon icon="lucide:badge-check" class="h-5 w-5 text-emerald-600" />
            Review
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-1 text-sm text-gray-700">
          <p>
            Approved by {{ document.reviewed_by?.name || 'a PCC admin' }} on
            {{ formatDateTime(document.reviewed_at) }}.
          </p>
          <p v-if="document.submitted_at" class="text-gray-500">
            Submitted for review on {{ formatDateTime(document.submitted_at) }}.
          </p>
        </CardContent>
      </Card>

      <!-- Audit trail: every workflow transition, from the activity log -->
      <Card v-if="document.activities?.length">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Icon icon="lucide:history" class="h-5 w-5" />
            Activity
          </CardTitle>
          <CardDescription>
            A record of everything that has happened to this document.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ol class="space-y-4">
            <li v-for="activity in document.activities" :key="activity.id" class="flex gap-3">
              <div class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-500" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900">
                  {{ activity.description }}
                </p>
                <p class="text-xs text-gray-500">
                  {{ activity.causer?.name || 'System' }}
                  &middot; {{ formatDateTime(activity.created_at) }}
                </p>
                <p
                  v-if="activity.properties?.rejection_reason"
                  class="mt-1 rounded-md bg-red-50 p-2 text-sm whitespace-pre-line text-red-800"
                >
                  {{ activity.properties.rejection_reason }}
                </p>
              </div>
            </li>
          </ol>
        </CardContent>
      </Card>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Content -->
        <div class="space-y-6 lg:col-span-2">
          <!-- File Information -->
          <Card v-if="document.file">
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Icon icon="lucide:file" class="h-5 w-5" />
                File Information
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4">
                <div class="flex items-center gap-3">
                  <Icon icon="lucide:paperclip" class="h-6 w-6 text-gray-500" />
                  <div>
                    <p class="font-medium text-gray-900">
                      {{ document.file.name }}
                    </p>
                    <p class="text-sm text-gray-500">
                      {{ (document.file.size / 1024).toFixed(1) }} KB •
                      {{ document.file.type }}
                    </p>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <a
                    :href="document.file.path"
                    target="_blank"
                    class="inline-flex items-center rounded-md bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-100"
                  >
                    <Icon icon="lucide:external-link" class="mr-2 h-4 w-4" />
                    View
                  </a>
                  <a
                    :href="document.file.path"
                    download
                    class="inline-flex items-center rounded-md bg-gray-50 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100"
                  >
                    <Icon icon="lucide:download" class="mr-2 h-4 w-4" />
                    Download
                  </a>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Registration, validity, compliance and every tag, resolved -->
          <DocumentDetailFields :details="details" />

          <!-- Metadata -->
          <Card v-if="document.metadata">
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Icon icon="lucide:info" class="h-5 w-5" />
                Metadata
              </CardTitle>
            </CardHeader>
            <CardContent>
              <pre class="text-sm whitespace-pre-wrap text-gray-600">{{
                JSON.stringify(document.metadata, null, 2)
              }}</pre>
            </CardContent>
          </Card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Document Details -->
          <Card>
            <CardHeader>
              <CardTitle>Document Details</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div>
                <label class="text-sm font-medium text-gray-500">Created</label>
                <p class="text-sm text-gray-900">
                  {{ formatDate(document.created_at) }}
                </p>
              </div>

              <div>
                <label class="text-sm font-medium text-gray-500">Last Updated</label>
                <p class="text-sm text-gray-900">
                  {{ formatDate(document.updated_at) }}
                </p>
              </div>

              <div v-if="document.published_at">
                <label class="text-sm font-medium text-gray-500">Published</label>
                <p class="text-sm text-gray-900">
                  {{ formatDate(document.published_at) }}
                </p>
              </div>

              <div v-if="document.file && document.file.uploaded_by">
                <label class="text-sm font-medium text-gray-500">Uploaded By</label>
                <p class="text-sm text-gray-900">
                  {{ document.file.uploaded_by.name }}
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>

    <DocumentReviewDialog
      v-model:open="reviewDialog.open"
      :mode="reviewDialog.mode"
      :document-id="document.id"
      :document-title="document.title"
      :review-stage="reviewDialog.stage"
    />
  </DashboardLayout>
</template>
