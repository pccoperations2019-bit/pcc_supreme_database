<script setup>
import { displayCenterLabel } from '@/lib/center'
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater } from '@/lib/utils'
import debounce from 'lodash/debounce'
import { Button } from '@/components/ui/button'
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
import { useAuth } from '@/composable/useAuth'

const props = defineProps({
  goals: {
    type: Object,
    required: true,
  },
  centers: {
    type: Array,
    default: () => [],
  },
  availableYears: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  programs: { type: Array, default: () => [] },
  forceMode: {
    type: String,
    default: null,
    validator: value => ['production', 'sales', null].includes(value),
  },
})

const page = usePage()
const { hasPermission } = useAuth()

const searchParams = new URLSearchParams(window.location.search)

const rawProgram = searchParams.get('filter[program]')
const programTabValue = ref(['ih', 'cbed', 'total'].includes(rawProgram) ? rawProgram : 'ih')
const navigationTabValue = ref(
  props.forceMode
    ? props.forceMode
    : route().current() === 'center-sales-goals.index' || page.props.ziggy?.query?.tab === 'sales'
      ? 'sales'
      : 'production'
)

const currentQueryParams = () => Object.fromEntries(new URLSearchParams(window.location.search))

const MODE_FIELDS = {
  production: {
    milk: 'milk_production_goal',
    meat: 'meat_meat_equivalent_goal',
    manure: 'manure_based_goal',
    hide: 'hide_production_goals',
  },
  sales: {
    milk: 'milk_sales_goal',
    meat: 'meat_sales_goal',
    manure: 'manure_sales_goal',
    hide: 'hide_sales_goals',
  },
}

const currentFilters = reactive({
  [`filter[center_id]`]: searchParams.get('filter[center_id]'),
  [`filter[year]`]: searchParams.get('filter[year]'),
})

const currentFilterIndicator = computed(() => {
  return Object.keys(currentFilters).reduce((acc, key) => {
    if (currentFilters[key]) {
      acc[key] = currentFilters[key]
    }
    return acc
  }, {})
})

const yearOptions = computed(() => props.availableYears)

const openFilterSection = ref(null)

const toggleFilterSection = section => {
  openFilterSection.value = openFilterSection.value === section ? null : section
}

