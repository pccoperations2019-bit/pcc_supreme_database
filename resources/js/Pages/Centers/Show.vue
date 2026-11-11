<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import CenterView from './Partials/CenterView.vue'
import { buildEntityBreadcrumbs } from '@/lib/breadcrumbs'
const props = defineProps({
  center: { type: Object, required: true },
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
  projects: {
    type: Object,
    default: () => ({}),
  },
  partners: {
    type: Object,
    required: true,
  },
  user_center_id: {
    type: Number,
    required: false,
  },
})

const breadcrumbs = computed(() =>
  buildEntityBreadcrumbs({
    sectionLabel: 'Centers',
    sectionHref: '/centers',
    itemLabel: props.center.code || props.center.name,
    itemHref: `/centers/${props.center.id}`,
  })
)
</script>
<template>
  <Head :title="`Center - ${props.center.name}`" />
  <DashboardLayout :breadcrumbs="breadcrumbs">
    <CenterView
      :center="props.center"
      :products="props.products"
      :enums="props.enums"
      :documents="props.documents"
      :projects="props.projects"
      :partners="props.partners"
      :user_center_id="props.user_center_id"
    />
  </DashboardLayout>
</template>
