<script setup>
import { computed } from 'vue'

const props = defineProps({
  columns: { type: Array, default: () => [] },
  sourceStatusNotes: { type: Object, default: () => ({}) },
  notes: { type: Array, default: () => [] },
})

const unavailableGroups = computed(() => {
  const groups = new Map()

  props.columns.forEach(column => {
    if (!column.source_status || column.source_status === 'ok' || column.in_values === false) {
      return
    }

    const group = groups.get(column.source_status) ?? {
      status: column.source_status,
      message: props.sourceStatusNotes[column.source_status] ?? '',
      columns: [],
    }

    group.columns.push(column)
    groups.set(column.source_status, group)
  })

  return [...groups.values()]
})

const footnotes = computed(() => props.notes.filter(note => /^\s*\*/.test(note)))
const generalNotes = computed(() => props.notes.filter(note => !/^\s*\*/.test(note)))
</script>

<template>
  <div
    v-if="unavailableGroups.length || generalNotes.length || footnotes.length"
    class="rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700"
  >
    <div v-if="unavailableGroups.length">
      <p class="text-xs font-semibold tracking-wide text-slate-600 uppercase">
        Data availability — an em dash (—) means “not captured”, not zero
      </p>
      <ul class="mt-2 space-y-2 pl-5">
        <li v-for="group in unavailableGroups" :key="group.status">
          <span class="font-medium text-slate-900">
            <span class="font-mono text-xs text-slate-600">{{ group.status }}</span>
            <span v-if="group.message"> — {{ group.message }}</span>
          </span>
          <ul class="mt-1 list-disc space-y-1 pl-5">
            <li v-for="column in group.columns" :key="column.key">
              <span class="font-medium text-slate-900"
                >{{ column.letter }} — {{ column.label }}</span
              >
              <span v-if="column.source" class="text-slate-600"> ({{ column.source }})</span>
              <span class="text-slate-600"> — {{ column.formula }}</span>
            </li>
          </ul>
        </li>
      </ul>
    </div>

    <div v-if="generalNotes.length" class="mt-3">
      <p class="text-xs font-semibold tracking-wide text-slate-600 uppercase">Notes</p>
      <ul class="mt-1 list-disc space-y-1 pl-5">
        <li v-for="(note, index) in generalNotes" :key="index">{{ note }}</li>
      </ul>
    </div>

    <div v-if="footnotes.length" class="mt-3">
      <p class="text-xs font-semibold tracking-wide text-slate-600 uppercase">Footnotes</p>
      <ul class="mt-1 list-disc space-y-1 pl-5">
        <li v-for="(note, index) in footnotes" :key="index">{{ note }}</li>
      </ul>
    </div>
  </div>
</template>
