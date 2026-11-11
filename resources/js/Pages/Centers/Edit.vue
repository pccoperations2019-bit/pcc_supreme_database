<script setup>
import { ref, provide, computed } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import CenterForm from './Partials/Form.vue'
import CenterModalForm from './Partials/CenterModalForm.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { buildEntityBreadcrumbs } from '@/lib/breadcrumbs'

const props = defineProps({
  center: { type: Object, required: true },
  products: {
    type: Array,
  },
  productRegistrationDocuments: {
    type: Object,
    default: () => ({}),
  },
  enums: {
    type: Object,
    required: true,
  },
  documents: {
    type: Array,
    required: true,
  },
  partners: {
    type: Object,
    required: true,
  },
  projects: {
    type: Object,
    default: () => ({}),
  },
})

const centerLabel = computed(() => {
  if (!props.center.code) return 'N/A'
  return props.enums?.center?.[props.center.code] || props.center.name
})

const breadcrumbs = buildEntityBreadcrumbs({
  sectionLabel: 'Centers',
  sectionHref: '/centers',
  itemLabel: centerLabel.value,
  itemHref: `/centers/${props.center.id}`,
  actionLabel: 'Edit',
  actionHref: `/centers/${props.center.id}/edit`,
})

const modalState = ref({ open: false, type: null, id: null })
provide('modalState', modalState)
</script>

<template>
  <Head :title="`Edit Center - ${centerLabel}`" />

  <DashboardLayout :breadcrumbs="breadcrumbs">
    <div
      class="sticky top-0 z-20 mb-3 flex flex-wrap items-start justify-between gap-3 rounded-lg border-b bg-white px-4 py-4"
    >
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <h1 class="truncate text-2xl font-bold text-gray-700 sm:text-3xl">
            {{ centerLabel }}
          </h1>

          <Badge v-if="props.center.category">
            {{ props.center.category }}
          </Badge>

          <Badge v-if="props.center.type">
            {{ props.center.type }}
          </Badge>
        </div>
      </div>

      <Button
        variant="outline"
        class="shrink-0 cursor-pointer text-slate-900 hover:text-slate-900"
        @click="router.visit(route('centers.show', props.center.id))"
      >
        View
      </Button>
    </div>

    <CenterForm
      :center="props.center"
      :products="props.products"
      :product-registration-documents="props.productRegistrationDocuments"
      :enums="props.enums"
      :documents="props.documents"
      :projects="props.projects"
      mode="edit"
    />

    <CenterModalForm :center="props.center" :products="props.products" :enums="props.enums" />
  </DashboardLayout>
</template>
