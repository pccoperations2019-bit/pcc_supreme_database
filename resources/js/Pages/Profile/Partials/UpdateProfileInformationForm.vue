<script setup>
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const user = usePage().props.auth.user

const form = useForm({
  name: user.name,
  email: user.email,
  username: user.username ?? '',
})

const errors = computed(() => form.errors)

const emit = defineEmits(['saved'])

const submit = () => {
  form.patch(route('profile.update'), {
    preserveScroll: true,
    onSuccess: () => emit('saved'),
  })
}

const clearError = key => {
  if (form.errors[key]) form.clearErrors(key)
}

const firstError = value => (Array.isArray(value) ? value[0] : value)
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Profile Information</CardTitle>
      <CardDescription>Update your name, login username and email address</CardDescription>
    </CardHeader>
    <CardContent>
      <form class="space-y-6" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
          <div class="space-y-2">
            <Label for="name" class="text-muted-foreground font-medium">Name</Label>
            <Input
              id="name"
              v-model="form.name"
              type="text"
              autocomplete="name"
              placeholder="Enter name"
              required
              :class="[
                'h-9',
                errors.name ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '',
              ]"
              @input="clearError('name')"
            />
            <p v-if="errors.name" class="mt-1 text-sm text-red-500">
              {{ firstError(errors.name) }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="username" class="text-muted-foreground font-medium">Username</Label>
            <Input
              id="username"
              v-model="form.username"
              type="text"
              autocomplete="username"
              placeholder="Enter username"
              required
              :class="[
                'h-9',
                errors.username ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '',
              ]"
              @input="clearError('username')"
            />
            <p v-if="errors.username" class="mt-1 text-sm text-red-500">
              {{ firstError(errors.username) }}
            </p>
          </div>

          <div class="space-y-2 sm:col-span-2">
            <Label for="email" class="text-muted-foreground font-medium">Email Address</Label>
            <Input
              id="email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              placeholder="Enter email address"
              required
              :class="[
                'h-9',
                errors.email ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '',
              ]"
              @input="clearError('email')"
            />
            <p v-if="errors.email" class="mt-1 text-sm text-red-500">
              {{ firstError(errors.email) }}
            </p>
          </div>
        </div>

        <Button class="bg-blue-600 hover:bg-blue-700" :disabled="form.processing" type="submit">
          {{ form.processing ? 'Saving...' : 'Save Changes' }}
        </Button>
      </form>
    </CardContent>
  </Card>
</template>
