<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { VisDonut, VisSingleContainer } from '@unovis/vue'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { Download as DownloadIcon } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import axios from 'axios'
import { toast } from 'vue-sonner'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const props = defineProps({
  dashboard: { type: Object, required: true },
  baseRoute: { type: String, default: 'milk-productions.index' },
})

const page = usePage()

const reportType = ref(props.dashboard.report_type || 'production')
const year = ref(
  props.dashboard.year || props.dashboard.available_years?.[0] || new Date().getFullYear()
)
const month = ref(props.dashboard.month || new Date().getMonth() + 1)

watch(
  () => props.dashboard,
  value => {
    reportType.value = value.report_type || 'production'
    year.value = value.year || value.available_years?.[0] || new Date().getFullYear()
    month.value = value.month || new Date().getMonth() + 1
  }
)

const weeks = computed(() => props.dashboard.weeks || [])
const rows = computed(() => props.dashboard.rows || [])

const numberOrZero = value => Number(value || 0)

const totalAnnualTarget = computed(() =>
  rows.value.reduce((sum, row) => sum + numberOrZero(row.annual_target), 0)
)

const totalAnnualAccomplishment = computed(() =>
  rows.value.reduce((sum, row) => sum + numberOrZero(row.annual_accomplishment), 0)
)

const percentageAccomplishment = computed(() => {
  const target = totalAnnualTarget.value
  if (!target) return 0
  return Math.min((totalAnnualAccomplishment.value / target) * 100, 100)
})

const totalBalance = computed(() => totalAnnualTarget.value - totalAnnualAccomplishment.value)

const overallPieData = computed(() => [
  {
    label: 'Cumulative Annual Accomplishment',
    value: Math.max(totalAnnualAccomplishment.value, 0),
  },
  { label: 'Balance Annual Accomplishment', value: Math.max(totalBalance.value, 0) },
])

const centerSummary = computed(() =>
  rows.value.map(row => {
    const annualTarget = numberOrZero(row.annual_target)
    const annualAccomplishment = numberOrZero(row.annual_accomplishment)
    const rawPercent = annualTarget > 0 ? (annualAccomplishment / annualTarget) * 100 : 0
    return {
      center_code: row.center_code,
      annual_target: annualTarget,
      annual_accomplishment: annualAccomplishment,
      balance: annualTarget - annualAccomplishment,
      cumulative_percent: Math.min(rawPercent, 100),
    }
  })
)

const getCurrentParams = () => Object.fromEntries(new URLSearchParams(window.location.search))

