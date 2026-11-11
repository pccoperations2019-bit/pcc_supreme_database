<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import UserTable from './Partials/UserTable.vue'
import { useAuth } from '@/composable/useAuth'

defineProps({
  users: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const { hasPermission } = useAuth()
</script>

<template>
  <Head title="Users" />
  <DashboardLayout>
    <UserTable :users="users" :filters="filters" :can="{ create: hasPermission('create-users') }" />
  </DashboardLayout>
</template>
