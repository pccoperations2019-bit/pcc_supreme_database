<script setup>
import { displayCenterLabel } from '@/lib/center'
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate } from '@/lib/utils'
import debounce from 'lodash/debounce'

import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuTrigger,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuLabel,
} from '@/components/ui/dropdown-menu'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Label } from '@/components/ui/label'

const props = defineProps({
  milkTraders: {
    type: Object,
    required: true,
  },
  centers: {
    type: Array,
    default: () => [],
  },
  partners: {
    type: Array,
    default: () => [],
  },
})

const page = usePage()

const getCurrentSort = () => {
  return page.props.ziggy?.query?.sort || new URLSearchParams(window.location.search).get('sort')
}

const handleSort = column => {
  const currentSort = getCurrentSort()
  let newSort = column

  if (currentSort === column) {
    newSort = `-${column}`
  } else if (currentSort === `-${column}`) {
    newSort = null
  }

  const params = { ...page.props.ziggy?.query }
  if (newSort) {
    params.sort = newSort
  } else {
    delete params.sort
  }

  router.get(route('milk-traders.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const getSortIcon = column => {
  const currentSort = getCurrentSort()
  if (currentSort === column) return 'lucide:chevron-up'
  if (currentSort === `-${column}`) return 'lucide:chevron-down'
  return 'lucide:chevrons-up-down'
}

const createSortableHeader = (label, column) => {
  return () =>
    h(
      Button,
      {
        variant: 'ghost',
        onClick: () => handleSort(column),
        class: 'p-0 h-auto font-medium',
      },
      () => [
        label,
        h(Icon, {
          icon: getSortIcon(column),
          class: 'ml-2 h-4 w-4',
        }),
      ]
    )
}

const searchParams = new URLSearchParams(window.location.search)
const currentFilters = reactive({
  [`filter[center_id]`]: searchParams.get('filter[center_id]'),
  [`filter[partner_id]`]: searchParams.get('filter[partner_id]'),
})

const currentFilterIndicator = computed(() => {
  return Object.keys(currentFilters).reduce((acc, key) => {
    if (currentFilters[key]) {
      acc[key] = currentFilters[key]
    }
    return acc
  }, {})
})

const filteredPartners = computed(() => {
  const selectedCenterId = currentFilters[`filter[center_id]`]
  if (!selectedCenterId) {
    return []
  }
  return props.partners.filter(partner => partner.center_id === Number(selectedCenterId))
})

const isPartnerFilterDisabled = computed(() => {
  return !currentFilters[`filter[center_id]`]
})

const handleFilter = debounce((key, value) => {
  const params = { ...page.props.ziggy?.query, ...currentFilters }

  if (key === 'center_id') {
    delete params[`filter[partner_id]`]
    delete currentFilters[`filter[partner_id]`]
  }

  if (value) {
    params[`filter[${key}]`] = value
    currentFilters[`filter[${key}]`] = value
  } else {
    delete params[`filter[${key}]`]
    delete currentFilters[`filter[${key}]`]
  }

  router.get(route('milk-traders.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const handlePagination = url => {
  if (url) {
    router.get(url, {}, { preserveState: true, preserveScroll: true })
  }
}

const MonthEnum = {
  1: 'Jan',
  2: 'Feb',
  3: 'Mar',
  4: 'Apr',
  5: 'May',
  6: 'Jun',
  7: 'Jul',
  8: 'Aug',
  9: 'Sep',
  10: 'Oct',
  11: 'Nov',
  12: 'Dec',
}

const getMonthName = month => {
  return MonthEnum[month] || month
}

const deleteMilkTraded = milkTraded => {
  if (confirm(`Are you sure you want to delete traded record ID: ${milkTraded.id}?`)) {
    router.delete(route('milk-traders.destroy', milkTraded.id))
  }
}

const formatVolume = volume => {
  if (!volume && volume !== 0) return '0.00'
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(volume)
}

const formatPeriod = (start, end) => {
  if (!start && !end) return 'N/A'
  if (start && end) {
    return `${formatDate(start)} - ${formatDate(end)}`
  }
  return formatDate(start || end)
}

const columns = [
  {
    accessorKey: 'center.code',
    header: createSortableHeader('Center', 'center_id'),
    cell: ({ row }) => {
      const milkTraded = row.original
      return h('div', { class: 'flex flex-col space-y-1' }, [
        h('p', { class: 'text-sm font-medium text-slate-900' }, milkTraded.center?.name || 'N/A'),
        h('p', { class: 'text-xs text-slate-500' }, milkTraded.center?.code || 'N/A'),
      ])
    },
  },
  {
    accessorKey: 'partner.name',
    header: createSortableHeader('Partner', 'partner_id'),
    cell: ({ row }) => {
      const milkTraded = row.original
      return h('div', { class: 'flex flex-col space-y-1' }, [
        h('p', { class: 'text-sm font-medium text-slate-900' }, milkTraded.partner?.name || 'N/A'),
        h('p', { class: 'text-xs text-slate-500' }, milkTraded.partner?.code || ''),
      ])
    },
  },
  {
    accessorKey: 'year',
    header: createSortableHeader('Year', 'year'),
    cell: ({ row }) => {
      const year = row.original.year
      return h('span', { class: 'text-sm text-slate-900' }, year || 'N/A')
    },
  },
  {
    accessorKey: 'week',
    header: createSortableHeader('Week', 'week'),
    cell: ({ row }) => {
      const week = row.original.week
      return h('span', { class: 'text-sm text-slate-900' }, week ? `Week ${week}` : 'N/A')
    },
  },
  {
    accessorKey: 'month',
    header: createSortableHeader('Month', 'month'),
    cell: ({ row }) => {
      const month = row.original.month
      if (!month) return h('span', { class: 'text-sm text-slate-500' }, 'N/A')
      const monthName = getMonthName(month)
      return h('span', { class: 'text-sm text-slate-900' }, monthName)
    },
  },
  {
    accessorKey: 'transaction_date',
    header: createSortableHeader('Transaction Date', 'transaction_date'),
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        formatDate(row.getValue('transaction_date'))
      )
    },
  },
  {
    accessorKey: 'period',
    header: () => h('span', { class: 'font-medium' }, 'Period'),
    cell: ({ row }) => {
      const milkTraded = row.original
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        formatPeriod(milkTraded.period_start, milkTraded.period_end)
      )
    },
  },
  {
    accessorKey: 'total_volume_traded',
    header: createSortableHeader('Total Volume Traded (kg)', 'total_volume_traded'),
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900 font-medium' },
        formatVolume(row.original.total_volume_traded)
      )
    },
  },
  {
    accessorKey: 'created_at',
    header: createSortableHeader('Created', 'created_at'),
    cell: ({ row }) => {
      return h('span', { class: 'text-sm text-slate-500' }, formatDate(row.getValue('created_at')))
    },
  },
  {
    id: 'actions',
    enableHiding: true,
    cell: ({ row }) => {
      const milkTraded = row.original
      return h(
        DropdownMenu,
        {},
        {
          default: () => [
            h(
              DropdownMenuTrigger,
              { asChild: true },
              {
                default: () =>
                  h(
                    Button,
                    { variant: 'ghost', class: 'h-8 w-8 p-0' },
                    {
                      default: () => h(Icon, { icon: 'lucide:more-horizontal', class: 'h-4 w-4' }),
                    }
                  ),
              }
            ),
            h(
              DropdownMenuContent,
              { align: 'end', class: 'w-56 bg-white' },
              {
                default: () => [
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () => router.visit(route('milk-traders.show', milkTraded.id)),
                      class: 'cursor-pointer',
                    },
                    {
                      default: () => [
                        h(Icon, { icon: 'lucide:eye', class: 'mr-2 h-4 w-4' }),
                        'View Details',
                      ],
                    }
                  ),
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () => router.visit(route('milk-traders.edit', milkTraded.id)),
                      class: 'cursor-pointer',
                    },
                    {
                      default: () => [
                        h(Icon, { icon: 'lucide:pencil', class: 'mr-2 h-4 w-4' }),
                        'Edit Traded',
                      ],
                    }
                  ),
                  h(DropdownMenuSeparator),
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () => deleteMilkTraded(milkTraded),
                      class: 'cursor-pointer text-red-600 focus:text-red-600',
                    },
                    {
                      default: () => [
                        h(Icon, { icon: 'lucide:trash-2', class: 'mr-2 h-4 w-4' }),
                        'Delete Traded',
                      ],
                    }
                  ),
                ],
              }
            ),
          ],
        }
      )
    },
  },
]

