<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import PartnerView from './Partials/PartnerView.vue'
import { buildEntityBreadcrumbs } from '@/lib/breadcrumbs'

const props = defineProps({
  partner: { type: Object, required: true },
  products: {
    type: Array,
  },
  enums: {
    type: Object,
    required: true,
  },
  documents: {
    type: Array,
    required: true,
  },
  farmers: {
    type: Object,
    required: true,
  },
})

const breadcrumbs = buildEntityBreadcrumbs({
  sectionLabel: 'Partners',
  sectionHref: '/partners',
  itemLabel: props.partner.name,
  itemHref: `/partners/${props.partner.id}`,
})
</script>
<template>
  <Head :title="`Partner - ${props.partner.name}`" />
  <DashboardLayout :breadcrumbs="breadcrumbs">
    <PartnerView
      :partner="props.partner"
      :products="props.products"
      :enums="props.enums"
      :documents="props.documents"
      :farmers="props.farmers"
      :dairy-boxes="props.partner.dairy_boxes || []"
    />
  </DashboardLayout>
</template>
