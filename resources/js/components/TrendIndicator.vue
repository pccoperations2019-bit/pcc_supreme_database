<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ArrowDown, ArrowUp, Minus } from 'lucide-vue-next'
import { formatMetricValue } from '@/lib/utils'

const props = defineProps({
  current: { type: Number, default: 0 },
  previous: { type: Number, default: 0 },
  unit: { type: String, default: '' },
  periodLabel: { type: String, required: true },
  label: { type: String, default: '' },
})

const page = usePage()
const currencySymbols = computed(() => page.props.currencySymbols || {})

const trend = computed(() => {
  const { current, previous } = props
  if (previous <= 0 && current <= 0) return null
  if (previous <= 0) return { direction: 'up', percent: null }
  if (current === previous) return { direction: 'flat', percent: 0 }
  return {
    direction: current > previous ? 'up' : 'down',
    percent: Math.abs(((current - previous) / previous) * 100),
  }
})

const directionClasses = {
  up: 'text-green-600',
  down: 'text-red-600',
  flat: 'text-gray-400',
}

const tooltip = computed(() => {
  const previousValue = formatMetricValue(props.previous, props.unit, currencySymbols.value)
  return props.label
    ? `${props.label} in ${props.periodLabel}: ${previousValue}`
    : `${props.periodLabel}: ${previousValue}`
})
</script>

<template>
  <div
    v-if="trend"
    class="mt-1 flex items-center gap-0.5 text-[9px] font-semibold sm:text-[11px]"
    :class="directionClasses[trend.direction]"
    :title="tooltip"
  >
    <ArrowUp v-if="trend.direction === 'up'" class="h-3 w-3" />
    <ArrowDown v-else-if="trend.direction === 'down'" class="h-3 w-3" />
    <Minus v-else class="h-3 w-3" />
    <span v-if="trend.percent !== null">{{ trend.percent.toFixed(0) }}%</span>
    <span class="font-normal">vs {{ periodLabel }}</span>
  </div>
</template>
