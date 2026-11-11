<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import ProfileTable from './Partials/ProfileTable.vue'
import FiltersPanel from '@/components/FiltersPanel.vue'
import { useAuth } from '@/composable/useAuth'

const page = usePage()
const { isCenterScoped } = useAuth()

defineProps({
  profiles: { type: Object, required: true },
  filters: { type: Object, required: false },
})

const inertiaProps = computed(() => page.props || {})

const handleClusterFilter = () => {
  router.get(
    window.location.pathname + window.location.search,
    {},
    {
      only: ['profiles'],
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}
</script>

<template>
  <Head title="Profiles" />
  <DashboardLayout>
    <template v-if="!isCenterScoped" #filters>
      <FiltersPanel
        :filters="filters"
        :elements="['clusteredCenters']"
        @update:filter="handleClusterFilter"
      />
    </template>
    <ProfileTable :profiles="profiles" />
  </DashboardLayout>
</template>
