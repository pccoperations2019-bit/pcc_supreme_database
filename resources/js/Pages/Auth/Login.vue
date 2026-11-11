<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Checkbox } from '@/components/ui/checkbox'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import AuthInput from '@/components/auth/AuthInput.vue'
import AuthButton from '@/components/auth/AuthButton.vue'

defineProps({
  canResetPassword: Boolean,
  status: String,
  canRegister: Boolean,
})

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  console.log(route('login'))
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <GuestLayout title="Log in">
    <Head title="Log in" />

    <template #header>
      <div class="space-y-1 text-center sm:space-y-2">
        <h2 class="text-xl font-bold tracking-tight sm:text-2xl md:text-3xl">Welcome back</h2>
        <p class="text-muted-foreground text-xs sm:text-sm">
          Enter your credentials to access your account
        </p>
      </div>
    </template>

    <div
      v-if="status"
      class="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-600"
    >
      {{ status }}
    </div>

    <form class="space-y-6" @submit.prevent="submit">
      <AuthInput
        id="email"
        v-model="form.email"
        type="text"
        label="Email or Username"
        placeholder="Enter email or username"
        :error="form.errors.email"
        autocomplete="username"
        required
        autofocus
      />

      <AuthInput
        id="password"
        v-model="form.password"
        type="password"
        label="Password"
        :error="form.errors.password"
        autocomplete="current-password"
        :min-length="8"
        required
      />

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center space-x-2">
          <Checkbox id="remember" v-model:checked="form.remember" />
          <label
            for="remember"
            class="cursor-pointer text-xs leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70 sm:text-sm"
          >
            Remember me
          </label>
        </div>

        <Link
          v-if="canResetPassword"
          :href="route('password.request')"
          class="text-primary text-xs font-medium underline-offset-4 hover:underline sm:text-sm"
        >
          Forgot password?
        </Link>
      </div>
      <AuthButton type="submit" class-name="w-full cursor-pointer" :loading="form.processing">
        Sign in
      </AuthButton>

      <div v-if="canRegister" class="text-muted-foreground text-center text-xs sm:text-sm">
        Don't have an account?
        <Link
          :href="route('register')"
          class="text-primary font-medium underline-offset-4 hover:underline"
        >
          Sign up
        </Link>
      </div>
    </form>
  </GuestLayout>
</template>