const updateDashboard = () => {
  const params = getCurrentParams()
  params._view = 'dashboard'
  params.dashboard_report_type = reportType.value
  params.dashboard_year = year.value
  params.dashboard_month = month.value

  router.get(route(props.baseRoute), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const queueExportPDF = async () => {
  try {
    const response = await axios.post('/milk-productions/export/pdf', {
      dashboard_report_type: reportType.value,
      dashboard_year: year.value,
      dashboard_month: month.value,
      filter: page.props.ziggy?.query?.filter || {},
    })

    window.dispatchEvent(
      new window.CustomEvent('report-export-queued', {
        detail: {
          documentId: response.data.document_id,
          reportType: 'milk-production-dashboard',
          format: 'pdf',
          exportType: null,
          status: 'processing',
          fileName: null,
          fileSize: 0,
          downloadUrl: null,
        },
      })
    )
  } catch (error) {
    console.error('Error queueing dashboard export:', error)
    console.error('Error response:', error.response)
    console.error('Error message:', error.message)
    toast.error('Unable to queue export right now.')
  }
}

const months = [
  { value: 1, label: 'January' },
  { value: 2, label: 'February' },
  { value: 3, label: 'March' },
  { value: 4, label: 'April' },
  { value: 5, label: 'May' },
  { value: 6, label: 'June' },
  { value: 7, label: 'July' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'October' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
]

const years = computed(() => props.dashboard.available_years || [])

const formatNumber = value =>
  Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })
const formatPercent = value =>
  `${Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}%`

const monthWeekRangeLabel = week => {
  const weekData = (props.dashboard.week_ranges || {})[week]
  return weekData ? weekData.label : '—'
}

// Dynamic min-width for table: base cols + week cols
const tableMinWidth = computed(() => {
  const baseColsPx = 9 * 110 // 9 fixed columns × ~110px each
  const weekColsPx = (weeks.value.length || 0) * 90
  return Math.max(800, baseColsPx + weekColsPx)
})

// Bar chart: scale bar heights relative to max target
const maxTarget = computed(() => Math.max(...centerSummary.value.map(c => c.annual_target), 1))

const BAR_MAX_HEIGHT = 160 // px – tallest possible bar

const barHeight = value => Math.max(4, (value / maxTarget.value) * BAR_MAX_HEIGHT)
</script>

<template>
  <div class="space-y-4 sm:space-y-6">
    <!-- ── Header / Controls ── -->
    <div class="rounded-lg border border-slate-200 bg-white p-3 sm:p-6">
      <Tabs v-model="reportType" class="bordered mb-3 w-fit" @update:model-value="updateDashboard">
        <TabsList class="h-8 sm:h-9">
          <TabsTrigger value="production" class="px-2 text-xs sm:px-3 sm:text-sm"
            >Production</TabsTrigger
          >
          <TabsTrigger value="sales" class="px-2 text-xs sm:px-3 sm:text-sm">Sales</TabsTrigger>
        </TabsList>
      </Tabs>

      <!-- Title row + controls -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 class="text-base font-semibold text-slate-900 sm:text-lg">
            Milk Production Dashboard
          </h2>
          <p class="mt-1 text-xs text-slate-600 sm:text-sm">
            Scorecard for {{ dashboard.month_label }} {{ dashboard.year }}.
          </p>
        </div>

        <!-- Year / Month / Export — full-width on mobile, inline on sm+ -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:gap-3">
          <div class="grid grid-cols-2 gap-2 sm:flex sm:gap-3">
            <div>
              <div class="text-xs font-medium text-slate-500">Year</div>
              <Select
                :model-value="String(year)"
                @update:model-value="
                  val => {
                    year = Number(val)
                    updateDashboard()
                  }
                "
              >
                <SelectTrigger class="mt-1 h-9 w-full sm:w-32">
                  <SelectValue placeholder="Select year" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="y in years" :key="y" :value="String(y)">
                    {{ y }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <div class="text-xs font-medium text-slate-500">Month</div>
              <Select
                :model-value="String(month)"
                @update:model-value="
                  val => {
                    month = Number(val)
                    updateDashboard()
                  }
                "
              >
                <SelectTrigger class="mt-1 h-9 w-full sm:w-44">
                  <SelectValue placeholder="Select month" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="m in months" :key="m.value" :value="String(m.value)">
                    {{ m.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <Button
            variant="secondary"
            title="Export dashboard as PDF"
            class="w-full sm:w-auto"
            @click="queueExportPDF"
          >
            <span>Export</span>
            <DownloadIcon class="ml-1 size-3.5 sm:size-4" />
          </Button>
        </div>
      </div>

      <!-- Formula notes -->
      <div class="mt-3 space-y-1 text-xs text-slate-500 sm:mt-4 sm:text-sm">
        <div>
          Monthly target formula:
          <span class="font-medium"
            >(Annual Target − Previous Month Cumulative Accomplishment) ÷ Remaining Months</span
          >
        </div>
        <div>
          Monthly rating: <span class="font-medium">(Monthly Total ÷ Monthly Target) × 100</span>
        </div>
      </div>
    </div>

    <!-- ── Scorecard Table ── -->
    <div class="w-full overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <table
        class="w-full border-collapse text-xs sm:text-sm"
        :style="{ minWidth: tableMinWidth + 'px' }"
      >
        <thead class="bg-slate-50 text-slate-700">
          <!-- Main header row -->
          <tr>
            <th class="border-b px-2 py-2 text-left whitespace-nowrap sm:px-3">Center</th>
            <th class="border-b px-2 py-2 text-right whitespace-nowrap sm:px-3">
              Cum. Monthly Target
            </th>
            <th class="border-b px-2 py-2 text-right whitespace-nowrap sm:px-3">Total</th>
            <th
              v-for="w in weeks"
              :key="w"
              class="border-b px-2 py-2 text-right whitespace-nowrap sm:px-3"
            >
              WK {{ w }}
            </th>
            <th class="border-b px-2 py-2 text-right whitespace-nowrap sm:px-3">Monthly Rating</th>
            <th class="border-b px-2 py-2 text-right whitespace-nowrap sm:px-3">Lead Rank</th>
            <th class="border-b px-2 py-2 text-right whitespace-nowrap sm:px-3">
              Cum. Accomp (Less Current Month)
            </th>
            <th class="border-b px-2 py-2 text-right whitespace-nowrap sm:px-3">Annual Target</th>
            <th class="border-b px-2 py-2 text-right whitespace-nowrap sm:px-3">Annual Accomp</th>
            <th class="border-b px-2 py-2 text-right whitespace-nowrap sm:px-3">% Accomp</th>
          </tr>
          <!-- Week-range sub-header -->
          <tr>
            <th
              class="border-b px-2 py-1 text-left text-xs font-normal text-slate-500 sm:px-3"
            ></th>
            <th class="border-b px-2 py-1 sm:px-3">&nbsp;</th>
            <th class="border-b px-2 py-1 sm:px-3">&nbsp;</th>
            <th
              v-for="w in weeks"
              :key="`range-${w}`"
              class="border-b px-2 py-1 text-right text-xs font-normal whitespace-nowrap text-slate-500 sm:px-3"
            >
              {{ monthWeekRangeLabel(w) }}
            </th>
            <th class="border-b px-2 py-1 sm:px-3">&nbsp;</th>
            <th class="border-b px-2 py-1 sm:px-3">&nbsp;</th>
            <th class="border-b px-2 py-1 sm:px-3">&nbsp;</th>
            <th class="border-b px-2 py-1 sm:px-3">&nbsp;</th>
            <th class="border-b px-2 py-1 sm:px-3">&nbsp;</th>
            <th class="border-b px-2 py-1 sm:px-3">&nbsp;</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="`${row.center_id}-${row.outlet_category || 'all'}`"
            class="hover:bg-slate-50"
          >
            <td class="border-b px-2 py-2 font-medium text-slate-900 sm:px-3">
              {{ row.center_code }}
            </td>
            <td class="border-b px-2 py-2 text-right tabular-nums sm:px-3">
              {{ formatNumber(row.cumulative_monthly_target) }}
            </td>
            <td class="border-b px-2 py-2 text-right tabular-nums sm:px-3">
              {{ formatNumber(row.monthly_total) }}
            </td>
            <td
              v-for="w in weeks"
              :key="w"
              class="border-b px-2 py-2 text-right tabular-nums sm:px-3"
            >
              {{ formatNumber(row.weeks?.[w] || 0) }}
            </td>
            <td class="border-b px-2 py-2 text-right tabular-nums sm:px-3">
              {{ row.monthly_rating?.toFixed?.(2) ?? '—' }}%
            </td>
            <td class="border-b px-2 py-2 text-right tabular-nums sm:px-3">{{ row.lead_rank }}</td>
            <td class="border-b px-2 py-2 text-right tabular-nums sm:px-3">
              {{ formatNumber(row.previous_month_cumulative_accomplishment) }}
            </td>
            <td class="border-b px-2 py-2 text-right tabular-nums sm:px-3">
              {{ formatNumber(row.annual_target) }}
            </td>
            <td class="border-b px-2 py-2 text-right tabular-nums sm:px-3">
              {{ formatNumber(row.annual_accomplishment) }}
            </td>
            <td class="border-b px-2 py-2 text-right tabular-nums sm:px-3">
              {{ row.annual_percent?.toFixed?.(2) ?? '—' }}%
            </td>
          </tr>

          <tr v-if="rows.length === 0">
            <td :colspan="3 + weeks.length + 7" class="px-3 py-10 text-center text-slate-500">
              No dashboard data for the selected period/filters.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Donut + Bar chart panel ── -->
    <div v-if="rows.length" class="w-full rounded-lg border border-slate-200 bg-white p-3 sm:p-6">
      <!--
        Mobile  : stack donut on top, bars below
        lg+     : side-by-side, donut ~35% / bars ~65%
      -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1.1fr,2fr] lg:gap-8">
        <!-- Donut chart -->
        <div class="flex flex-col items-center justify-center">
          <div class="relative mx-auto w-full max-w-[280px] sm:max-w-sm">
            <VisSingleContainer
              :data="overallPieData"
              :margin="{ top: 30, bottom: 30 }"
              class="h-56 w-full sm:h-72"
            >
              <VisDonut
                :value="d => d.value"
                :color="d => (d.label.includes('Cumulative') ? '#FBBF24' : '#22C55E')"
                :arc-width="38"
              />
            </VisSingleContainer>

            <!-- Centered label inside donut hole -->
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
              <div
                class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-blue-600 sm:h-36 sm:w-36"
              >
                <div class="text-xs font-semibold text-yellow-400 sm:text-sm">
                  {{ formatPercent(percentageAccomplishment) }}
                </div>
                <div class="text-sm font-bold text-white sm:text-lg">
                  {{ formatNumber(totalAnnualAccomplishment) }}
                </div>
              </div>
            </div>
          </div>

          <!-- Legend -->
          <div class="mt-4 flex justify-center gap-4 text-xs font-medium sm:mt-6 sm:gap-6">
            <div class="flex items-center gap-1.5">
              <span
                class="inline-block h-3.5 w-6 rounded-sm border border-yellow-700 bg-yellow-400"
              />
              <span>Accomp</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span
                class="inline-block h-3.5 w-6 rounded-sm border border-green-700 bg-green-500"
              />
              <span>Balance</span>
            </div>
          </div>
        </div>

        <!-- Bar chart per center -->
        <div class="flex flex-col items-center justify-center">
          <!-- Scrollable so many centers don't get squished -->
          <div class="w-full overflow-x-auto pb-2">
            <div
              class="flex items-end justify-center gap-1.5 border-b border-slate-300 pb-4 sm:gap-2"
              :style="{ minWidth: `${centerSummary.length * 52}px` }"
            >
              <div
                v-for="center in centerSummary"
                :key="center.center_code"
                class="flex w-10 shrink-0 flex-col items-center sm:w-12"
              >
                <TooltipProvider>
                  <Tooltip>
                    <TooltipTrigger as-child>
                      <div
                        class="flex w-full cursor-pointer flex-col justify-end bg-slate-100"
                        :style="{ height: BAR_MAX_HEIGHT + 'px' }"
                      >
                        <!-- Balance (green) stacked on top -->
                        <div
                          class="w-full bg-green-500 transition-all duration-300"
                          :style="{ height: barHeight(center.balance) + 'px' }"
                        />
                        <!-- Accomplishment (yellow) below -->
                        <div
                          class="w-full bg-yellow-400 transition-all duration-300"
                          :style="{ height: barHeight(center.annual_accomplishment) + 'px' }"
                        />
                      </div>
                    </TooltipTrigger>
                    <TooltipContent class="max-w-[200px] text-xs">
                      <div class="font-semibold">{{ center.center_code }}</div>
                      <div>Annual Target: {{ formatNumber(center.annual_target) }}</div>
                      <div>
                        Annual Accomplishment: {{ formatNumber(center.annual_accomplishment) }}
                      </div>
                      <div>Balance: {{ formatNumber(center.balance) }}</div>
                      <div>Cumulative %: {{ formatPercent(center.cumulative_percent) }}</div>
                    </TooltipContent>
                  </Tooltip>
                </TooltipProvider>

                <!-- Labels under each bar -->
                <div
                  class="mt-1 w-full bg-slate-800 px-0.5 py-0.5 text-center text-[8px] leading-tight text-white sm:text-[9px]"
                >
                  {{ formatNumber(center.balance) }}
                </div>
                <div
                  class="w-full bg-yellow-600 px-0.5 py-0.5 text-center text-[8px] leading-tight text-white sm:text-[9px]"
                >
                  {{ formatNumber(center.annual_accomplishment) }}
                </div>
                <div class="mt-1 text-center text-[8px] font-semibold text-slate-900 sm:text-[9px]">
                  {{ center.center_code }}
                </div>
              </div>
            </div>
          </div>

          <!-- Footer notes -->
          <div class="mt-2 w-full space-y-0.5 text-[9px] text-slate-600 sm:mt-3 sm:text-[10px]">
            <div>
              <span class="font-semibold">Percentage Accomplishment:</span>
              Total of Column R (cumulative accomplishment ÷ annual target, ceiling at 100%).
            </div>
            <div>
              <span class="font-semibold">Balance Annual Accomplishment:</span>
              Annual Target − Annual Accomplishment (cumulative).
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
