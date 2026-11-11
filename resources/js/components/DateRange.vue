<script setup lang="ts">
import type { DateRange } from 'reka-ui'
import type { Ref } from 'vue'
import { getLocalTimeZone, today } from '@internationalized/date'
import { ref, watch } from 'vue'
import { RangeCalendar } from '@/components/ui/range-calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

// Default to current year
const start = today(getLocalTimeZone()).set({ month: 1, day: 1 })
const end = today(getLocalTimeZone())

const props = defineProps<{
  modelValue?: DateRange | null
}>()

const parseDate = date => {
  if (!date) return null
  if (date instanceof Date) return date
  if (typeof date === 'string') {
    const parsed = new Date(date)
    return Number.isNaN(parsed.getTime()) ? null : parsed
  }
  if (date.year && date.month && date.day) {
    return new Date(date.year, date.month - 1, date.day)
  }
  try {
    return new Date(date)
  } catch {
    return null
  }
}

const value = ref(
  (props.modelValue && typeof props.modelValue === 'object' && 'start' in props.modelValue
    ? props.modelValue
    : {
        start,
        end,
      }) as DateRange
) as Ref<DateRange>

watch(
  () => props.modelValue,
  newValue => {
    if (newValue && typeof newValue === 'object' && 'start' in newValue && 'end' in newValue) {
      value.value = newValue as DateRange
    }
  },
  { deep: true }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: DateRange): void
}>()

const formatDate = date => {
  const parsed = parseDate(date)
  if (!parsed) return 'Invalid Date'
  return parsed.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

// Or for date and time:
const formatDateTime = date => {
  const parsed = parseDate(date)
  if (!parsed) return 'Invalid Date'
  return parsed.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const updateDateRange = (newValue: DateRange) => {
  value.value = newValue
  emit('update:modelValue', newValue)
}

const datePresets = [
  {
    label: 'Last 7 Days',
    range: {
      start: today(getLocalTimeZone()).add({ days: -7 }),
      end: today(getLocalTimeZone()),
    },
  },
  {
    label: 'Last 30 Days',
    range: {
      start: today(getLocalTimeZone()).add({ days: -30 }),
      end: today(getLocalTimeZone()),
    },
  },
  {
    label: 'This Month',
    range: {
      start: today(getLocalTimeZone()).set({ day: 1 }),
      end: today(getLocalTimeZone()),
    },
  },
  {
    label: 'Last Month',
    range: {
      start: today(getLocalTimeZone()).add({ months: -1 }).set({ day: 1 }),
      end: today(getLocalTimeZone()).set({ day: 1 }).add({ days: -1 }),
    },
  },
  {
    label: 'This Year',
    range: {
      start: today(getLocalTimeZone()).set({ month: 1, day: 1 }),
      end: today(getLocalTimeZone()),
    },
  },
  {
    label: 'Last Year',
    range: {
      start: today(getLocalTimeZone()).add({ years: -1 }).set({ month: 1, day: 1 }),
      end: today(getLocalTimeZone()).set({ month: 1, day: 1 }).add({ days: -1 }),
    },
  },
  {
    label: 'This quarter',
    range: {
      start: (() => {
        const currentMonth = today(getLocalTimeZone()).month
        const quarterStartMonth = Math.floor((currentMonth - 1) / 3) * 3 + 1
        return today(getLocalTimeZone()).set({ month: quarterStartMonth, day: 1 })
      })(),
      end: today(getLocalTimeZone()),
    },
  },
  {
    label: 'Last quarter',
    range: {
      start: (() => {
        const currentMonth = today(getLocalTimeZone()).month
        const lastQuarterStartMonth = Math.floor((currentMonth - 1) / 3) * 3 - 2
        return today(getLocalTimeZone())
          .add({ months: -3 })
          .set({ month: lastQuarterStartMonth, day: 1 })
      })(),
      end: (() => {
        const currentMonth = today(getLocalTimeZone()).month
        const lastQuarterStartMonth = Math.floor((currentMonth - 1) / 3) * 3 - 2
        return today(getLocalTimeZone())
          .set({ month: lastQuarterStartMonth + 3, day: 1 })
          .add({ days: -1 })
      })(),
    },
  },
]
</script>

<template>
  <Popover>
    <PopoverTrigger as-child>
      <button
        class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto sm:max-w-xs sm:px-4 sm:py-2.5 sm:text-sm"
      >
        <svg class="h-4 w-4 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>

        <span class="hidden truncate text-center sm:inline sm:text-sm"
          >{{ formatDate(value.start) }} - {{ formatDate(value.end) }}</span
        >
      </button>
    </PopoverTrigger>
    <PopoverContent
      class="z-[1100] w-screen max-w-xs p-0 sm:w-auto sm:max-w-md"
      align="start"
      side="bottom"
    >
      <div class="flex flex-col space-y-4 p-4 sm:flex-row sm:space-y-0 sm:space-x-6 sm:p-6">
        <div class="w-full sm:w-48">
          <h4 class="mb-3 text-sm font-semibold text-gray-800 sm:mb-4 sm:text-base">
            Quick Select
          </h4>
          <ul class="space-y-2">
            <li
              v-for="preset in datePresets"
              :key="preset.label"
              @click="updateDateRange(preset.range)"
              class="w-full cursor-pointer rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-left text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 sm:w-auto sm:px-4 sm:py-2.5 sm:text-sm"
            >
              {{ preset.label }}
            </li>
          </ul>
        </div>
        <div class="w-full sm:w-80">
          <h4 class="mb-3 text-sm font-semibold text-gray-800 sm:mb-4 sm:text-base">
            Custom Range
          </h4>
          <RangeCalendar
            v-model="value"
            class="w-full rounded-md border border-gray-300 sm:w-auto"
            :number-of-months="1"
            @update:model-value="updateDateRange"
          />
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>
