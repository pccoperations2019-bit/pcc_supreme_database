<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { onMounted, ref, watch, computed, nextTick } from 'vue'
import axios from 'axios'
import debounce from 'lodash/debounce'

const props = defineProps({
  modelValue: { type: [String, Number, Array], required: false, default: null },
  options: { type: Array, required: false, default: () => [] },
  endpoint: { type: String, required: false },
  placeholder: { type: String, required: false, default: 'Select an option' },
  multiple: { type: Boolean, required: false, default: false },
  disabled: { type: Boolean, required: false, default: false },
  filterParams: { type: Object, required: false, default: () => ({}) },
  prependOptions: { type: Array, required: false, default: () => [] },
  limit: { type: Number, required: false, default: 0 },
})

const options = ref([])
const key = ref('')
const displayedValue = ref(Array.isArray(props.modelValue) ? props.modelValue.map(v => String(v)) : (props.modelValue !== null ? String(props.modelValue) : null))

const emit = defineEmits(['update:modelValue'])
console.log('[SearchableSelect] init modelValue:', props.modelValue, 'displayedValue:', displayedValue.value)

const displayValue = computed(() => {
  const allOptions = [...props.prependOptions, ...options.value]

  if (!props.multiple) {
    const selectedOption = allOptions.find(opt => String(opt.value) === String(displayedValue.value))
    return selectedOption ? selectedOption.label : ''
  }

  const selectedOptions = displayedValue.value
    .map(val => {
      const option = allOptions.find(opt => String(opt.value) === String(val))
      return option ? option.label : val
    })
    .filter(Boolean)

  return selectedOptions.join(', ')
})

const selectOptions = computed(() => {
  const q = String(key.value || '')
    .toLowerCase()
    .trim()
  const prepended = props.prependOptions
    .filter(
      opt =>
        !q ||
        String(opt?.label || '')
          .toLowerCase()
          .includes(q)
    )
    .map(opt => ({ ...opt, value: String(opt.value) }))

  const fetched = options.value.map(option => ({
    ...option,
    value: String(option.value),
  }))

  const combined = [...prepended, ...fetched]

  return props.limit > 0 ? combined.slice(0, props.limit) : combined
})

const truncatedCount = computed(() => {
  if (!props.limit || props.limit <= 0) return 0
  const total = props.prependOptions.length + options.value.length
  return Math.max(0, total - props.limit)
})

watch(
  () => props.modelValue,
  newValue => {
    const newVal = Array.isArray(newValue) ? newValue.map(v => String(v)) : (newValue !== null && newValue !== undefined ? String(newValue) : newValue)
    console.log('[SearchableSelect] modelValue changed:', newValue, '-> displayedValue:', newVal)
    if (JSON.stringify(newVal) !== JSON.stringify(displayedValue.value)) {
      displayedValue.value = newVal
    }
  },
  { deep: true }
)

watch(
  displayedValue,
  newValue => {
    let emitVal = newValue
    if (Array.isArray(newValue)) {
      emitVal = newValue.map(v => String(v))
    } else if (newValue !== null && newValue !== undefined) {
      emitVal = String(newValue)
    }
    console.log('[SearchableSelect] emitting:', emitVal, 'modelValue was:', props.modelValue)
    if (JSON.stringify(emitVal) !== JSON.stringify(props.modelValue)) {
      emit('update:modelValue', emitVal)
    }
  },
  { deep: true }
)

const handleAjaxRequest = () => {
  const params = {
    key: key.value,
    ...props.filterParams,
  }

  axios
    .get(props.endpoint, {
      params,
    })
    .then(response => {
      options.value = response.data
    })
    .catch(error => {
      console.error('Error fetching options:', error)
    })
}

const filterLocalOptions = () => {
  const q = String(key.value || '')
    .toLowerCase()
    .trim()
  const base = Array.isArray(props.options) ? props.options : []
  if (!q) {
    options.value = base
    return
  }
  options.value = base.filter(opt =>
    String(opt?.label || '')
      .toLowerCase()
      .includes(q)
  )
}

onMounted(async () => {
  await nextTick()
  if (props.modelValue !== null && props.modelValue !== undefined) {
    const newVal = Array.isArray(props.modelValue) ? props.modelValue.map(v => String(v)) : String(props.modelValue)
    console.log('[SearchableSelect] onMounted setting displayedValue:', newVal)
    if (JSON.stringify(newVal) !== JSON.stringify(displayedValue.value)) {
      displayedValue.value = newVal
    }
  }

  if (props.multiple && !Array.isArray(displayedValue.value)) {
    displayedValue.value = displayedValue.value ? [String(displayedValue.value)] : []
  }

  if (props.endpoint) {
    handleAjaxRequest()
  } else {
    filterLocalOptions()
  }
})

watch(
  () => props.filterParams,
  () => {
    if (props.endpoint) {
      handleAjaxRequest()
    }
  },
  { deep: true }
)

watch(
  () => props.options,
  () => {
    if (!props.endpoint) {
      filterLocalOptions()
    }
  },
  { deep: true }
)

watch(
  options,
  () => {
  },
  { deep: true }
)

const handleSearchInput = debounce(() => {
  if (props.endpoint) {
    handleAjaxRequest()
  } else {
    filterLocalOptions()
  }
}, 300)

const handleInputKeyDown = event => {
  event.stopPropagation()
}

const handleInputFocus = event => {
  event.stopPropagation()
}

const handleInputClick = event => {
  event.stopPropagation()
}
</script>

<template>
  <Select v-model="displayedValue" :multiple="multiple" :disabled="disabled">
    <SelectTrigger class="w-full">
      <SelectValue :placeholder="placeholder">
        {{ displayValue || placeholder }}
      </SelectValue>
    </SelectTrigger>
    <SelectContent class="max-h-80">
      <div class="bg-popover sticky top-0 z-10 pb-2" @mousedown.stop @click.stop>
        <Input
          v-model="key"
          type="text"
          placeholder="Type to search..."
          class="mb-0"
          @input="handleSearchInput"
          @keydown="handleInputKeyDown"
          @focus="handleInputFocus"
          @click="handleInputClick"
          @mousedown.stop
        />
      </div>
      <SelectItem v-for="option in selectOptions" :key="option.value" :value="option.value">
        {{ option.label }}
      </SelectItem>
      <div v-if="truncatedCount > 0" class="text-muted-foreground px-2 py-3 text-sm">
        Showing {{ selectOptions.length }} of {{ selectOptions.length + truncatedCount }}. Type to
        see more.
      </div>
      <div v-else-if="selectOptions.length === 0" class="text-muted-foreground px-2 py-3 text-sm">
        No results found.
      </div>
    </SelectContent>
  </Select>
</template>
