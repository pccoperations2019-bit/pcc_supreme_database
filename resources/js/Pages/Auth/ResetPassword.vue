<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import AuthInput from '@/components/auth/AuthInput.vue'
import AuthButton from '@/components/auth/AuthButton.vue'

const props = defineProps({
  email: {
    type: String,
    required: true,
  },
  token: {
    type: String,
    required: true,
  },
})

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('password.store'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <GuestLayout title="Reset Password">
    <Head title="Reset Password" />

    <template #header>
      <div class="space-y-2">
        <h2 class="text-2xl font-bold tracking-tight">Create new password</h2>
        <p class="text-muted-foreground text-sm">Enter your new password below</p>
      </div>
    </template>

    <form class="space-y-6" @submit.prevent="submit">
      <AuthInput
        id="email"
        v-model="form.email"
        type="email"
        label="Email"
        :error="form.errors.email"
        :validate-email="true"
        autocomplete="username"
        required
        autofocus
      />

      <AuthInput
        id="password"
        v-model="form.password"
        type="password"
        label="New Password"
        :error="form.errors.password"
        autocomplete="new-password"
        :min-length="8"
        required
      />

      <AuthInput
        id="password_confirmation"
        v-model="form.password_confirmation"
        type="password"
        label="Confirm Password"
        :error="form.errors.password_confirmation"
        autocomplete="new-password"
        :min-length="8"
        required
      />

      <AuthButton type="submit" class-name="w-full" :loading="form.processing">
        Reset password
      </AuthButton>
    </form>
  </GuestLayout>
</template>
