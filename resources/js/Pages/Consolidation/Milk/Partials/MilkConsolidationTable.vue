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
  milkConsolidations: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
})

const page = usePage()

const handleSort = column => {
  const currentSort =
    page.props.ziggy?.query?.sort || new URLSearchParams(window.location.search).get('sort')

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

  router.get(route('milk-consolidations.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const searchParams = new URLSearchParams(window.location.search)
const currentFilters = reactive({
  [`filter[center_id]`]: searchParams.get('filter[center_id]'),
})

const currentFilterIndicator = computed(() => {
  return Object.keys(currentFilters).reduce((acc, key) => {
    if (currentFilters[key]) {
      acc[key] = currentFilters[key]
    }
    return acc
  }, {})
})

const handleFilter = debounce((key, value) => {
  const params = { ...page.props.ziggy?.query, ...currentFilters }

  if (value) {
    params[`filter[${key}]`] = value
    currentFilters[`filter[${key}]`] = value
  } else {
    delete params[`filter[${key}]`]
    delete currentFilters[`filter[${key}]`]
  }

  router.get(route('milk-consolidations.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const getSortIcon = column => {
  const currentSort =
    page.props.ziggy?.query?.sort || new URLSearchParams(window.location.search).get('sort')

  if (currentSort === column) {
    return 'lucide:chevron-up'
  } else if (currentSort === `-${column}`) {
    return 'lucide:chevron-down'
  }

  return 'lucide:chevrons-up-down'
}

const handlePagination = url => {
  if (url) {
    router.get(
      url,
      {},
      {
        preserveState: true,
        preserveScroll: true,
      }
    )
  }
}

const formatCurrency = value => {
  if (!value) return '₱0.00'
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

const formatVolume = volume => {
  if (!volume) return '0.000'
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
  }).format(volume)
}

const formatPrice = price => {
  if (!price) return '₱0.0000'
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 4,
    maximumFractionDigits: 4,
  }).format(price)
}

const columns = [
  {
    accessorKey: 'center.name',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('center_id'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Center',
          h(Icon, {
            icon: getSortIcon('center_id'),
            class: 'ml-2 h-4 w-4',
          }),
        ]
      )
    },
    cell: ({ row }) => {
      const consolidation = row.original
      return h('div', { class: 'flex flex-col space-y-1' }, [
        h(
          'p',
          { class: 'text-sm font-medium text-slate-900' },
          consolidation.center?.name || 'N/A'
        ),
        h('p', { class: 'text-xs text-slate-500' }, consolidation.center?.code || 'N/A'),
      ])
    },
  },
  {
    accessorKey: 'partner.name',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('partner_id'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Partner',
          h(Icon, {
            icon: getSortIcon('partner_id'),
            class: 'ml-2 h-4 w-4',
          }),
        ]
      )
    },
    cell: ({ row }) => {
      const consolidation = row.original
      return h('div', { class: 'flex flex-col space-y-1' }, [
        h(
          'p',
          { class: 'text-sm font-medium text-slate-900' },
          consolidation.partner?.name || 'N/A'
        ),
        h('p', { class: 'text-xs text-slate-500' }, consolidation.partner?.code || 'N/A'),
      ])
    },
  },
  {
    accessorKey: 'farmer',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('farmer_id'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Farmer',
          h(Icon, {
            icon: getSortIcon('farmer_id'),
            class: 'ml-2 h-4 w-4',
          }),
        ]
      )
    },
    cell: ({ row }) => {
      const consolidation = row.original
      if (!consolidation.farmer) {
        return h('div', { class: 'flex flex-col space-y-1' }, [
          h('p', { class: 'text-sm text-slate-500' }, 'N/A'),
        ])
      }
      const fullName = `${consolidation.farmer.first_name || ''} ${
        consolidation.farmer.last_name || ''
      }`.trim()
      return h('div', { class: 'flex flex-col space-y-1' }, [
        h('p', { class: 'text-sm font-medium text-slate-900' }, fullName || 'N/A'),
        h('p', { class: 'text-xs text-slate-500' }, consolidation.farmer?.code || 'N/A'),
      ])
    },
  },
  {
    accessorKey: 'period_start',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('period_start'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Period Start',
          h(Icon, {
            icon: getSortIcon('period_start'),
            class: 'ml-2 h-4 w-4',
          }),
        ]
      )
    },
    cell: ({ row }) => {
      return h('span', { class: 'text-sm text-slate-900' }, formatDate(row.original.period_start))
    },
  },
  {
    accessorKey: 'period_end',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('period_end'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Period End',
          h(Icon, {
            icon: getSortIcon('period_end'),
            class: 'ml-2 h-4 w-4',
          }),
        ]
      )
    },
    cell: ({ row }) => {
      return h('span', { class: 'text-sm text-slate-900' }, formatDate(row.original.period_end))
    },
  },
  {
    accessorKey: 'supplier.name',
    header: () => h('span', { class: 'font-medium' }, 'Supplier'),
    cell: ({ row }) => {
      const consolidation = row.original
      return h('span', { class: 'text-sm text-slate-900' }, consolidation.supplier?.name || 'N/A')
    },
  },
  {
    accessorKey: 'total_raw_milk',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('total_raw_milk'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Total Raw Milk (kg)',
          h(Icon, { icon: getSortIcon('total_raw_milk'), class: 'ml-2 h-4 w-4' }),
        ]
      )
    },
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900 font-medium' },
        formatVolume(row.original.total_raw_milk)
      )
    },
  },
  {
    accessorKey: 'total_from_center',
    header: () => h('span', { class: 'font-medium' }, 'From Center (kg)'),
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        formatVolume(row.original.total_from_center)
      )
    },
  },
  {
    accessorKey: 'total_from_partners',
    header: () => h('span', { class: 'font-medium' }, 'From Partners (kg)'),
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        formatVolume(row.original.total_from_partners)
      )
    },
  },
  {
    accessorKey: 'total_from_farmers',
    header: () => h('span', { class: 'font-medium' }, 'From Farmers (kg)'),
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        formatVolume(row.original.total_from_farmers)
      )
    },
  },
  {
    accessorKey: 'total_from_suppliers',
    header: () => h('span', { class: 'font-medium' }, 'From Suppliers (kg)'),
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        formatVolume(row.original.total_from_suppliers)
      )
    },
  },
  {
    accessorKey: 'average_buying_price',
    header: () => h('span', { class: 'font-medium' }, 'Avg Buying Price'),
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        formatPrice(row.original.average_buying_price)
      )
    },
  },
  {
    accessorKey: 'total_cost',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('total_cost'),
          class: 'p-0 h-auto font-medium',
        },
        () => ['Total Cost', h(Icon, { icon: getSortIcon('total_cost'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900 font-medium' },
        formatCurrency(row.original.total_cost)
      )
    },
  },
  {
    accessorKey: 'average_selling_price',
    header: () => h('span', { class: 'font-medium' }, 'Avg Selling Price'),
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        formatPrice(row.original.average_selling_price)
      )
    },
  },
  {
    accessorKey: 'revenue_generated',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('revenue_generated'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Revenue Generated',
          h(Icon, { icon: getSortIcon('revenue_generated'), class: 'ml-2 h-4 w-4' }),
        ]
      )
    },
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900 font-medium text-green-600' },
        formatCurrency(row.original.revenue_generated)
      )
    },
  },
  {
    accessorKey: 'processing_cost',
    header: () => h('span', { class: 'font-medium' }, 'Processing Cost'),
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        formatCurrency(row.original.processing_cost)
      )
    },
  },
  {
    accessorKey: 'created_at',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('created_at'),
          class: 'p-0 h-auto font-medium',
        },
        () => ['Created', h(Icon, { icon: getSortIcon('created_at'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      return h('span', { class: 'text-sm text-slate-500' }, formatDate(row.getValue('created_at')))
    },
  },
]

const columnVisibility = ref({})
const rowSelection = ref({})

const table = useVueTable({
  get data() {
    return props.milkConsolidations.data || []
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
    <!-- Header -->
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
                >{{ Object.keys(currentFilterIndicator).length }}</span
              >
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
                  :value="currentFilters[`filter[center_id]`]"
                  @change="handleFilter('center_id', $event.target.value)"
                >
                  <option value="">All Centers</option>
                  <option v-for="center in filters.centers" :key="center.id" :value="center.id">
                    {{ displayCenterLabel(center.code) }}
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
          :href="route('milk-consolidations.create')"
          as="button"
          class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          New Consolidation
        </Link>
      </div>
    </div>

    <!-- Table -->
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
            >
              <TableCell
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                class="align-center border-none"
              >
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>

          <TableRow v-else class="border-none hover:bg-transparent">
            <TableCell :colspan="columns.length" class="h-32 border-none text-center">
              <div class="flex flex-col items-center justify-center space-y-3">
                <Icon icon="lucide:droplet" class="h-12 w-12 text-slate-300" />
                <div class="text-center">
                  <h3 class="mb-1 text-lg font-medium text-slate-900">
                    No milk consolidations found
                  </h3>
                  <p class="mb-4 text-slate-500">
                    Get started by adding your first milk consolidation record.
                  </p>
                  <Link
                    :href="route('milk-consolidations.create')"
                    as="button"
                    class="inline-flex border-collapse items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
                  >
                    <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
                    New Consolidation
                  </Link>
                </div>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between pt-4 text-xs font-semibold uppercase">
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        <span>
          Showing {{ milkConsolidations.from || 0 }}–{{ milkConsolidations.to || 0 }}
          of
          {{ milkConsolidations.total || 0 }}
        </span>
      </div>
      <div class="flex items-center space-x-2">
        <Button
          variant="ghost"
          size="xs"
          :disabled="!milkConsolidations.prev_page_url"
          @click="handlePagination(milkConsolidations.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="text-xs font-semibold text-zinc-600 uppercase">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="xs"
          :disabled="!milkConsolidations.next_page_url"
          @click="handlePagination(milkConsolidations.next_page_url)"
        >
          <span class="text-xs font-semibold text-zinc-600 uppercase">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
