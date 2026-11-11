<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import axios from 'axios'

const props = defineProps({
  source: String,
  field: String,
  label: String,
  modelValue: Object,
  anchorEl: { type: null, default: null },
})
const emit = defineEmits(['update:modelValue', 'close', 'apply'])

const search = ref('')
const allValues = ref([])
const hasBlanks = ref(false)
const selected = ref(new Set(props.modelValue?.values ?? []))
const includeBlanks = ref(props.modelValue?.includeBlanks ?? true)
const loading = ref(true)
const searching = ref(false)

const panelEl = ref(null)
const pos = ref({ top: 20, left: 20 })

onMounted(() => {
  nextTick(placePanel)
  window.addEventListener('resize', placePanel)
  window.addEventListener('scroll', placePanel, true)
})
onBeforeUnmount(() => {
  window.removeEventListener('resize', placePanel)
  window.removeEventListener('scroll', placePanel, true)
})

function placePanel() {
  const anchor = props.anchorEl
  const innerW = window.innerWidth
  const innerH = window.innerHeight
  const panelW = panelEl.value?.offsetWidth || 288
  const panelH = panelEl.value?.offsetHeight || 320

  if (!anchor) {
    pos.value = {
      top: Math.max(16, (innerH - panelH) / 2),
      left: Math.max(16, (innerW - panelW) / 2),
    }
    return
  }

  const r = anchor.getBoundingClientRect()

  let left = r.left
  if (left + panelW > innerW - 16) {
    left = Math.max(16, innerW - panelW - 16)
  }

  let top = r.bottom + 4
  if (top + panelH > innerH - 16) {
    const spaceAbove = r.top - 4
    if (spaceAbove >= panelH) {
      top = r.top - panelH - 4
    } else {
      top = Math.max(16, innerH - panelH - 16)
    }
  }

  pos.value = { top, left }
}

let requestId = 0
async function load() {
  const thisRequest = ++requestId
  if (allValues.value.length === 0) loading.value = true
  else searching.value = true
  const { data } = await axios.get('/reports/pivot/field-values', {
    params: {
      source: props.source,
      field: props.field,
      search: search.value || undefined,
    },
  })
  if (thisRequest !== requestId) return
  allValues.value = data.values
  hasBlanks.value = data.has_blanks
  if (!props.modelValue) {
    selected.value = new Set(data.values.map(v => v.value))
  }
  loading.value = false
  searching.value = false
  nextTick(placePanel)
}

let debounceHandle = null
watch(search, () => {
  searching.value = true
  clearTimeout(debounceHandle)
  debounceHandle = setTimeout(load, 300)
})
load()

const displayCount = computed(() =>
  loading.value ? '...' : allValues.value.length + (hasBlanks.value ? 1 : 0)
)

const rawValues = computed(() => allValues.value.map(v => v.value))

function toggle(v) {
  if (loading.value) return
  selected.value.has(v) ? selected.value.delete(v) : selected.value.add(v)
  selected.value = new Set(selected.value)
}
function selectAll() {
  if (loading.value) return
  selected.value = new Set(rawValues.value)
  includeBlanks.value = hasBlanks.value
}
function clearAll() {
  if (loading.value) return
  selected.value = new Set()
  includeBlanks.value = false
}
function apply() {
  if (loading.value) return
  emit('update:modelValue', {
    field: props.field,
    values: Array.from(selected.value),
    includeBlanks: includeBlanks.value,
  })
  emit('apply')
  emit('close')
}

function onKeydown(e) {
  if (e.key === 'Escape') emit('close')
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div
      ref="panelEl"
      class="pointer-events-auto flex w-72 flex-col rounded-xl border border-gray-200 bg-white p-3.5 text-xs text-gray-800 shadow-2xl"
      :style="{ position: 'fixed', top: pos.top + 'px', left: pos.left + 'px', zIndex: 99999 }"
    >
      <div
        class="fixed inset-0 z-[-1] bg-transparent"
        @mousedown.stop.prevent="emit('close')"
      ></div>

      <!-- Header row -->
      <div class="mb-2.5 flex items-center justify-between gap-2 text-xs">
        <div class="flex shrink-0 items-center gap-1.5">
          <button
            type="button"
            :disabled="loading"
            class="cursor-pointer font-medium text-blue-600 hover:underline focus:outline-none"
            :class="{ 'opacity-50': loading }"
            @click="selectAll"
          >
            Select all {{ displayCount }}
          </button>
          <span class="text-gray-300">•</span>
          <button
            type="button"
            :disabled="loading"
            class="cursor-pointer font-medium text-blue-600 hover:underline focus:outline-none"
            :class="{ 'opacity-50': loading }"
            @click="clearAll"
          >
            Clear
          </button>
        </div>
        <span class="shrink-0 text-[11px] text-gray-400">Displaying {{ displayCount }}</span>
      </div>

      <!-- Search box -->
      <div class="relative mb-2">
        <input
          v-model="search"
          type="text"
          placeholder="Search..."
          class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 pr-7 text-xs outline-none focus:border-blue-500"
        />
        <svg
          class="pointer-events-none absolute top-1/2 right-2.5 h-3.5 w-3.5 -translate-y-1/2 text-gray-400"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <circle cx="11" cy="11" r="7" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
      </div>
      <p v-if="searching" class="-mt-1 mb-1 text-[10px] text-gray-400">refining…</p>

      <div v-if="loading" class="py-6 text-center text-xs text-gray-400">Loading values…</div>
      <div v-else class="max-h-48 space-y-1 overflow-y-auto pr-1">
        <label
          v-if="hasBlanks"
          class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 hover:bg-gray-50"
        >
          <input
            v-model="includeBlanks"
            type="checkbox"
            class="size-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <span class="font-medium text-gray-700">(Blanks)</span>
        </label>
        <label
          v-for="v in allValues"
          :key="v.value"
          class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 hover:bg-gray-50"
        >
          <input
            type="checkbox"
            :checked="selected.has(v.value)"
            class="size-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            @change="toggle(v.value)"
          />
          <span class="truncate text-gray-700">{{ v.label }}</span>
        </label>
      </div>

      <div class="mt-3.5 flex justify-end gap-2 border-t border-gray-100 pt-2">
        <button
          type="button"
          :disabled="loading"
          class="cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none"
          :class="{ 'opacity-50': loading }"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="button"
          :disabled="loading"
          class="cursor-pointer rounded-lg bg-blue-600 px-3.5 py-1.5 text-xs font-medium text-white shadow-2xs hover:bg-blue-700 focus:outline-none"
          :class="{ 'opacity-50': loading }"
          @click="apply"
        >
          OK
        </button>
      </div>
    </div>
  </Teleport>
</template>
