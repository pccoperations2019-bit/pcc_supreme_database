<script setup>
import { ref, provide } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import PartnerForm from './Partials/Form.vue'
import PartnerModalForm from './Partials/PartnerModalForm.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { buildEntityBreadcrumbs } from '@/lib/breadcrumbs'
import { label } from '@/lib/utils'

const props = defineProps({
  partner: { type: Object, required: true },
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
  centers: { type: Array, default: () => [] },
  projects: { type: Object, default: () => ({}) },
  farmers: {
    type: Object,
    required: true,
  },
  dairyBoxes: {
    type: Array,
    default: () => [],
  },
})

const modalState = ref({ open: false, type: null, id: null })
provide('modalState', modalState)

const breadcrumbs = buildEntityBreadcrumbs({
  sectionLabel: 'Partners',
  sectionHref: '/partners',
  itemLabel: props.partner.name,
  itemHref: `/partners/${props.partner.id}`,
  actionLabel: 'Edit',
  actionHref: `/partners/${props.partner.id}/edit`,
})
</script>

<template>
  <Head :title="`Edit Partner - ${props.partner.name}`" />
  <DashboardLayout :breadcrumbs="breadcrumbs">
    <div
      class="sticky top-0 z-20 mb-3 flex flex-wrap items-start justify-between gap-3 rounded-lg border-b bg-white px-4 py-4"
    >
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <h1 class="truncate text-2xl font-bold text-gray-700 sm:text-3xl">
            {{ props.partner.name }}
          </h1>
          <Badge v-if="props.partner.category">
            {{ label(props.enums.categories, props.partner.category) }}
          </Badge>
          <Badge v-if="props.partner.partnership_type">{{
            label(props.enums.partnershipTypes, props.partner.partnership_type)
          }}</Badge>
        </div>
      </div>
      <Button
        variant="outline"
        class="shrink-0 cursor-pointer text-slate-900 hover:text-slate-900"
        @click="router.visit(route('partners.show', props.partner.id))"
      >
        View
      </Button>
    </div>
    <PartnerForm
      :partner="props.partner"
      :products="props.products"
      :product-registration-documents="props.productRegistrationDocuments"
      :enums="props.enums"
      :documents="props.documents"
      :centers="props.centers"
      :projects="props.projects"
      :dairy-boxes="props.partner.dairy_boxes || props.dairyBoxes"
      mode="edit"
    />
    <PartnerModalForm :partner="props.partner" :products="props.products" :enums="props.enums" />
  </DashboardLayout>
</template>
