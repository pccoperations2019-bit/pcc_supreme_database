<script setup>
import { computed, watch } from 'vue'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Label } from '@/components/ui/label'

const props = defineProps({
  provinceValue: {
    type: String,
    default: '',
  },
  townValue: {
    type: String,
    default: '',
  },
  barangayValue: {
    type: String,
    default: '',
  },
  provinceOptions: {
    type: Object,
    default: () => ({}),
  },
  townOptions: {
    type: Object,
    default: () => ({}),
  },
  barangayOptions: {
    type: Object,
    default: () => ({}),
  },
  provinceLabel: {
    type: String,
    default: 'Province',
  },
  townLabel: {
    type: String,
    default: 'Town/Municipality',
  },
  barangayLabel: {
    type: String,
    default: 'Barangay',
  },
})

const emit = defineEmits(['update:provinceValue', 'update:townValue', 'update:barangayValue'])

// Computed options based on parent selection
const availableTowns = computed(() => {
  if (!props.provinceValue || !props.townOptions) return {}
  return props.townOptions[props.provinceValue] || {}
})

const availableBarangays = computed(() => {
  if (!props.townValue || !props.barangayOptions) return {}
  return props.barangayOptions[props.townValue] || {}
})

// Reset child selections when parent changes
watch(
  () => props.provinceValue,
  newVal => {
    if (!newVal) {
      emit('update:townValue', '')
      emit('update:barangayValue', '')
    }
  }
)

watch(
  () => props.townValue,
  newVal => {
    if (!newVal) {
      emit('update:barangayValue', '')
    }
  }
)
</script>

<template>
  <div class="space-y-4">
    <!-- Province -->
    <div class="space-y-2">
      <Label>{{ provinceLabel }}</Label>
      <Select
        :model-value="provinceValue"
        @update:model-value="emit('update:provinceValue', $event)"
      >
        <SelectTrigger>
          <SelectValue :placeholder="`Select ${provinceLabel}`" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="(label, key) in provinceOptions" :key="key" :value="key">
            {{ label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Town/Municipality -->
    <div class="space-y-2">
      <Label>{{ townLabel }}</Label>
      <Select
        :model-value="townValue"
        :disabled="!provinceValue"
        @update:model-value="emit('update:townValue', $event)"
      >
        <SelectTrigger>
          <SelectValue
            :placeholder="provinceValue ? `Select ${townLabel}` : `Select ${provinceLabel} first`"
          />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="(label, key) in availableTowns" :key="key" :value="key">
            {{ label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Barangay -->
    <div class="space-y-2">
      <Label>{{ barangayLabel }}</Label>
      <Select
        :model-value="barangayValue"
        :disabled="!townValue"
        @update:model-value="emit('update:barangayValue', $event)"
      >
        <SelectTrigger>
          <SelectValue
            :placeholder="townValue ? `Select ${barangayLabel}` : `Select ${townLabel} first`"
          />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="(label, key) in availableBarangays" :key="key" :value="key">
            {{ label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
  </div>
</template>
