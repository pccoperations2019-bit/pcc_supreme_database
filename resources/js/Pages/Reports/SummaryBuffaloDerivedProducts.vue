<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { BarChart } from '@/components/ui/chart-bar'
import { VisAxis } from '@unovis/vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const props = defineProps({
  milkProductionSummary: {
    type: Array,
    default: () => [],
  },
  reportColors: {
    type: Object,
    default: () => ({
      report: '#4F81BD',
      sum: '#9BBB59',
      target: '#C0504D',
      accomplishment: {
        high: '#22c55e',
        mid: '#facc15',
        low: '#ef4444',
      },
    }),
  },
  selectedCategory: {
    type: String,
    default: 'milk',
  },
  selectedReportType: {
    type: String,
    default: 'production',
  },
  selectedReportTypeLabel: {
    type: String,
    default: 'Production',
  },
  centerCodes: {
    type: Array,
    default: () => [],
  },
  hasYearParam: { type: Boolean, default: false },
  hasCategoryParam: { type: Boolean, default: false },
  hasReportTypeParam: { type: Boolean, default: false },
  years: { type: Array, default: () => [] },
  selectedYear: { type: Number, default: () => new Date().getFullYear() },
})

const localYear = ref(props.selectedYear ? String(props.selectedYear) : '')
const localCategory = ref(props.selectedCategory || 'milk')
const localReportType = ref(props.selectedReportType || 'production')
const hasYearParam = ref(Boolean(props.hasYearParam))
const hasCategoryParam = ref(Boolean(props.hasCategoryParam))
const hasReportTypeParam = ref(Boolean(props.hasReportTypeParam))

watch(
  () => props.selectedYear,
  newVal => {
    localYear.value = newVal ? String(newVal) : ''
  }
)
watch(
  () => props.selectedCategory,
  newVal => {
    localCategory.value = newVal || 'milk'
  }
)
watch(
  () => props.selectedReportType,
  newVal => {
    localReportType.value = newVal || 'production'
  }
)
watch(
  () => props.hasYearParam,
  newVal => {
    hasYearParam.value = Boolean(newVal)
  }
)
watch(
  () => props.hasCategoryParam,
  newVal => {
    hasCategoryParam.value = Boolean(newVal)
  }
)
watch(
  () => props.hasReportTypeParam,
  newVal => {
    hasReportTypeParam.value = Boolean(newVal)
  }
)

const categoryLabel = computed(() => {
  const value = (localCategory.value || 'milk').toLowerCase()
  return value.charAt(0).toUpperCase() + value.slice(1)
})

const formatNumber = value =>
  Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const computeAchievement = (total, goal) => {
  const totalNum = Number(total || 0)
  const goalNum = Number(goal || 0)
  if (!goalNum) return '0.00'
  return ((totalNum / goalNum) * 100).toFixed(2)
}

const computeAchievementValue = (total, goal) => {
  const totalNum = Number(total || 0)
  const goalNum = Number(goal || 0)
  return goalNum ? (totalNum / goalNum) * 100 : 0
}

const achievementColor = percent => {
  if (percent >= 95) return props.reportColors.accomplishment.high
  if (percent >= 50) return props.reportColors.accomplishment.mid
  return props.reportColors.accomplishment.low
}

const displaySummary = computed(() => {
  const summary = props.milkProductionSummary || []
  if (summary.length) return summary
  return (props.centerCodes || []).map(code => ({
    center_code: code,
    ih_total_volume_produced: 0,
    cbed_total_volume_produced: 0,
    total_volume_produced: 0,
    annual_goal: 0,
  }))
})

const totals = computed(() =>
  (displaySummary.value || []).reduce(
    (acc, item) => {
      acc.ih_total_volume_produced += Number(item.ih_total_volume_produced || 0)
      acc.cbed_total_volume_produced += Number(item.cbed_total_volume_produced || 0)
      acc.total_volume_produced += Number(item.total_volume_produced || 0)
      acc.annual_goal += Number(item.annual_goal || 0)
      return acc
    },
    {
      ih_total_volume_produced: 0,
      cbed_total_volume_produced: 0,
      total_volume_produced: 0,
      annual_goal: 0,
    }
  )
)

