<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import Form from './Partials/Form.vue'
import UserGuide from './Partials/UserGuide.vue'

defineProps({
  user: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})
</script>

<template>
  <Head :title="`User - ${user.name}`" />
  <DashboardLayout>
    <div
      class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-[minmax(0,1fr)_42%] 2xl:grid-cols-[minmax(0,1fr)_38%]"
    >
      <div class="flex min-w-0 flex-col">
        <Form mode="view" :user="user" :centers="centers" :enums="enums" />
      </div>
      <div class="flex min-w-0 flex-col">
        <UserGuide mode="view" />
      </div>
    </div>
  </DashboardLayout>
</template>
