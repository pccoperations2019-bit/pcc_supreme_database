<script setup>
/* global URLSearchParams, route, confirm */
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import { valueUpdater } from '@/lib/utils'
import debounce from 'lodash/debounce'
import { useAuth } from '@/composable/useAuth'
import { monthsArray } from '@/enums/MonthEnum'
import DairyBoxSalesMonthlyTable from './DairyBoxSalesMonthlyTable.vue'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  DropdownMenu,
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

const page = usePage()
const { isCenterScoped } = useAuth()

const searchParams = new URLSearchParams(window.location.search)
const globalSearch = ref(searchParams.get('search') || '')
const currentFilters = reactive({
  [`filter[center_id]`]: searchParams.get('filter[center_id]'),
  [`filter[partner_id]`]: searchParams.get('filter[partner_id]'),
  [`filter[year]`]: searchParams.get('filter[year]'),
  [`filter[month]`]: searchParams.get('filter[month]'),
})

const getCurrentParams = () => Object.fromEntries(new URLSearchParams(window.location.search))
const navigationTabValue = ref('sales')
const searchParamsForView = new URLSearchParams(window.location.search)
const activeView = ref(searchParamsForView.get('_view') || 'data')

const handleViewModeChange = mode => {
  activeView.value = mode
  const params = Object.fromEntries(
    Object.entries(getCurrentParams()).filter(([_, v]) => v !== '' && v !== undefined && v !== null)
  )
  params._view = mode
  delete params.page

  router.get(route('dairy-box-sales.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const handleNavigationTabChange = value => {
  navigationTabValue.value = value
  if (value === 'production') {
    router.get('/dairy-boxes', {}, { preserveState: false })
  }
}

const currentFilterIndicator = computed(() => {
  return Object.keys(currentFilters).reduce((acc, key) => {
    if (currentFilters[key]) {
      acc[key] = currentFilters[key]
    }
    return acc
  }, {})
})

const openFilterSection = ref(null)

const toggleFilterSection = section => {
  openFilterSection.value = openFilterSection.value === section ? null : section
}

const handleFilter = debounce((key, value) => {
  const params = Object.fromEntries(
    Object.entries(getCurrentParams()).filter(([_, v]) => v !== '' && v !== undefined && v !== null)
  )

  if (value) {
    params[`filter[${key}]`] = value
    currentFilters[`filter[${key}]`] = value
  } else {
    delete params[`filter[${key}]`]
    delete currentFilters[`filter[${key}]`]
  }

  router.get(route('dairy-box-sales.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const handleGlobalSearch = debounce(value => {
  const params = Object.fromEntries(
    Object.entries(getCurrentParams()).filter(([_, v]) => v !== '' && v !== undefined && v !== null)
  )

  if (value) {
    params.search = value
  } else {
    delete params.search
  }

  router.get(route('dairy-box-sales.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const props = defineProps({
  dairyBoxSales: {
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
  years: {
    type: Array,
    default: () => [],
  },
})

const formatCurrency = value => {
  const num = Number(value ?? 0)
  if (!Number.isFinite(num)) return '0.00'
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num)
}

const deleteDairyBoxSale = dairyBoxSale => {
  if (confirm(`Are you sure you want to delete this dairy box sale record?`)) {
    router.delete(route('dairy-box-sales.destroy', { dairy_box_sale: dairyBoxSale.id }))
  }
}

const columns = [
  {
    accessorKey: 'dairy_box_id',
    header: () => 'Dairy Box ID',
    cell: ({ row }) => {
      const record = row.original
      return h('span', { class: 'text-sm text-slate-900' }, record.dairy_box?.dairy_box_id || 'N/A')
    },
  },
  {
    accessorKey: 'business_name',
    header: () => 'Business Name',
    cell: ({ row }) => {
      const record = row.original
      const businessName = record.dairy_box?.business_name || record.business_name || 'N/A'
      return h('span', { class: 'text-sm text-slate-900' }, businessName)
    },
  },
  {
    accessorKey: 'partner.name',
    header: () => 'Partner',
    cell: ({ row }) => {
      const record = row.original
      return h('span', { class: 'text-sm text-slate-900' }, record.partner?.name || 'N/A')
    },
  },
  {
    accessorKey: 'month',
    header: () => 'Month',
    cell: ({ row }) => {
      const month = row.original.month
      const found = month ? monthsArray.find(m => m.value === month) : null
      return h('span', { class: 'text-sm text-slate-900' }, found?.label || 'N/A')
    },
  },
  {
    accessorKey: 'total_sales',
    header: 'Total Sales',
    cell: ({ row }) => {
      return h(
        'span',
        { class: 'text-sm font-medium text-slate-900' },
        formatCurrency(row.original.total_sales)
      )
    },
  },
  {
    id: 'actions',
    enableHiding: true,
    cell: ({ row }) => {
      const dairyBoxSale = row.original
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
                      onClick: () =>
                        router.visit(
                          route('dairy-box-sales.show', { dairy_box_sale: dairyBoxSale.id })
                        ),
                      class: 'cursor-pointer',
                    },
                    {
                      default: () => [
                        h(Icon, { icon: 'lucide:eye', class: 'mr-2 h-4 w-4' }),
                        'View',
                      ],
                    }
                  ),
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () =>
                        router.visit(
                          route('dairy-box-sales.edit', { dairy_box_sale: dairyBoxSale.id })
                        ),
                      class: 'cursor-pointer',
                    },
                    {
                      default: () => [
                        h(Icon, { icon: 'lucide:pencil', class: 'mr-2 h-4 w-4' }),
                        'Edit',
                      ],
                    }
                  ),
                  h(DropdownMenuSeparator),
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () => deleteDairyBoxSale(dairyBoxSale),
                      class: 'cursor-pointer text-red-600 focus:text-red-600',
                    },
                    {
                      default: () => [
                        h(Icon, { icon: 'lucide:trash-2', class: 'mr-2 h-4 w-4' }),
                        'Delete Sale',
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
    return props.dairyBoxSales?.data || []
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
    class="space-y-4 rounded-lg bg-white px-4 py-6 text-black shadow-sm sm:space-y-6 sm:px-6 sm:py-12"
  >
    <!-- Header -->
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Dairy Box Sales</h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Manage dairy box sales records and information.
        </p>
      </div>
      <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-4">
        <Tabs
          v-model="navigationTabValue"
          class="bordered w-fit"
          @update:model-value="handleNavigationTabChange"
        >
          <TabsList class="h-8 sm:h-9">
            <TabsTrigger value="production" class="px-2 text-xs sm:px-3 sm:text-sm"
              >DairyBox</TabsTrigger
            >
            <TabsTrigger value="sales" class="px-2 text-xs sm:px-3 sm:text-sm"
              >DairyBox Sales</TabsTrigger
            >
          </TabsList>
        </Tabs>
        <!-- Data/Monthly Toggle -->
        <div class="inline-flex rounded-md border border-slate-200 bg-slate-100 p-1">
          <button
            class="rounded-md px-4 py-2 text-xs font-medium transition-colors"
            :class="
              activeView === 'data'
                ? 'bg-warm-yellow text-deep-navy-blue'
                : 'text-slate-700 hover:bg-slate-200'
            "
            @click="handleViewModeChange('data')"
          >
            Data
          </button>
          <button
            class="rounded-md px-4 py-2 text-xs font-medium transition-colors"
            :class="
              activeView === 'monthly'
                ? 'bg-warm-yellow text-deep-navy-blue'
                : 'text-slate-700 hover:bg-slate-200'
            "
            @click="handleViewModeChange('monthly')"
          >
            Monthly
          </button>
        </div>

        <!-- Global Search -->
        <div class="relative w-full sm:w-auto">
          <Icon
            icon="lucide:search"
            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
          />
          <Input
            v-model="globalSearch"
            placeholder="Search by Center, Partner..."
            class="w-full pl-10 sm:w-50"
            @input="handleGlobalSearch(globalSearch)"
          />
        </div>

        <!-- Filters -->
        <DropdownMenu class="w-full sm:w-auto">
          <DropdownMenuTrigger as-child>
            <Button variant="outline" class="w-full cursor-pointer sm:w-auto">
              <Icon icon="lucide:filter" class="mr-2 h-4 w-4" />
              <span class="flex-1 text-left">Filters</span>
              <span
                v-show="Object.keys(currentFilterIndicator).length > 0"
                class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800"
              >
                {{ Object.keys(currentFilterIndicator).length }}
              </span>
              <Icon icon="lucide:chevron-down" class="ml-2 h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            align="end"
            class="max-h-[50vh] w-72 overflow-y-auto bg-white sm:max-h-[70vh] sm:w-80"
          >
            <DropdownMenuLabel>Filters</DropdownMenuLabel>
            <DropdownMenuSeparator class="border-b border-slate-100" />

            <!-- Center -->
            <template v-if="!isCenterScoped">
              <DropdownMenuItem
                class="cursor-pointer"
                @select.prevent
                @click="toggleFilterSection('center')"
              >
                <div class="flex w-full items-center justify-between">
                  <span>Center</span>
                  <Icon
                    :icon="
                      openFilterSection === 'center' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                    "
                    class="h-4 w-4 text-slate-500"
                  />
                </div>
              </DropdownMenuItem>
              <div v-if="openFilterSection === 'center'" class="max-h-56 overflow-y-auto">
                <DropdownMenuItem
                  v-for="center in centers"
                  :key="center.id"
                  class="cursor-pointer pl-8"
                  :class="{
                    'bg-indigo-50 text-indigo-700':
                      currentFilters[`filter[center_id]`] === String(center.id),
                  }"
                  @select.prevent
                  @click="
                    handleFilter(
                      'center_id',
                      currentFilters[`filter[center_id]`] === String(center.id)
                        ? null
                        : String(center.id)
                    )
                  "
                >
                  {{ center.name }}
                </DropdownMenuItem>
              </div>
              <DropdownMenuSeparator />
            </template>

            <!-- Partner -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('partner')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Partner</span>
                <Icon
                  :icon="
                    openFilterSection === 'partner' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'partner'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="partner in partners"
                :key="partner.id"
                class="cursor-pointer pl-8"
                :class="{
                  'bg-indigo-50 text-indigo-700':
                    currentFilters[`filter[partner_id]`] === String(partner.id),
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'partner_id',
                    currentFilters[`filter[partner_id]`] === String(partner.id)
                      ? null
                      : String(partner.id)
                  )
                "
              >
                {{ partner.name }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />
            <!-- Year -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('year')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Year</span>
                <Icon
                  :icon="openFilterSection === 'year' ? 'lucide:chevron-up' : 'lucide:chevron-down'"
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'year'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="year in years"
                :key="year"
                class="cursor-pointer pl-8"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[year]`] === String(year),
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'year',
                    currentFilters[`filter[year]`] === String(year) ? null : String(year)
                  )
                "
              >
                {{ year }}
              </DropdownMenuItem>
            </div>
          </DropdownMenuContent>
        </DropdownMenu>
        <!-- New Dairy Box Sale Button -->
        <Link
          :href="route('dairy-box-sales.create')"
          as="button"
          class="inline-flex w-full cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          <span class="flex-1 text-left">New Dairy Box Sale</span>
        </Link>
      </div>
    </div>

    <!-- Data View Table -->
    <div
      v-if="activeView === 'data'"
      class="w-full overflow-x-auto rounded-lg border border-slate-200 bg-white"
    >
      <div class="min-w-[1000px] sm:min-w-full">
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
                  <Icon icon="lucide:database" class="h-12 w-12 text-slate-300" />
                  <div class="text-center">
                    <h3 class="mb-1 text-lg font-medium text-slate-900">
                      No dairy box sales records found
                    </h3>
                    <p class="mb-4 text-slate-500">Adjust your filters to see more results.</p>
                  </div>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>

    <!-- Monthly View Table -->
    <DairyBoxSalesMonthlyTable v-if="activeView === 'monthly'" :dairy-box-sales="dairyBoxSales" />
    <!-- Pagination (only for Data view) -->
    <div
      v-if="activeView === 'data' && dairyBoxSales?.links"
      class="flex flex-col justify-between gap-4 pt-4 text-xs font-semibold uppercase sm:flex-row sm:items-center"
    >
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        <span class="text-sm">
          Showing {{ dairyBoxSales.from || 0 }}–{{ dairyBoxSales.to || 0 }} of
          {{ dairyBoxSales.total || 0 }}
        </span>
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          :disabled="!dairyBoxSales.prev_page_url"
          @click="handlePagination(dairyBoxSales.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="hidden sm:inline">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="sm"
          :disabled="!dairyBoxSales.next_page_url"
          @click="handlePagination(dairyBoxSales.next_page_url)"
        >
          <span class="hidden sm:inline">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
