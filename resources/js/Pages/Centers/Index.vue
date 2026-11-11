<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import CenterTable from './Partials/CenterTable.vue'
import FiltersPanel from '@/components/FiltersPanel.vue'
import { useAuth } from '@/composable/useAuth'

const page = usePage()
const { isCenterScoped } = useAuth()

defineProps({
  centers: { type: Array, required: true },
  enums: { type: Object, required: true },
  filters: { type: Object, required: false },
  can: { type: Object, required: false },
  userCenterId: { type: Number, required: false },
})

const inertiaProps = computed(() => page.props || {})
</script>
<template>
  <Head title="Centers" />
  <DashboardLayout>
    <template v-if="!isCenterScoped" #filters>
      <FiltersPanel :filters="filters" :elements="['clusteredCenters']" />
    </template>
    <CenterTable :centers="centers" :enums="enums" :can="can" :user-center-id="userCenterId" />
  </DashboardLayout>
</template>