const columnVisibility = ref({})
const rowSelection = ref({})

const table = useVueTable({
  get data() {
    return props.milkTraders.data || []
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
</script>

<template>
  <div class="space-y-6 bg-white">
    <div class="flex items-center justify-end">
      <div class="flex items-center space-x-2">
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm">
              <Icon icon="lucide:filter" class="mr-2 h-4 w-4" />
              Filters
              <span
                v-show="Object.keys(currentFilterIndicator).length > 0"
                class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800"
              >
                {{ Object.keys(currentFilterIndicator).length }}
              </span>
              <Icon icon="lucide:chevron-down" class="ml-2 h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-80 bg-white">
            <DropdownMenuLabel>Filters</DropdownMenuLabel>
            <DropdownMenuSeparator class="border-b border-slate-100" />
            <div class="space-y-3 p-3">
              <div>
                <Label for="center-filter" class="text-xs">Center</Label>
                <select
                  id="center-filter"
                  class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm"
                  :value="currentFilters[`filter[center_id]`] || ''"
                  @change="handleFilter('center_id', $event.target.value || null)"
                >
                  <option value="">All Centers</option>
                  <option v-for="center in centers" :key="center.id" :value="center.id">
                    {{ displayCenterLabel(center.code) }}
                  </option>
                </select>
              </div>
              <div>
                <Label for="partner-filter" class="text-xs">Partner</Label>
                <select
                  id="partner-filter"
                  class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                  :value="currentFilters[`filter[partner_id]`] || ''"
                  :disabled="isPartnerFilterDisabled"
                  @change="handleFilter('partner_id', $event.target.value || null)"
                >
                  <option value="">
                    {{ isPartnerFilterDisabled ? 'Select a center first' : 'All Partners' }}
                  </option>
                  <option v-for="partner in filteredPartners" :key="partner.id" :value="partner.id">
                    {{ partner.name }}
                  </option>
                </select>
              </div>
            </div>
          </DropdownMenuContent>
        </DropdownMenu>
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm">
              <Icon icon="lucide:columns" class="mr-2 h-4 w-4" />
              Columns
              <Icon icon="lucide:chevron-down" class="ml-2 h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-56 bg-white">
            <DropdownMenuCheckboxItem
              v-for="column in table.getAllColumns().filter(column => column.getCanHide())"
              :key="column.id"
              class="capitalize"
              :model-value="column.getIsVisible()"
              @update:model-value="value => column.toggleVisibility(!!value)"
            >
              {{ column.id.replace('_', ' ') }}
            </DropdownMenuCheckboxItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <Link
          :href="route('milk-traders.create')"
          as="button"
          class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          New Traded
        </Link>
      </div>
    </div>

    <div
      class="w-full overflow-x-auto [--tw-scroll-target:table] [-webkit-overflow-scrolling:touch] md:overflow-visible"
    >
      <Table>
        <TableHeader>
          <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
            <TableHead
              v-for="header in headerGroup.headers"
              :key="header.id"
              class="font-semibold text-slate-700"
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
              @click="router.visit(route('milk-traders.show', row.original.id))"
            >
              <TableCell
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                class="align-center border-none"
                @click.stop="cell.column.id === 'actions' ? null : undefined"
              >
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>

          <TableRow v-else class="border-none hover:bg-transparent">
            <TableCell :colspan="columns.length" class="h-32 border-none text-center">
              <div class="flex flex-col items-center justify-center space-y-3">
                <Icon icon="lucide:arrow-left-right" class="h-12 w-12 text-slate-300" />
                <div class="text-center">
                  <h3 class="mb-1 text-lg font-medium text-slate-900">No milk traded found</h3>
                  <p class="mb-4 text-slate-500">
                    Get started by adding your first milk traded record.
                  </p>
                  <Link
                    :href="route('milk-traders.create')"
                    as="button"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
                  >
                    <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
                    New Traded
                  </Link>
                </div>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div class="flex items-center justify-between pt-4 text-xs font-semibold uppercase">
      <div class="text-muted-foreground flex flex-wrap items-center gap-2 text-zinc-600">
        <span>
          Showing {{ milkTraders.from || 0 }}–{{ milkTraders.to || 0 }} of
          {{ milkTraders.total || 0 }}
        </span>
      </div>
      <div class="flex items-center space-x-2">
        <Button
          variant="ghost"
          size="xs"
          :disabled="!milkTraders.prev_page_url"
          @click="handlePagination(milkTraders.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="text-xs font-semibold text-zinc-600 uppercase">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="xs"
          :disabled="!milkTraders.next_page_url"
          @click="handlePagination(milkTraders.next_page_url)"
        >
          <span class="text-xs font-semibold text-zinc-600 uppercase">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
