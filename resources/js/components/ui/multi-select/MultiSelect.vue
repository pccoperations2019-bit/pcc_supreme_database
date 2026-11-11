<script setup>
import { ref } from 'vue'
import { Check, ChevronDown } from 'lucide-vue-next'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'

const props = defineProps({
  options: {
    type: Object,
    default: () => ({}),
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
  placeholder: {
    type: String,
    default: 'Select',
  },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)

const toggle = value => {
  const current = props.modelValue.includes(value)
    ? props.modelValue.filter(v => v !== value)
    : [...props.modelValue, value]
  emit('update:modelValue', current)
}
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <button
        type="button"
        role="combobox"
        :aria-expanded="open"
        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus:ring-ring flex h-8 w-full items-center justify-between rounded-md border px-3 py-2 text-sm focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
      >
        <span v-if="modelValue.length" class="flex flex-wrap gap-1">
          <span
            v-for="key in modelValue"
            :key="key"
            class="bg-muted rounded px-2 py-0.5 text-xs"
          >
            {{ options[key] }}
          </span>
        </span>
        <span v-else class="text-muted-foreground">{{ placeholder }}</span>
        <ChevronDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
      </button>
    </PopoverTrigger>
    <PopoverContent class="w-[var(--radix-popover-trigger-width)] p-1" align="start">
      <div
        v-for="(labelText, key) in options"
        :key="key"
        class="hover:bg-accent hover:text-accent-foreground flex cursor-pointer items-center rounded-sm px-2 py-1.5 text-sm select-none"
        @click="toggle(key)"
      >
        <Check
          class="mr-2 h-4 w-4 shrink-0"
          :class="modelValue.includes(key) ? 'opacity-100' : 'opacity-0'"
        />
        {{ labelText }}
      </div>
    </PopoverContent>
  </Popover>
</template>