const handleFilter = debounce((key, value) => {
  const params = { ...currentQueryParams(), ...currentFilters }

  if (value) {
    params[`filter[${key}]`] = value
    currentFilters[`filter[${key}]`] = value
  } else {
    delete params[`filter[${key}]`]
    delete currentFilters[`filter[${key}]`]
  }

  router.get(route('center-production-goals.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const baseRouteForMode = mode =>
  mode === 'sales' ? 'center-sales-goals.index' : 'center-production-goals.index'

const handleProgramTabChange = value => {
  programTabValue.value = value
  const params = { ...currentQueryParams() }
  delete params.tab
  if (value === 'total') {
    delete params[`filter[program]`]
  } else {
    params[`filter[program]`] = value
  }
  router.get(route(baseRouteForMode(navigationTabValue.value)), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const handleNavigationTabChange = value => {
  navigationTabValue.value = value
  const params = { ...currentQueryParams() }
  delete params.tab
  router.get(route(baseRouteForMode(value)), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

onMounted(() => {
  const params = Object.fromEntries(new URLSearchParams(window.location.search))
  if (!params['filter[program]']) {
    params['filter[program]'] = 'ih'
    router.visit(route(baseRouteForMode(navigationTabValue.value), params), {
      replace: true,
      preserveState: true,
      preserveScroll: true,
    })
  }
})

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

const deleteGoal = (rowGoal, id, routeName, label, isSales) => {
  const kind = isSales ? 'sales' : 'production'
  if (
    confirm(
      `Are you sure you want to delete the ${label} ${kind} goal for ${rowGoal.center?.name}?`
    )
  ) {
    router.delete(route(`${routeName}.destroy`, id))
  }
}

const formatNumber = value => {
  if (value === null || value === undefined || value === '' || value === '0.00') return '-'
  return Number(value).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const valueFor = (row, key) => {
  const fields = MODE_FIELDS[navigationTabValue.value]
  if (programTabValue.value === 'total') {
    const ihVal = row.ih?.[fields[key]] ?? null
    const cbedVal = row.cbed?.[fields[key]] ?? null
    if (ihVal === null && cbedVal === null) return null
    return Number(ihVal || 0) + Number(cbedVal || 0)
  }
  const prog = row[programTabValue.value]
  return prog ? prog[fields[key]] : null
}

const renderGoalCell =
  key =>
  ({ row }) => {
    return h('span', { class: 'text-sm text-slate-900' }, formatNumber(valueFor(row.original, key)))
  }

const goalHeader = label => () => {
  const isSales = navigationTabValue.value === 'sales'
  return h('span', { class: 'inline-flex flex-col' }, [
    h('span', `${label} Goal`),
    h(
      'span',
      { class: 'text-xs font-normal text-slate-400' },
      isSales ? `Sales (₱ ${label})` : `Production (kg ${label})`
    ),
  ])
}

const columns = [
  {
    accessorKey: 'center',
    header: 'Center',
    cell: ({ row }) => {
      const goal = row.original
      return h(
        'span',
        { class: 'block text-sm font-medium text-slate-900' },
        displayCenterLabel(goal.center?.code)
      )
    },
  },
  { id: 'milk', header: goalHeader('Milk'), cell: renderGoalCell('milk') },
  { id: 'meat', header: goalHeader('Meat'), cell: renderGoalCell('meat') },
  { id: 'manure', header: goalHeader('Manure'), cell: renderGoalCell('manure') },
  { id: 'hide', header: goalHeader('Hide'), cell: renderGoalCell('hide') },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const goal = row.original

      if (programTabValue.value === 'total') {
        return h('span')
      }

      const programMenu = (program, label) => {
        const prog = goal[program]
        if (!prog) return []

        const isSales = navigationTabValue.value === 'sales'
        const routeName = isSales ? 'center-sales-goals' : 'center-production-goals'
        const id = isSales ? prog.sales_id : prog.id

        if (!id) return []

        const items = [
          h(
            DropdownMenuItem,
            {
              onClick: () => router.visit(route(`${routeName}.show`, id)),
              class: 'cursor-pointer hover:bg-gray-100',
            },
            {
              default: () => [
                h(Icon, { icon: 'lucide:eye', class: 'mr-2 h-4 w-4' }),
                `View ${label}`,
              ],
            }
          ),
        ]

        if (hasPermission('enter-data')) {
          items.push(
            h(DropdownMenuSeparator),
            h(
              DropdownMenuItem,
              {
                onClick: () => router.visit(route(`${routeName}.edit`, id)),
                class: 'cursor-pointer hover:bg-gray-100',
              },
              {
                default: () => [
                  h(Icon, { icon: 'lucide:pencil', class: 'mr-2 h-4 w-4' }),
                  `Edit ${label}`,
                ],
              }
            ),
            h(DropdownMenuSeparator),
            h(
              DropdownMenuItem,
              {
                onClick: () => deleteGoal(goal, id, routeName, label, isSales),
                class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
              },
              {
                default: () => [
                  h(Icon, { icon: 'lucide:trash-2', class: 'mr-2 h-4 w-4' }),
                  `Delete ${label}`,
                ],
              }
            )
          )
        }

        return items
      }

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
                      default: () =>
                        h(Icon, {
                          icon: 'lucide:more-horizontal',
                          class: 'h-4 w-4',
                        }),
                    }
                  ),
              }
            ),
            h(
              DropdownMenuContent,
              { align: 'end', class: 'w-56 bg-white cursor-pointer' },
              {
                default: () => [
                  ...programMenu('ih', 'IH Goal'),
                  ...programMenu('cbed', 'CBED Goal'),
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
    return props.goals.data || []
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
  <div class="w-full space-y-6 rounded-lg bg-white px-6 py-12 text-black shadow-sm">
    <!-- Header -->
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">
          Center {{ navigationTabValue === 'sales' ? 'Sales' : 'Production' }} Goals
        </h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Manage {{ navigationTabValue === 'sales' ? 'sales' : 'production' }} targets for each
          center and year.
        </p>
      </div>
      <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-4">
        <!-- Program Switch -->
        <Tabs
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

        <!-- Production / Sales Switch -->
        <Tabs
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
                v-for="year in yearOptions"
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
        <!-- New Goal Button -->
        <Link
          v-if="hasPermission('enter-data')"
          :href="
            navigationTabValue === 'sales'
              ? route('center-sales-goals.create')
              : route('center-production-goals.create')
          "
          as="button"
          class="inline-flex w-full cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          <span class="flex-1 text-left">New Goal</span>
        </Link>
      </div>
    </div>

    <!-- Table -->
    <div class="w-full overflow-hidden overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <Table>
        <TableHeader>
          <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
            <TableHead
              v-for="header in headerGroup.headers"
              :key="header.id"
              class="bg-slate-50 font-semibold whitespace-nowrap text-slate-700"
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
                class="align-center border-none whitespace-nowrap"
                :class="{ 'py-3': programTabValue === 'total' }"
                @click.stop="cell.column.id === 'actions' ? null : undefined"
              >
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>
          <TableRow v-else class="border-none hover:bg-transparent">
            <TableCell :colspan="columns.length" class="h-32 border-none text-center">
              <div class="flex flex-col items-center justify-center space-y-3">
                <Icon icon="lucide:target" class="h-12 w-12 text-slate-300" />
                <div class="text-center">
                  <h3 class="mb-1 text-lg font-medium text-slate-900">
                    No {{ navigationTabValue === 'sales' ? 'sales' : 'production' }} goals found
                  </h3>
                  <p class="mb-4 text-slate-500">
                    Get started by adding your first
                    {{ navigationTabValue === 'sales' ? 'sales' : 'production' }} goal.
                  </p>
                  <Link
                    :href="
                      navigationTabValue === 'sales'
                        ? route('center-sales-goals.create')
                        : route('center-production-goals.create')
                    "
                    as="button"
                    class="inline-flex cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
                  >
                    <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
                    <span class="hidden sm:inline">New Goal</span>
                  </Link>
                </div>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
    <!-- Pagination -->
    <div
      class="flex flex-col justify-between gap-4 pt-4 text-xs font-semibold uppercase sm:flex-row sm:items-center"
    >
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        <span class="text-sm">
          Showing {{ goals.from || 0 }}–{{ goals.to || 0 }} of
          {{ goals.total || 0 }}
        </span>
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          :disabled="!goals.prev_page_url"
          @click="handlePagination(goals.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="hidden sm:inline">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="sm"
          :disabled="!goals.next_page_url"
          @click="handlePagination(goals.next_page_url)"
        >
          <span class="hidden sm:inline">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