const chartData = computed(() =>
  (displaySummary.value || []).map(item => ({
    centerCode: item.center_code,
    'Total Volume': Number(item.total_volume_produced || 0),
    'Target Volume': Number(item.annual_goal || 0),
  }))
)

const chartCategories = ['Total Volume', 'Target Volume']
const chartColors = [props.reportColors.sum, props.reportColors.target]

const buildParams = () => {
  const params = {}
  if (hasYearParam.value) params.year = localYear.value
  if (hasCategoryParam.value) params.category = localCategory.value || 'milk'
  if (hasReportTypeParam.value) params.reportType = localReportType.value || 'production'
  return params
}

const navigate = (extraParams = {}) => {
  router.get(
    window.route('summary-buffalo-derived-products'),
    { ...buildParams(), ...extraParams },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

const handleYearChange = val => {
  localYear.value = val
  hasYearParam.value = true
  navigate({ year: val })
}

const handleReportTypeChange = value => {
  localReportType.value = value
  hasReportTypeParam.value = true
  navigate({ reportType: value })
}

const handleCategoryChange = value => {
  localCategory.value = value
  hasCategoryParam.value = true
  navigate({ category: value || 'milk' })
}
</script>

<template>
  <Head :title="`${categoryLabel} ${props.selectedReportTypeLabel} Summary`" />
  <DashboardLayout>
    <div class="px-3 py-4 sm:px-4 sm:py-6 md:px-6 md:py-8 lg:py-12">
      <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
        <div class="bg-white p-3 sm:p-4 md:p-6">
          <!-- ── Filters + Title ── -->
          <div class="mb-4 flex w-full flex-col gap-3">
            <!-- Filters row: stack on mobile, space-between on sm+ -->
            <div
              class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between"
            >
              <!-- Tab groups -->
              <div class="flex flex-col gap-2">
                <!-- Report type tabs -->
                <div class="overflow-x-auto">
                  <Tabs
                    v-model="localReportType"
                    class="bordered w-fit"
                    @update:model-value="handleReportTypeChange"
                  >
                    <TabsList class="h-8 sm:h-9">
                      <TabsTrigger value="production" class="px-2 text-xs sm:px-3 sm:text-sm"
                        >Production</TabsTrigger
                      >
                      <TabsTrigger value="sales" class="px-2 text-xs sm:px-3 sm:text-sm"
                        >Sales</TabsTrigger
                      >
                    </TabsList>
                  </Tabs>
                </div>
                <!-- Category tabs -->
                <div class="overflow-x-auto">
                  <Tabs
                    v-model="localCategory"
                    class="bordered w-fit"
                    @update:model-value="handleCategoryChange"
                  >
                    <TabsList class="h-8 sm:h-9">
                      <TabsTrigger value="milk" class="px-2 text-xs sm:px-3 sm:text-sm"
                        >Milk</TabsTrigger
                      >
                      <TabsTrigger value="meat" class="px-2 text-xs sm:px-3 sm:text-sm"
                        >Meat</TabsTrigger
                      >
                      <TabsTrigger value="manure" class="px-2 text-xs sm:px-3 sm:text-sm"
                        >Manure</TabsTrigger
                      >
                      <TabsTrigger value="hide" class="px-2 text-xs sm:px-3 sm:text-sm"
                        >Hide</TabsTrigger
                      >
                    </TabsList>
                  </Tabs>
                </div>
              </div>

              <!-- Year Select -->
              <div class="flex items-center gap-2">
                <label
                  for="year-filter"
                  class="text-xs font-medium whitespace-nowrap text-slate-700 sm:text-sm"
                >
                  Year
                </label>
                <Select :model-value="localYear" @update:model-value="handleYearChange">
                  <SelectTrigger id="year-filter" class="h-9 w-32 text-sm text-slate-900">
                    <SelectValue placeholder="Select year" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="year in years" :key="year" :value="String(year)">
                      {{ year }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <!-- Page title -->
            <div class="w-full text-center">
              <h2 class="text-base font-bold text-slate-900 sm:text-lg">
                {{ categoryLabel.toUpperCase() }}
                {{ props.selectedReportTypeLabel.toUpperCase() }} REPORTS {{ selectedYear }}
              </h2>
              <p class="text-xs text-slate-600 sm:text-sm">
                Summary of {{ categoryLabel.toLowerCase() }}
                {{ props.selectedReportTypeLabel.toLowerCase() }} accomplishments by center.
              </p>
            </div>
          </div>

          <!-- ── Cards ── -->
          <div class="grid grid-cols-1 gap-4 sm:gap-6 md:gap-8">
            <!-- Overall Totals -->
            <Card>
              <CardHeader class="pb-2 sm:pb-4">
                <CardTitle class="text-sm sm:text-base">Overall Total</CardTitle>
                <CardDescription class="text-xs sm:text-sm">
                  Totals across all centers for the selected filters
                </CardDescription>
              </CardHeader>
              <CardContent>
                <!--
                  2-col on mobile, 3-col on sm, 5-col on lg
                  Accomplishment % tile spans 2 cols on mobile so it doesn't orphan
                -->
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5">
                  <div class="rounded-md border border-slate-200 bg-slate-50 p-3 sm:p-4">
                    <div class="text-xs font-medium text-slate-600">Institutional Herd</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900 sm:text-base lg:text-lg">
                      {{ formatNumber(totals.ih_total_volume_produced) }}
                    </div>
                  </div>
                  <div class="rounded-md border border-slate-200 bg-slate-50 p-3 sm:p-4">
                    <div class="text-xs font-medium text-slate-600">CBED</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900 sm:text-base lg:text-lg">
                      {{ formatNumber(totals.cbed_total_volume_produced) }}
                    </div>
                  </div>
                  <div class="rounded-md border border-slate-200 bg-slate-50 p-3 sm:p-4">
                    <div class="text-xs font-medium text-slate-600">Total Accomplishment</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900 sm:text-base lg:text-lg">
                      {{ formatNumber(totals.total_volume_produced) }}
                    </div>
                  </div>
                  <div class="rounded-md border border-slate-200 bg-slate-50 p-3 sm:p-4">
                    <div class="text-xs font-medium text-slate-600">Target IH &amp; CBED</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900 sm:text-base lg:text-lg">
                      {{ formatNumber(totals.annual_goal) }}
                    </div>
                  </div>
                  <div
                    class="col-span-2 rounded-md border border-slate-200 p-3 text-white sm:col-span-1 sm:p-4"
                    :style="{
                      backgroundColor: achievementColor(
                        computeAchievementValue(totals.total_volume_produced, totals.annual_goal)
                      ),
                    }"
                  >
                    <div class="text-xs font-medium opacity-90">Accomplishment %</div>
                    <div class="mt-1 text-sm font-semibold sm:text-base lg:text-lg">
                      {{ computeAchievement(totals.total_volume_produced, totals.annual_goal) }}%
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Summary Table -->
            <Card>
              <CardHeader class="pb-2 sm:pb-4">
                <CardTitle class="text-sm sm:text-base">
                  {{ categoryLabel }} {{ props.selectedReportTypeLabel }} Summary
                </CardTitle>
                <CardDescription class="text-xs sm:text-sm">
                  Detailed breakdown of {{ categoryLabel.toLowerCase() }}
                  {{ props.selectedReportTypeLabel.toLowerCase() }} by center
                </CardDescription>
              </CardHeader>
              <CardContent class="p-0 sm:p-6 sm:pt-0">
                <!-- Horizontal scroll: 6 columns need ~660px minimum to breathe -->
                <div class="overflow-x-auto">
                  <Table class="w-full" style="min-width: 660px">
                    <TableHeader>
                      <TableRow>
                        <TableHead
                          class="border border-black text-center text-sm font-bold text-white"
                          :style="{ backgroundColor: reportColors.report }"
                          >Center</TableHead
                        >
                        <TableHead
                          class="border border-black text-center text-sm font-bold text-white"
                          :style="{ backgroundColor: reportColors.report }"
                          >Institutional Herd</TableHead
                        >
                        <TableHead
                          class="border border-black text-center text-sm font-bold text-white"
                          :style="{ backgroundColor: reportColors.report }"
                          >CBED</TableHead
                        >
                        <TableHead
                          class="border border-black text-center text-sm font-bold text-white"
                          :style="{ backgroundColor: reportColors.sum }"
                          >Total Accomplishment</TableHead
                        >
                        <TableHead
                          class="border border-black text-center text-sm font-bold text-white"
                          :style="{ backgroundColor: reportColors.target }"
                          >Target IH &amp; CBED</TableHead
                        >
                        <TableHead
                          class="border border-black text-center text-sm font-bold text-white"
                          :style="{ backgroundColor: reportColors.report }"
                          >% Accomplishment</TableHead
                        >
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      <TableRow v-for="(item, index) in displaySummary" :key="index">
                        <TableCell
                          class="border border-black text-center text-sm text-white"
                          :style="{ backgroundColor: reportColors.report }"
                          >{{ item.center_code }}</TableCell
                        >
                        <TableCell
                          class="border border-black text-center text-sm text-white"
                          :style="{ backgroundColor: reportColors.report }"
                          >{{ formatNumber(item.ih_total_volume_produced) }}</TableCell
                        >
                        <TableCell
                          class="border border-black text-center text-sm text-white"
                          :style="{ backgroundColor: reportColors.report }"
                          >{{ formatNumber(item.cbed_total_volume_produced) }}</TableCell
                        >
                        <TableCell
                          class="border border-black text-center text-sm text-white"
                          :style="{ backgroundColor: reportColors.sum }"
                          >{{ formatNumber(item.total_volume_produced) }}</TableCell
                        >
                        <TableCell
                          class="border border-black text-center text-sm text-white"
                          :style="{ backgroundColor: reportColors.target }"
                          >{{ formatNumber(item.annual_goal) }}</TableCell
                        >
                        <TableCell
                          class="border border-black text-center text-sm text-white"
                          :style="{
                            backgroundColor: achievementColor(
                              computeAchievementValue(item.total_volume_produced, item.annual_goal)
                            ),
                          }"
                          >{{
                            computeAchievement(item.total_volume_produced, item.annual_goal)
                          }}%</TableCell
                        >
                      </TableRow>
                    </TableBody>
                  </Table>
                </div>
              </CardContent>
            </Card>

            <!-- Chart -->
            <Card>
              <CardHeader class="pb-2 sm:pb-4">
                <CardTitle class="text-sm sm:text-base">
                  {{ categoryLabel }} {{ props.selectedReportTypeLabel }} vs Targets
                </CardTitle>
                <CardDescription class="text-xs sm:text-sm">
                  Total {{ props.selectedReportTypeLabel.toLowerCase() }} volume vs annual targets
                  across all centers
                </CardDescription>
              </CardHeader>
              <CardContent class="px-2 pb-4 sm:px-6">
                <!-- Scrollable chart wrapper: each center gets at least 80px, floor at 400px -->
                <div class="overflow-x-auto">
                  <div
                    class="h-[300px] sm:h-[400px] md:h-[500px] lg:h-[600px]"
                    :style="{ minWidth: `${Math.max(chartData.length * 80, 400)}px` }"
                  >
                    <BarChart
                      :data="chartData"
                      index="centerCode"
                      :categories="chartCategories"
                      :colors="chartColors"
                      :y-formatter="tick => formatNumber(tick) + ' kg'"
                      type="grouped"
                      class="h-full w-full"
                      :margin="{ top: 40, bottom: 80, left: 10, right: 10 }"
                      :show-x-axis="false"
                    >
                      <VisAxis
                        type="x"
                        :num-ticks="chartData.length"
                        :tick-format="v => chartData[v]?.centerCode"
                        :tick-text-angle="0"
                        :tick-line="false"
                        :grid-line="false"
                        tick-text-color="#64748b"
                      />
                    </BarChart>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
