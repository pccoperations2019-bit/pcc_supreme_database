<script setup>
import { computed, ref, watch } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'

const props = defineProps({
  id: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    required: true,
  },
  type: {
    type: String,
    default: 'text',
  },
  modelValue: {
    type: [String, Number],
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  required: {
    type: Boolean,
    default: false,
  },
  autofocus: {
    type: Boolean,
    default: false,
  },
  autocomplete: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  validateOnBlur: {
    type: Boolean,
    default: true,
  },
  validateEmail: {
    type: Boolean,
    default: false,
  },
  minLength: {
    type: Number,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue'])

const localValue = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const showPassword = ref(false)
const touched = ref(false)
const localError = ref('')

const isPasswordField = computed(() => props.type === 'password')
const inputType = computed(() => {
  if (isPasswordField.value && showPassword.value) return 'text'
  return props.type
})

const hasError = computed(() => !!(props.error || localError.value))
const errorMessage = computed(() => props.error || localError.value)

const validateField = () => {
  if (!touched.value) return

  localError.value = ''

  if (props.required && !localValue.value) {
    localError.value = `${props.label} is required`
    return
  }

  if (props.validateEmail && localValue.value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(localValue.value)) {
      localError.value = 'Please enter a valid email address'
      return
    }
  }

  if (props.minLength && localValue.value.length < props.minLength) {
    localError.value = `${props.label} must be at least ${props.minLength} characters`
    return
  }
}

const handleBlur = () => {
  touched.value = true
  if (props.validateOnBlur) {
    validateField()
  }
}

watch(localValue, () => {
  if (touched.value) {
    validateField()
  }
})

// Clear local error when server error is provided
watch(
  () => props.error,
  newError => {
    if (newError) {
      localError.value = ''
    }
  }
)

const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value
}
</script>

<template>
  <div class="space-y-2">
    <Label :for="id" :class="cn(hasError && 'text-destructive')">
      {{ label }}
      <span v-if="required" class="text-destructive">*</span>
    </Label>

    <div class="relative">
      <Input
        :id="id"
        v-model="localValue"
        :type="inputType"
        :placeholder="placeholder"
        :required="required"
        :autofocus="autofocus"
        :autocomplete="autocomplete"
        :disabled="disabled"
        :class="cn(hasError && 'border-destructive focus-visible:ring-destructive', 'pr-10')"
        @blur="handleBlur"
      />

      <button
        v-if="isPasswordField"
        type="button"
        class="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2 transition-colors"
        tabindex="-1"
        @click="togglePasswordVisibility"
      >
        <svg
          v-if="!showPassword"
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
          <circle cx="12" cy="12" r="3" />
        </svg>
        <svg
          v-else
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
          <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
          <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
          <line x1="2" x2="22" y1="2" y2="22" />
        </svg>
      </button>
    </div>

    <p
      v-if="hasError"
      class="text-destructive animate-in fade-in-50 text-sm font-medium duration-200"
    >
      {{ errorMessage }}
    </p>
  </div>
</template>
