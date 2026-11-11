<script setup>
import { displayCenterLabel } from '@/lib/center'
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { valueUpdater } from '@/lib/utils'
import { MonthEnum } from '@/enums/MonthEnum'

import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

const props = defineProps({
  manureProductions: {
    type: Object,
    required: true,
  },
})

const formatVolume = volume => {
  const value = Number(volume ?? 0)
  if (!Number.isFinite(value)) return '0.00'
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

const monthColumns = Object.values(MonthEnum).map(month => ({
  accessorKey: `monthly_data.${month.label.substring(0, 3).toLowerCase()}`,
  header: month.label,
  cell: ({ row }) => {
    const monthlyData = row.original.monthly_data || {}
    const monthKey = month.label.substring(0, 3).toLowerCase()
    const value = monthlyData[monthKey] || 0
    return h('span', { class: 'text-sm text-slate-900' }, formatVolume(value))
  },
}))

const columns = [
  {
    accessorKey: 'center.code',
    header: () => 'Center',
    cell: ({ row }) => {
      const record = row.original
      const centerCode = record.center?.code
      return h('span', { class: 'text-sm text-slate-900' }, displayCenterLabel(centerCode))
    },
  },
  ...monthColumns,
  {
    id: 'grand_total',
    header: () => 'Grand Total',
    cell: ({ row }) =>
      h('span', { class: 'text-sm text-slate-900' }, formatVolume(row.original.total_volume || 0)),
  },
]

const monthlyTotals = computed(() => {
  const totals = {}
  const rows = props.manureProductions?.data || []
  Object.values(MonthEnum).forEach(month => {
    const monthKey = month.label.substring(0, 3).toLowerCase()
    totals[monthKey] = rows.reduce((sum, row) => {
      return sum + (parseFloat(row.monthly_data?.[monthKey]) || 0)
    }, 0)
  })
  totals.grandTotal = Object.values(totals).reduce((sum, val) => sum + val, 0)
  return totals
})

const columnVisibility = ref({})
const rowSelection = ref({})

const table = useVueTable({
  get data() {
    return props.manureProductions?.data || []
  },
  columns,
  getCoreRowModel: getCoreRowModel(),
  onColumnVisibilityChange: updaterOrValue => valueUpdater(updaterOrValue, columnVisibility),
  onRowSelectionChange: updaterOrValue => valueUpdater(updaterOrValue, rowSelection),
  state: {
    get columnVisibility() {
      return columnVisibility.value
    },
    get rowSelection() {
      return rowSelection.value
    },
  },
  manualSorting: true,
  manualFiltering: true,
  manualPagination: true,
})

const handlePagination = url => {
  if (url) {
    router.get(url, {}, { preserveState: true, preserveScroll: true })
  }
}
</script>

<template>
  <div
    class="mx-auto w-full max-w-full overflow-hidden rounded-lg border border-slate-200 bg-white"
  >
    <Table>
      <TableHeader>
        <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
          <TableHead
            v-for="header in headerGroup.headers"
            :key="header.id"
            class="bg-slate-50 font-semibold text-slate-700"
          >
            <FlexRender
              v-if="!header.isPlaceholder"
              :render="header.column.columnDef.header"
              :props="header.getContext()"
            />
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <template v-if="table.getRowModel().rows?.length">
          <TableRow
            v-for="row in table.getRowModel().rows"
            :key="row.id"
            :data-state="row.getIsSelected() && 'selected'"
            class="cursor-pointer border-none transition-colors hover:bg-slate-50"
          >
            <TableCell
              v-for="cell in row.getVisibleCells()"
              :key="cell.id"
              class="align-center border-none px-4 py-3"
            >
              <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
            </TableCell>
          </TableRow>
        </template>
        <TableRow v-else class="border-none hover:bg-transparent">
          <TableCell :colspan="columns.length" class="h-32 border-none text-center">
            <div class="flex flex-col items-center justify-center space-y-3">
              <Icon icon="lucide:database" class="h-12 w-12 text-slate-300" />
              <div class="text-center">
                <h3 class="mb-1 text-lg font-medium text-slate-900">
                  No manure production records found
                </h3>
                <p class="mb-4 text-slate-500">Adjust your filters to see more results.</p>
              </div>
            </div>
          </TableCell>
        </TableRow>
      </TableBody>
      <TableFooter v-if="table.getRowModel().rows?.length">
        <TableRow class="border-none bg-slate-50">
          <TableCell class="border-none px-4 py-3 font-semibold text-slate-700">Total</TableCell>
          <TableCell
            v-for="month in monthColumns"
            :key="month.accessorKey"
            class="border-none px-4 py-3 text-sm font-semibold text-slate-900"
          >
            {{ formatVolume(monthlyTotals[month.accessorKey.split('.').pop()] || 0) }}
          </TableCell>
          <TableCell class="border-none px-4 py-3 text-sm font-semibold text-slate-900">
            {{ formatVolume(monthlyTotals.grandTotal || 0) }}
          </TableCell>
        </TableRow>
      </TableFooter>
    </Table>
  </div>

  <div class="flex items-center justify-between pt-4 text-xs font-semibold uppercase">
    <div class="flex flex-wrap items-center gap-2 text-zinc-600">
      <span>
        Showing {{ manureProductions.from || 0 }}–{{ manureProductions.to || 0 }} of
        {{ manureProductions.total || 0 }}
      </span>
    </div>
    <div class="flex items-center space-x-2">
      <Button
        variant="ghost"
        size="xs"
        :disabled="!manureProductions.prev_page_url"
        @click="handlePagination(manureProductions.prev_page_url)"
      >
        <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
        Prev
      </Button>
      <Button
        variant="ghost"
        size="xs"
        :disabled="!manureProductions.next_page_url"
        @click="handlePagination(manureProductions.next_page_url)"
      >
        Next
        <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
      </Button>
    </div>
  </div>
</template>
