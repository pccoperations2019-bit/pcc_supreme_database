<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import FarmerTable from './Partials/FarmerTable.vue'
import FiltersPanel from '@/components/FiltersPanel.vue'
import { useAuth } from '@/composable/useAuth'

const page = usePage()
const { isCenterScoped } = useAuth()

defineProps({
  farmers: { type: Object, required: true },
  filters: { type: Object, required: false },
  enums: { type: Object, required: false, default: () => ({}) },
})

const inertiaProps = computed(() => page.props || {})

const handleClusterFilter = () => {
  router.get(
    window.location.pathname + window.location.search,
    {},
    {
      only: ['farmers'],
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}
</script>
<template>
  <Head title="Farmers" />
  <DashboardLayout>
    <template v-if="!isCenterScoped" #filters>
      <FiltersPanel
        :filters="filters"
        :elements="['clusteredCenters']"
        @update:filter="handleClusterFilter"
      />
    </template>
    <FarmerTable :farmers="farmers" :enums="enums" />
  </DashboardLayout>
</template>
