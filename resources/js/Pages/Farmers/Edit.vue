<script setup>
import { ref, provide } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import FarmerForm from './Partials/Form.vue'
import FarmerModalForm from './Partials/FarmerModalForm.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { buildEntityBreadcrumbs } from '@/lib/breadcrumbs'

const props = defineProps({
  farmer: { type: Object, required: true },
  enums: {
    type: Object,
    required: true,
  },
  documents: {
    type: Array,
    required: true,
  },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  defaultCenterId: { type: Number, default: null },
})

const modalState = ref({ open: false, type: null, id: null, lastClosedAt: 0 })
provide('modalState', modalState)

const getFullName = farmer => {
  const parts = [farmer.first_name, farmer.middle_name, farmer.last_name].filter(Boolean)
  return parts.join(' ') || 'N/A'
}

const breadcrumbs = buildEntityBreadcrumbs({
  sectionLabel: 'Farmers',
  sectionHref: '/farmers',
  itemLabel: getFullName(props.farmer),
  itemHref: `/farmers/${props.farmer.id}`,
  actionLabel: 'Edit',
  actionHref: `/farmers/${props.farmer.id}/edit`,
})
</script>
<template>
  <Head :title="`Edit Farmer - ${getFullName(props.farmer)}`" />
  <DashboardLayout :breadcrumbs="breadcrumbs">
    <div
      class="sticky top-0 z-20 mb-3 flex flex-wrap items-start justify-between gap-3 rounded-lg border-b bg-white px-4 py-4"
    >
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <h1 class="truncate text-2xl font-bold text-gray-700 sm:text-3xl">
            {{ getFullName(props.farmer) }}
          </h1>
          <div class="flex flex-wrap gap-1">
            <Badge v-if="props.farmer.code">{{ props.farmer.code }}</Badge>
            <Badge v-if="props.farmer.partner">{{ props.farmer.partner.name }}</Badge>
          </div>
        </div>
      </div>
      <Button
        variant="outline"
        class="cursor-pointer text-slate-900 hover:text-slate-900"
        @click="router.visit(route('farmers.show', props.farmer.id))"
      >
        View
      </Button>
    </div>
    <FarmerForm
      :farmer="props.farmer"
      :enums="props.enums"
      :documents="props.documents"
      :centers="props.centers"
      :partners="props.partners"
      :projects="props.projects"
      :default-center-id="defaultCenterId"
      mode="edit"
    />
    <FarmerModalForm :farmer="props.farmer" :enums="props.enums" />
  </DashboardLayout>
</template>
