<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import AuthButton from '@/components/auth/AuthButton.vue'
import { Button } from '@/components/ui/button'

const props = defineProps({
  status: {
    type: String,
  },
})

const form = useForm({})

const submit = () => {
  form.post(route('verification.send'))
}

const verificationLinkSent = computed(() => props.status === 'verification-link-sent')
</script>

<template>
  <GuestLayout title="Email Verification">
    <Head title="Email Verification" />

    <template #header>
      <div class="space-y-2">
        <h2 class="text-2xl font-bold tracking-tight">Verify your email</h2>
        <p class="text-muted-foreground text-sm text-balance">
          Check your inbox to verify your email address
        </p>
      </div>
    </template>

    <div class="space-y-6">
      <div class="bg-muted/50 text-muted-foreground rounded-lg p-4 text-sm">
        <p>
          Thanks for signing up! Before getting started, please verify your email address by
          clicking on the link we just emailed to you.
        </p>
        <p class="mt-2">If you didn't receive the email, we'll gladly send you another.</p>
      </div>

      <div
        v-if="verificationLinkSent"
        class="rounded-md border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-600"
      >
        A new verification link has been sent to your email address.
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <AuthButton type="submit" class-name="w-full" :loading="form.processing">
          Resend verification email
        </AuthButton>

        <div class="text-center">
          <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="text-muted-foreground hover:text-primary text-sm font-medium underline-offset-4 hover:underline"
          >
            Log out
          </Link>
        </div>
      </form>
    </div>
  </GuestLayout>
</template>
