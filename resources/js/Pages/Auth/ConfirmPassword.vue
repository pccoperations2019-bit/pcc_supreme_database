<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import AuthInput from '@/components/auth/AuthInput.vue'
import AuthButton from '@/components/auth/AuthButton.vue'

const form = useForm({
  password: '',
})

const submit = () => {
  form.post(route('password.confirm'), {
    onFinish: () => form.reset(),
  })
}
</script>

<template>
  <GuestLayout title="Confirm Password">
    <Head title="Confirm Password" />

    <template #header>
      <div class="space-y-2">
        <h2 class="text-2xl font-bold tracking-tight">Confirm your password</h2>
        <p class="text-muted-foreground text-sm text-balance">
          This is a secure area. Please confirm your password before continuing.
        </p>
      </div>
    </template>

    <form class="space-y-6" @submit.prevent="submit">
      <AuthInput
        id="password"
        v-model="form.password"
        type="password"
        label="Password"
        :error="form.errors.password"
        autocomplete="current-password"
        required
        autofocus
      />

      <AuthButton type="submit" class-name="w-full" :loading="form.processing">
        Confirm
      </AuthButton>
    </form>
  </GuestLayout>
</template>
