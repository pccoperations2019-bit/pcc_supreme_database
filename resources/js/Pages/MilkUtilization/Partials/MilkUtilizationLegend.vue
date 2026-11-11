<script setup>
import { computed } from 'vue'

const props = defineProps({
  columns: { type: Array, default: () => [] },
  legend: { type: Array, default: () => [] },
})

const FALLBACKS = {
  source: { term: 'Source', text: 'aggregated from source records' },
  key_input: { term: 'Key input', text: 'entered directly on the tracker' },
  derived: { term: 'Derived', text: 'calculated from the other columns on the row' },
  ratio: { term: 'Ratio', text: 'a share of Total Milk Available' },
  headline: { term: 'Headline', text: 'the % Used KPI for the row' },
}

const humanise = kind =>
  kind
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')

const entries = computed(() => {
  const present = new Set(
    props.columns.filter(column => column.in_values !== false).map(column => column.kind)
  )

  present.delete('label')

  return [...present].filter(Boolean).map(kind => {
    const serverEntry = props.legend.find(item => item.color_key === kind || item.key === kind)
    const fallback = FALLBACKS[kind]

    return {
      kind,
      term: fallback?.term ?? humanise(kind),
      text: serverEntry?.label ?? fallback?.text ?? 'see the column header',
    }
  })
})
</script>

<template>
  <div v-if="entries.length" class="rounded-lg border border-slate-200 px-4 py-3">
    <p class="text-xs font-semibold tracking-wide text-slate-600 uppercase">
      How to read the columns
    </p>
    <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
      <div v-for="entry in entries" :key="entry.kind" class="flex gap-2 text-sm text-slate-700">
        <dt class="font-semibold whitespace-nowrap text-slate-900">{{ entry.term }}</dt>
        <dd class="min-w-0">— {{ entry.text }}</dd>
      </div>
    </dl>
    <p class="mt-2 text-xs text-slate-500">
      The Total row is the only emphasised row, and it is a sum of the centers above it. An em dash
      (—) means “no records captured”, not a measured zero.
    </p>
  </div>
</template>
