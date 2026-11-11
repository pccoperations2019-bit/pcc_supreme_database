<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Eye, EyeOff } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)

const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const errors = computed(() => form.errors)

const emit = defineEmits(['saved'])

const firstError = value => (Array.isArray(value) ? value[0] : value)

const clearError = key => {
  if (form.errors[key]) form.clearErrors(key)
}

const errorClass = key =>
  errors.value[key] ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''

const updatePassword = () => {
  form.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      emit('saved')
    },
  })
}

// Confirm must match before hitting the server, so the message appears under the field.
const submit = () => {
  if (form.password_confirmation && form.password_confirmation !== form.password) {
    form.setError('password_confirmation', 'Passwords do not match.')
    return
  }
  updatePassword()
}
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Change Password</CardTitle>
      <CardDescription>Use a long, random password to keep your account secure</CardDescription>
    </CardHeader>
    <CardContent>
      <form class="space-y-6" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
          <div class="space-y-2 sm:col-span-2">
            <Label for="current_password" class="text-muted-foreground font-medium">
              Current Password
            </Label>
            <div class="relative">
              <Input
                id="current_password"
                v-model="form.current_password"
                :type="showCurrentPassword ? 'text' : 'password'"
                autocomplete="current-password"
                :class="['h-9 pr-10', errorClass('current_password')]"
                @input="clearError('current_password')"
              />
              <button
                type="button"
                class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute top-1/2 right-1 flex h-7 w-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded transition-colors focus-visible:ring-2 focus-visible:outline-none"
                :aria-label="
                  showCurrentPassword ? 'Hide current password' : 'Show current password'
                "
                :aria-pressed="showCurrentPassword"
                aria-controls="current_password"
                @click="showCurrentPassword = !showCurrentPassword"
              >
                <EyeOff v-if="showCurrentPassword" class="h-4 w-4" aria-hidden="true" />
                <Eye v-else class="h-4 w-4" aria-hidden="true" />
              </button>
            </div>
            <p v-if="errors.current_password" class="mt-1 text-sm text-red-500">
              {{ firstError(errors.current_password) }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="password" class="text-muted-foreground font-medium">New Password</Label>
            <div class="relative">
              <Input
                id="password"
                v-model="form.password"
                :type="showNewPassword ? 'text' : 'password'"
                autocomplete="new-password"
                :class="['h-9 pr-10', errorClass('password')]"
                @input="clearError('password')"
              />
              <button
                type="button"
                class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute top-1/2 right-1 flex h-7 w-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded transition-colors focus-visible:ring-2 focus-visible:outline-none"
                :aria-label="showNewPassword ? 'Hide new password' : 'Show new password'"
                :aria-pressed="showNewPassword"
                aria-controls="password"
                @click="showNewPassword = !showNewPassword"
              >
                <EyeOff v-if="showNewPassword" class="h-4 w-4" aria-hidden="true" />
                <Eye v-else class="h-4 w-4" aria-hidden="true" />
              </button>
            </div>
            <p v-if="errors.password" class="mt-1 text-sm text-red-500">
              {{ firstError(errors.password) }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="password_confirmation" class="text-muted-foreground font-medium">
              Confirm Password
            </Label>
            <div class="relative">
              <Input
                id="password_confirmation"
                v-model="form.password_confirmation"
                :type="showConfirmPassword ? 'text' : 'password'"
                autocomplete="new-password"
                :class="['h-9 pr-10', errorClass('password_confirmation')]"
                @input="clearError('password_confirmation')"
              />
              <button
                type="button"
                class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute top-1/2 right-1 flex h-7 w-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded transition-colors focus-visible:ring-2 focus-visible:outline-none"
                :aria-label="
                  showConfirmPassword ? 'Hide confirmed password' : 'Show confirmed password'
                "
                :aria-pressed="showConfirmPassword"
                aria-controls="password_confirmation"
                @click="showConfirmPassword = !showConfirmPassword"
              >
                <EyeOff v-if="showConfirmPassword" class="h-4 w-4" aria-hidden="true" />
                <Eye v-else class="h-4 w-4" aria-hidden="true" />
              </button>
            </div>
            <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-500">
              {{ firstError(errors.password_confirmation) }}
            </p>
          </div>
        </div>

        <Button class="bg-blue-600 hover:bg-blue-700" :disabled="form.processing" type="submit">
          {{ form.processing ? 'Updating...' : 'Update Password' }}
        </Button>
      </form>
    </CardContent>
  </Card>
</template>
