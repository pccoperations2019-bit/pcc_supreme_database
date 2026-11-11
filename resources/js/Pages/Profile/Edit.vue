<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'
</script>

<template>
  <Head title="Account Settings" />
  <DashboardLayout>
    <div class="mx-auto w-full max-w-6xl">
      <div class="mb-8">
        <h1 class="text-3xl font-bold">Account Settings</h1>
        <p class="mt-2 text-slate-600">Manage your profile and login credentials.</p>
      </div>

      <div class="space-y-6">
        <UpdateProfileInformationForm />
        <UpdatePasswordForm />
      </div>
    </div>
  </DashboardLayout>
</template>
