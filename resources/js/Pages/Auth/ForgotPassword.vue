<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import AuthInput from '@/components/auth/AuthInput.vue'
import AuthButton from '@/components/auth/AuthButton.vue'

defineProps({
  status: {
    type: String,
  },
})

const form = useForm({
  email: '',
})

const submit = () => {
  form.post(route('password.email'))
}
</script>

<template>
  <GuestLayout title="Forgot Password">
    <Head title="Forgot Password" />

    <template #header>
      <div class="space-y-2">
        <h2 class="text-2xl font-bold tracking-tight">Reset password</h2>
        <p class="text-muted-foreground text-sm text-balance">
          Enter your email address and we'll send you a password reset link
        </p>
      </div>
    </template>

    <div
      v-if="status"
      class="mb-6 rounded-md border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-600"
    >
      {{ status }}
    </div>

    <form class="space-y-6" @submit.prevent="submit">
      <AuthInput
        id="email"
        v-model="form.email"
        type="email"
        label="Email"
        placeholder="name@example.com"
        :error="form.errors.email"
        :validate-email="true"
        autocomplete="username"
        required
        autofocus
      />

      <AuthButton type="submit" class-name="w-full" :loading="form.processing">
        Send reset link
      </AuthButton>
    </form>
  </GuestLayout>
</template>
