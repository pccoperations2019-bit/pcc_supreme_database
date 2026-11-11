<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import AuthInput from '@/components/auth/AuthInput.vue'
import AuthButton from '@/components/auth/AuthButton.vue'

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <GuestLayout title="Register">
    <Head title="Register" />

    <template #header>
      <div class="space-y-2">
        <h2 class="text-2xl font-bold tracking-tight">Create an account</h2>
        <p class="text-muted-foreground text-sm">Enter your information to get started</p>
      </div>
    </template>

    <form class="space-y-6" @submit.prevent="submit">
      <AuthInput
        id="name"
        v-model="form.name"
        type="text"
        label="Name"
        placeholder="John Doe"
        :error="form.errors.name"
        autocomplete="name"
        required
        autofocus
      />

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
      />

      <AuthInput
        id="password"
        v-model="form.password"
        type="password"
        label="Password"
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
        Create account
      </AuthButton>

      <div class="text-muted-foreground text-center text-sm">
        Already have an account?
        <Link
          :href="route('login')"
          class="text-primary font-medium underline-offset-4 hover:underline"
        >
          Sign in
        </Link>
      </div>
    </form>
  </GuestLayout>
</template>
