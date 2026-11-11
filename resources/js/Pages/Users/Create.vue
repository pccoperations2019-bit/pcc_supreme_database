<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'
import UserGuide from './Partials/UserGuide.vue'

defineProps({
  user: { type: Object, default: () => ({}) },
  centers: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head title="Create User" />
  <DashboardLayout>
    <div class="mb-3">
      <h1 class="text-3xl font-bold">Create User</h1>
      <p class="text-slate-600">Create a new user account. It stays pending until approved.</p>
    </div>
    <div
      class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-[minmax(0,1fr)_42%] 2xl:grid-cols-[minmax(0,1fr)_38%]"
    >
      <div class="flex min-w-0 flex-col">
        <Form mode="create" :user="user" :centers="centers" :enums="enums" />
      </div>
      <div class="flex min-w-0 flex-col">
        <UserGuide mode="create" />
      </div>
    </div>
  </DashboardLayout>
</template>
