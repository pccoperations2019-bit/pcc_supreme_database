<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import PartnerTable from './Partials/PartnerTable.vue'
import FiltersPanel from '@/components/FiltersPanel.vue'
import { useAuth } from '@/composable/useAuth'

const page = usePage()
const { isCenterScoped } = useAuth()

defineProps({
  partners: { type: Object, required: true },
  filters: { type: Object, required: false },
  enums: { type: Object, required: false, default: () => ({}) },
})

const inertiaProps = computed(() => page.props || {})

const handleClusterFilter = () => {
  // FiltersPanel already did pushState to set ?filter[center_id]=4
  // Need an Inertia visit so PartnerController index re-queries with that filter
  // without requiring a hard refresh
  router.get(
    window.location.pathname + window.location.search,
    {},
    {
      only: ['partners'],
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}
</script>
<template>
  <Head title="Partner" />
  <DashboardLayout>
    <template v-if="!isCenterScoped" #filters>
      <FiltersPanel
        :filters="filters"
        :elements="['clusteredCenters']"
        @update:filter="handleClusterFilter"
      />
    </template>
    <PartnerTable :partners="partners" :enums="enums" />
  </DashboardLayout>
</template>
