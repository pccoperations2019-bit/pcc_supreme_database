<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Icon } from '@iconify/vue'
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useAuth } from '@/composable/useAuth'

const props = defineProps({
  period: { type: Object, required: true },
  byCenter: { type: Array, default: () => [] },
  byProduct: { type: Array, default: () => [] },
  centers: { type: Array, default: () => [] },
  months: { type: Object, default: () => ({}) },
  totals: { type: Object, default: () => ({}) },
  years: { type: Array, default: () => [] },
})

const { hasPermission } = useAuth()

const currentQuery = () => Object.fromEntries(new URLSearchParams(window.location.search))

const filters = computed(() => {
  const query = currentQuery()

  return {
    from_year: query.from_year || String(props.period.from.year),
    from_month: query.from_month || String(props.period.from.month),
    to_year: query.to_year || String(props.period.to.year),
    to_month: query.to_month || String(props.period.to.month),
    center_id: query.center_id || '',
  }
})

const months = computed(() =>
  Object.entries(props.months).map(([value, label]) => ({ value: String(value), label }))
)

const years = computed(() => {
  const active = [Number(filters.value.from_year), Number(filters.value.to_year)]

  return [...new Set([...(props.years || []), ...active])].sort((a, b) => b - a)
})

const visit = () => {
  router.get(route('monthly-tasks.reports.totals'), filters.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const submit = () => visit()

const exportCsv = () => {
  const header = ['Center', 'Program', 'Period', 'Product', 'Type', 'Rows', 'Volume']

  const lines = props.byProduct.map(row =>
    [
      row.center_name,
      row.program_label,
      row.period_label,
      row.product_label,
      row.task_type_label,
      row.row_count,
      row.volume_formatted,
    ]
      .map(value => `"${String(value ?? '').replace(/"/g, '""')}"`)
      .join(',')
  )

  const blob = new Blob([[header.join(','), ...lines].join('\n')], {
    type: 'text/csv;charset=utf-8;',
  })

  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = `monthly-task-totals-${filters.value.from_year}-${filters.value.to_year}.csv`
  link.click()

  URL.revokeObjectURL(url)
}

const grouped = computed(() => {
  const groups = new Map()

  props.byProduct.forEach(row => {
    const key = `${row.center_id}|${row.period_label}|${row.program_label}`

    if (!groups.has(key)) {
      groups.set(key, {
        center_id: row.center_id,
        center_name: row.center_name,
        period_label: row.period_label,
        program_label: row.program_label,
        rows: [],
      })
    }

    groups.get(key).rows.push(row)
  })

  return [...groups.values()]
})

const hasResults = computed(() => props.byProduct.length > 0)
</script>

<template>
  <Head title="Production & Sales Totals" />
  <DashboardLayout>
    <div class="flex flex-col gap-6 rounded-lg bg-white px-6 py-12 text-black shadow-sm">
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">
            Production &amp; Sales Totals
          </h1>
          <p class="text-sm text-slate-600 sm:text-base">
            What has actually been entered, read from the production and sales tables.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <Button as-child variant="outline" class="cursor-pointer">
            <Link :href="route('monthly-tasks.index')">
              <Icon icon="lucide:list-checks" class="mr-2 h-4 w-4" />
              Back to tasks
            </Link>
          </Button>
          <Button
            v-if="hasResults && hasPermission('export-data')"
            variant="outline"
            class="cursor-pointer"
            @click="exportCsv"
          >
            <Icon icon="lucide:download" class="mr-2 h-4 w-4" />
            Export CSV
          </Button>
        </div>
      </div>

      <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label="Totals">
        <div class="rounded-lg border border-slate-200 px-4 py-3">
          <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Produced</dt>
          <dd class="mt-1 text-2xl font-semibold text-slate-900">
            {{ totals.production ?? '0.00' }}
          </dd>
        </div>
        <div class="rounded-lg border border-slate-200 px-4 py-3">
          <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Sold</dt>
          <dd class="mt-1 text-2xl font-semibold text-emerald-700">{{ totals.sales ?? '0.00' }}</dd>
        </div>
        <div class="rounded-lg border border-slate-200 px-4 py-3">
          <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Rows entered</dt>
          <dd class="mt-1 text-2xl font-semibold text-slate-900">{{ totals.rows ?? 0 }}</dd>
        </div>
      </dl>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Filters</CardTitle>
          <CardDescription>
            The period is applied in the database before anything is summed.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form class="grid grid-cols-1 gap-4 md:grid-cols-6" @submit.prevent="submit">
            <div class="space-y-2">
              <Label for="totals_from_year">From year</Label>
              <Select v-model="filters.from_year">
                <SelectTrigger id="totals_from_year" class="h-9 w-full">
                  <SelectValue placeholder="Year" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="year in years" :key="year" :value="String(year)">
                    {{ year }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="totals_from_month">From month</Label>
              <Select v-model="filters.from_month">
                <SelectTrigger id="totals_from_month" class="h-9 w-full">
                  <SelectValue placeholder="Month" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="month in months" :key="month.value" :value="month.value">
                    {{ month.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="totals_to_year">To year</Label>
              <Select v-model="filters.to_year">
                <SelectTrigger id="totals_to_year" class="h-9 w-full">
                  <SelectValue placeholder="Year" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="year in years" :key="year" :value="String(year)">
                    {{ year }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="totals_to_month">To month</Label>
              <Select v-model="filters.to_month">
                <SelectTrigger id="totals_to_month" class="h-9 w-full">
                  <SelectValue placeholder="Month" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="month in months" :key="month.value" :value="month.value">
                    {{ month.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="totals_center">Center</Label>
              <Select v-model="filters.center_id">
                <SelectTrigger id="totals_center" class="h-9 w-full">
                  <SelectValue placeholder="All centers" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All centers</SelectItem>
                  <SelectItem v-for="center in centers" :key="center.id" :value="String(center.id)">
                    {{ center.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex items-end md:col-span-6">
              <Button
                type="submit"
                class="w-full cursor-pointer bg-black text-white hover:bg-black/90 sm:w-auto"
              >
                Apply
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <div
        v-for="group in grouped"
        :key="`${group.center_id}-${group.period_label}-${group.program_label}`"
        class="overflow-hidden rounded-lg border border-slate-200"
      >
        <div class="flex flex-wrap items-center gap-3 bg-slate-50 px-4 py-3">
          <h2 class="text-sm font-semibold text-slate-900">{{ group.center_name }}</h2>
          <span class="rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-800">
            {{ group.program_label }}
          </span>
          <span class="text-sm text-slate-600">{{ group.period_label }}</span>
        </div>

        <div class="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="bg-slate-50 font-semibold whitespace-nowrap text-slate-700">
                  Product
                </TableHead>
                <TableHead class="bg-slate-50 font-semibold whitespace-nowrap text-slate-700">
                  Type
                </TableHead>
                <TableHead class="bg-slate-50 font-semibold whitespace-nowrap text-slate-700">
                  Rows
                </TableHead>
                <TableHead class="bg-slate-50 font-semibold whitespace-nowrap text-slate-700">
                  Volume
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="row in group.rows" :key="`${row.product_type}-${row.task_type}`">
                <TableCell class="text-sm whitespace-nowrap text-slate-700">
                  {{ row.product_label }}
                </TableCell>
                <TableCell class="text-sm whitespace-nowrap text-slate-700">
                  {{ row.task_type_label }}
                </TableCell>
                <TableCell class="text-sm whitespace-nowrap text-slate-600">
                  {{ row.row_count }}
                </TableCell>
                <TableCell class="text-sm font-medium whitespace-nowrap text-slate-900">
                  {{ row.volume_formatted }}
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </div>

      <div v-if="!hasResults" class="rounded-lg border border-slate-200 px-4 py-12 text-center">
        <div class="flex flex-col items-center justify-center gap-2">
          <Icon icon="lucide:chart-area" class="h-10 w-10 text-slate-300" />
          <h3 class="text-lg font-medium text-slate-900">Nothing entered for this period</h3>
          <p class="text-slate-500">Try widening the range or clearing the center filter.</p>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
