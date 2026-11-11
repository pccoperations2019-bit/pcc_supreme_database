<script setup>
import { displayCenterLabel } from '@/lib/center'
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive, onMounted, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater } from '@/lib/utils'
import debounce from 'lodash/debounce'
import { MonthEnum, monthsArray } from '@/enums/MonthEnum'
import { useProgramColumns } from '@/composable/useProgramColumns'
import MilkProductionMonthlyTable from './MilkProductionMonthlyTable.vue'
import { useAuth } from '@/composable/useAuth'

import { Badge } from '@/components/ui/badge'
import { programBadgeClass, programLabel } from '@/lib/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import MilkProductionDashboard from './MilkProductionDashboard.vue'

const props = defineProps({
  milkProductions: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  years: { type: Array, default: () => [] },
  defaultYear: { type: Number, default: null },
})

const page = usePage()
const inertiaProps = computed(() => page.props || {})
const { hasPermission } = useAuth()

const searchParams = new URLSearchParams(window.location.search)
const getCurrentParams = () => Object.fromEntries(new URLSearchParams(window.location.search))

const globalSearch = ref(searchParams.get('search') || '')

const handleGlobalSearch = debounce(value => {
  const params = { ...page.props.ziggy?.query }
  if (value) {
    params.search = value
  } else {
    delete params.search
  }
  router.get(route('milk-productions.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const currentProgramFilter = searchParams.get('filter[program]')
const programTabValue = ref(
  currentProgramFilter === 'total' ? 'total' : currentProgramFilter || 'ih'
)

const currentFilters = reactive({
  [`filter[center_id]`]: searchParams.get('filter[center_id]'),
  [`filter[year]`]: searchParams.get('filter[year]'),
  [`filter[month]`]: searchParams.get('filter[month]'),
})

const activeView = ref(searchParams.get('_view') || 'monthly')

const currentFilterIndicator = computed(() => {
  return Object.keys(currentFilters).reduce((acc, key) => {
    if (currentFilters[key]) acc[key] = currentFilters[key]
    return acc
  }, {})
})

const openFilterSection = ref(null)

const toggleFilterSection = section => {
  openFilterSection.value = openFilterSection.value === section ? null : section
}

onMounted(() => {
  const params = getCurrentParams()
  if (!params['filter[program]']) {
    params['filter[program]'] = 'ih'
  }
  // Auto-set year filter for monthly view only
  if (activeView.value === 'monthly' && !params['filter[year]'] && props.defaultYear) {
    params['filter[year]'] = String(props.defaultYear)
  }
  const query = new URLSearchParams(params).toString()
  if (query !== window.location.search.slice(1)) {
    router.visit(route('milk-productions.index', params), {
      replace: true,
      preserveState: true,
      preserveScroll: true,
    })
  }
})

const navigationTabValue = ref('production')

const handleProgramTabChange = value => {
  programTabValue.value = value
  const params = getCurrentParams()
  if (value === 'total') {
    delete params['filter[program]']
  } else {
    params['filter[program]'] = value
  }
  router.get(route('milk-productions.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const handleNavigationTabChange = value => {
  navigationTabValue.value = value
  if (value === 'sales') {
    router.get('/milk-sales', {}, { preserveState: false })
  }
}

const handleViewModeChange = mode => {
  const params = getCurrentParams()
  params._view = mode
  router.get(route('milk-productions.index'), params, {
    preserveScroll: true,
    replace: true,
  })
}

const handleFilter = debounce((key, value) => {
  const params = getCurrentParams()
  if (value) {
    params[`filter[${key}]`] = value
    currentFilters[`filter[${key}]`] = value
  } else {
    delete params[`filter[${key}]`]
    delete currentFilters[`filter[${key}]`]
  }
  router.get(route('milk-productions.index'), params, {
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

const deleteMilkProduction = milkProduction => {
  if (confirm('Are you sure you want to delete this milk production record?')) {
    router.delete(route('milk-productions.destroy', milkProduction.id), {
      data: { _query: getCurrentParams() },
    })
  }
}

const formatVolume = volume => {
  const value = Number(volume ?? 0)
  if (!Number.isFinite(value)) return '0.00'
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

const dataViewColumns = [
  {
    accessorKey: 'center.code',
    header: () => 'Center',
    cell: ({ row }) => {
      const centerCode = row.original.center?.code
      return h('span', { class: 'text-sm text-slate-900' }, displayCenterLabel(centerCode))
    },
  },
  {
    accessorKey: 'partner.name',
    header: () => 'Partner',
    cell: ({ row }) => {
      return h('span', { class: 'text-sm text-slate-900' }, row.original.partner?.name || 'N/A')
    },
  },
  {
    accessorKey: 'farmer',
    header: () => 'Farmer',
    cell: ({ row }) => {
      const farmer = row.original.farmer
      const farmerName = farmer
        ? [farmer.first_name, farmer.last_name].filter(Boolean).join(' ')
        : null
      return h('span', { class: 'text-sm text-slate-900' }, farmerName || 'N/A')
    },
  },
  {
    accessorKey: 'program',
    header: () => 'Program',
    cell: ({ row }) => {
      const program = row.original.program
      return h(Badge, { class: programBadgeClass(program) }, () => programLabel(program))
    },
  },
  {
    accessorKey: 'year',
    header: () => 'Year',
    cell: ({ row }) => h('span', { class: 'text-sm text-slate-900' }, row.original.year || 'N/A'),
  },
  {
    accessorKey: 'month',
    header: () => 'Month',
    cell: ({ row }) => {
      const found = monthsArray.find(m => m.value === row.original.month)
      return h('span', { class: 'text-sm text-slate-900' }, found?.label || 'N/A')
    },
  },
  {
    accessorKey: 'total_volume_produced',
    header: 'Total Volume',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'text-sm font-medium text-slate-900' },
        formatVolume(row.original.total_volume_produced)
      ),
  },
  {
    id: 'actions',
    enableHiding: true,
    cell: ({ row }) => {
      const milkProduction = row.original
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
                default: () => {
                  const items = [
                    h(
                      DropdownMenuItem,
                      {
                        onClick: () =>
                          router.visit(route('milk-productions.show', milkProduction.id)),
                        class: 'cursor-pointer',
                      },
                      {
                        default: () => [
                          h(Icon, { icon: 'lucide:eye', class: 'mr-2 h-4 w-4' }),
                          'View Details',
                        ],
                      }
                    ),
                  ]

                  if (hasPermission('enter-data')) {
                    items.push(
                      h(
                        DropdownMenuItem,
                        {
                          onClick: () =>
                            router.visit(route('milk-productions.edit', milkProduction.id)),
                          class: 'cursor-pointer',
                        },
                        {
                          default: () => [
                            h(Icon, { icon: 'lucide:pencil', class: 'mr-2 h-4 w-4' }),
                            'Edit Production',
                          ],
                        }
                      ),
                      h(DropdownMenuSeparator),
                      h(
                        DropdownMenuItem,
                        {
                          onClick: () => deleteMilkProduction(milkProduction),
                          class: 'cursor-pointer text-red-600 focus:text-red-600',
                        },
                        {
                          default: () => [
                            h(Icon, { icon: 'lucide:trash-2', class: 'mr-2 h-4 w-4' }),
                            'Delete Production',
                          ],
                        }
                      )
                    )
                  }

                  return items
                },
              }
            ),
          ],
        }
      )
    },
  },
]

const columns = useProgramColumns(programTabValue, dataViewColumns)

const columnVisibility = ref({})
const rowSelection = ref({})

const table = useVueTable({
  get data() {
    return props.milkProductions?.data || []
  },
  get columns() {
    return columns.value
  },
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
  <div
    class="space-y-4 rounded-lg bg-white px-4 py-6 text-black shadow-sm sm:space-y-6 sm:px-6 sm:py-12"
  >
    <!-- Header -->
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Milk Production</h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Review milk production records, totals, and monthly breakdowns.
        </p>
      </div>
      <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-4">
        <Tabs
          v-if="activeView !== 'dashboard'"
          v-model="programTabValue"
          class="bordered w-fit"
          @update:model-value="handleProgramTabChange"
        >
          <TabsList class="h-8 sm:h-9">
            <TabsTrigger value="ih" class="px-2 text-xs sm:px-3 sm:text-sm">IH</TabsTrigger>
            <TabsTrigger value="cbed" class="px-2 text-xs sm:px-3 sm:text-sm">CBED</TabsTrigger>
            <TabsTrigger value="total" class="px-2 text-xs sm:px-3 sm:text-sm">Total</TabsTrigger>
          </TabsList>
        </Tabs>
        <Tabs
          v-if="activeView !== 'dashboard'"
          v-model="navigationTabValue"
          class="bordered w-fit"
          @update:model-value="handleNavigationTabChange"
        >
          <TabsList class="h-8 sm:h-9">
            <TabsTrigger value="production" class="px-2 text-xs sm:px-3 sm:text-sm"
              >Production</TabsTrigger
            >
            <TabsTrigger value="sales" class="px-2 text-xs sm:px-3 sm:text-sm">Sales</TabsTrigger>
          </TabsList>
        </Tabs>

        <!-- Data / Monthly / Dashboard Toggle -->
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
          <button
            class="rounded-md px-4 py-2 text-xs font-medium transition-colors"
            :class="
              activeView === 'dashboard'
                ? 'bg-warm-yellow text-deep-navy-blue'
                : 'text-slate-700 hover:bg-slate-200'
            "
            @click="handleViewModeChange('dashboard')"
          >
            Dashboard
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
            placeholder="Search records..."
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
            <!-- Center -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('center_id')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Center</span>
                <Icon
                  :icon="
                    openFilterSection === 'center_id' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'center_id'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="center in centers"
                :key="center.id"
                class="cursor-pointer pl-8"
                :class="{
                  'bg-indigo-50 text-indigo-700':
                    String(currentFilters['filter[center_id]']) === String(center.id),
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'center_id',
                    String(currentFilters['filter[center_id]']) === String(center.id)
                      ? null
                      : center.id
                  )
                "
                >{{ center.name }}</DropdownMenuItem
              >
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
                  'bg-indigo-50 text-indigo-700': currentFilters['filter[year]'] === String(year),
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'year',
                    currentFilters['filter[year]'] === String(year) ? null : String(year)
                  )
                "
                >{{ year }}</DropdownMenuItem
              >
            </div>
            <DropdownMenuSeparator />

            <!-- Month -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('month')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Month</span>
                <Icon
                  :icon="
                    openFilterSection === 'month' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'month'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="m in monthsArray"
                :key="m.value"
                class="cursor-pointer pl-8"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters['filter[month]'] === String(m.value),
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'month',
                    currentFilters['filter[month]'] === String(m.value) ? null : String(m.value)
                  )
                "
                >{{ m.label }}</DropdownMenuItem
              >
            </div>
          </DropdownMenuContent>
        </DropdownMenu>

        <Link
          v-if="hasPermission('enter-data')"
          :href="route('milk-productions.create')"
          as="button"
          class="inline-flex w-full cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          <span class="flex-1 text-left">New Production</span>
        </Link>
      </div>
    </div>

    <!-- Dashboard View -->
    <MilkProductionDashboard
      v-if="activeView === 'dashboard'"
      :dashboard="inertiaProps.dashboard"
    />

    <!-- Data View -->
    <div
      v-else-if="activeView === 'data'"
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
                    No milk production records found
                  </h3>
                  <p class="mb-4 text-slate-500">Adjust your filters to see more results.</p>
                </div>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- Monthly View -->
    <MilkProductionMonthlyTable
      v-else-if="activeView === 'monthly'"
      :milk-productions="milkProductions"
    />

    <!-- Pagination -->
    <div
      v-if="activeView === 'data'"
      class="flex items-center justify-between pt-4 text-xs font-semibold uppercase"
    >
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        <span
          >Showing {{ milkProductions.from || 0 }}–{{ milkProductions.to || 0 }} of
          {{ milkProductions.total || 0 }}</span
        >
      </div>
      <div class="flex items-center space-x-2">
        <Button
          variant="ghost"
          size="xs"
          :disabled="!milkProductions.prev_page_url"
          @click="handlePagination(milkProductions.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" /> Prev
        </Button>
        <Button
          variant="ghost"
          size="xs"
          :disabled="!milkProductions.next_page_url"
          @click="handlePagination(milkProductions.next_page_url)"
        >
          Next <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
