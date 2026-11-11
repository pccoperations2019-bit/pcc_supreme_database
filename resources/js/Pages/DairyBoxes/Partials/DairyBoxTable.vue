<script setup>
import { displayCenterLabel } from '@/lib/center'
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate } from '@/lib/utils'
import debounce from 'lodash/debounce'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useAuth } from '@/composable/useAuth'
import { Badge } from '@/components/ui/badge'
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

const props = defineProps({
  dairyBoxes: {
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
  statuses: {
    type: Object,
    default: () => ({}),
  },
  statusBadgeColors: {
    type: Object,
    default: () => ({}),
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
})

const page = usePage()
const { isCenterScoped } = useAuth()

const navigationTabValue = ref('production')

const handleNavigationTabChange = value => {
  navigationTabValue.value = value
  if (value === 'sales') {
    router.get('/dairy-box-sales', {}, { preserveState: false })
  }
}

const searchParams = new URLSearchParams(window.location.search)
const getCurrentParams = () => Object.fromEntries(new URLSearchParams(window.location.search))
const globalSearch = ref(searchParams.get('search') || '')
const currentFilters = reactive({
  [`filter[center_id]`]: searchParams.get('filter[center_id]'),
  [`filter[status]`]: searchParams.get('filter[status]'),
})

const currentFilterIndicator = computed(() => {
  return Object.keys(currentFilters).reduce((acc, key) => {
    if (currentFilters[key]) {
      acc[key] = currentFilters[key]
    }
    return acc
  }, {})
})

const statusOptions = computed(() => props.statuses)

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

  router.get(route('dairy-boxes.index'), params, {
    only: ['dairyBoxes'],
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

  router.get(route('dairy-boxes.index'), params, {
    only: ['dairyBoxes'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const handleSort = column => {
  const currentSort =
    page.props.ziggy?.query?.sort || new URLSearchParams(window.location.search).get('sort')

  let newSort = column
  if (currentSort === column) {
    newSort = `-${column}`
  } else if (currentSort === `-${column}`) {
    newSort = null
  }

  const params = Object.fromEntries(
    Object.entries(getCurrentParams()).filter(([_, v]) => v !== '' && v !== undefined && v !== null)
  )

  if (newSort) {
    params.sort = newSort
  } else {
    delete params.sort
  }

  router.get(route('dairy-boxes.index'), params, {
    only: ['dairyBoxes'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

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
    router.get(url, {}, { preserveState: true, preserveScroll: true })
  }
}

const deleteDairyBox = dairyBox => {
  if (
    confirm(
      `Are you sure you want to delete this dairy box "${dairyBox.business_name || dairyBox.dairy_box_id}"?`
    )
  ) {
    router.delete(route('dairy-boxes.destroy', dairyBox.id))
  }
}

const formatDateValue = value => {
  if (!value) return '-'
  return formatDate(value)
}

const columns = [
  {
    accessorKey: 'dairy_box_id',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('dairy_box_id'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Dairy Box ID',
          h(Icon, { icon: getSortIcon('dairy_box_id'), class: 'ml-2 h-4 w-4' }),
        ]
      )
    },
    cell: ({ row }) => {
      const dairyBox = row.original
      return h(
        'span',
        { class: 'text-sm font-medium text-slate-900' },
        dairyBox.dairy_box_id || '-'
      )
    },
  },
  {
    accessorKey: 'business_name',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('business_name'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Business Name',
          h(Icon, { icon: getSortIcon('business_name'), class: 'ml-2 h-4 w-4' }),
        ]
      )
    },
    cell: ({ row }) => {
      const dairyBox = row.original
      return h(
        'button',
        {
          class: 'text-sm font-medium text-slate-900 underline',
          onClick: () => router.visit(route('dairy-boxes.show', dairyBox.id)),
        },
        dairyBox.business_name || '-'
      )
    },
  },
  {
    accessorKey: 'center',
    header: 'Center',
    cell: ({ row }) => {
      const dairyBox = row.original
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        dairyBox.center ? displayCenterLabel(dairyBox.center.code) : '-'
      )
    },
  },
  {
    accessorKey: 'partner',
    header: 'Partner',
    cell: ({ row }) => {
      const dairyBox = row.original
      if (!dairyBox.partner) {
        return h('span', { class: 'text-sm text-slate-900' }, '-')
      }

      return h(
        'button',
        {
          class: 'text-sm font-medium text-slate-900 underline',
          onClick: () => router.visit(route('partners.show', dairyBox.partner.id)),
        },
        dairyBox.partner.name
      )
    },
  },
  {
    id: 'region',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('region'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Region', h(Icon, { icon: getSortIcon('region'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const dairyBox = row.original
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        dairyBox.address?.region_label || dairyBox.address?.region || 'N/A'
      )
    },
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const dairyBox = row.original
      const status = typeof dairyBox.status === 'object' ? dairyBox.status?.value : dairyBox.status

      if (!status) {
        return h('span', { class: 'text-sm text-slate-500' }, '-')
      }

      // Temporary fallback colors for testing
      const fallbackColors = {
        completed: 'bg-green-100 text-green-800 hover:bg-green-200',
        discontinued_operation: 'bg-red-100 text-red-800 hover:bg-red-200',
        on_going_construction: 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200',
        operational: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
      }

      const badgeColor =
        props.statusBadgeColors[status] || fallbackColors[status] || 'bg-gray-100 text-gray-800'
      const statusLabel = props.statuses[status] || status

      return h(Badge, { class: badgeColor }, () => statusLabel)
    },
  },
  {
    accessorKey: 'launched_date',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('launched_date'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Launched Date',
          h(Icon, { icon: getSortIcon('launched_date'), class: 'ml-2 h-4 w-4' }),
        ]
      )
    },
    cell: ({ row }) => {
      const value = row.getValue('launched_date')
      return h('span', { class: 'text-sm text-slate-900' }, formatDateValue(value))
    },
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const dairyBox = row.original

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
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () => router.visit(route('dairy-boxes.show', dairyBox.id)),
                      class: 'cursor-pointer hover:bg-gray-100',
                    },
                    {
                      default: () => [
                        h(Icon, {
                          icon: 'lucide:eye',
                          class: 'mr-2 h-4 w-4',
                        }),
                        'View Details',
                      ],
                    }
                  ),
                  h(DropdownMenuSeparator),
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () => router.visit(route('dairy-boxes.edit', dairyBox.id)),
                      class: 'cursor-pointer',
                    },
                    {
                      default: () => [
                        h(Icon, {
                          icon: 'lucide:pencil',
                          class: 'mr-2 h-4 w-4',
                        }),
                        'Edit Dairy Box',
                      ],
                    }
                  ),
                  h(DropdownMenuSeparator),
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () => deleteDairyBox(dairyBox),
                      class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                    },
                    {
                      default: () => [
                        h(Icon, {
                          icon: 'lucide:trash-2',
                          class: 'mr-2 h-4 w-4',
                        }),
                        'Delete Dairy Box',
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
    return props.dairyBoxes.data || []
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
  <div
    class="space-y-4 rounded-lg bg-white px-4 py-6 text-black shadow-sm sm:space-y-6 sm:px-6 sm:py-12"
  >
    <!-- Header -->
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Dairy Boxes</h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Manage dairy box operations and information.
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
        <!-- Global Search -->
        <div class="relative w-full sm:w-auto">
          <Icon
            icon="lucide:search"
            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
          />
          <Input
            v-model="globalSearch"
            placeholder="Search by Dairy Box ID, Business Name, Partner..."
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

            <!-- Status -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('status')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Status</span>
                <Icon
                  :icon="
                    openFilterSection === 'status' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'status'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="status in statusOptions"
                :key="status"
                class="cursor-pointer pl-8"
                :class="{
                  'bg-indigo-50 text-indigo-700':
                    currentFilters[`filter[status]`] === String(status),
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'status',
                    currentFilters[`filter[status]`] === String(status) ? null : String(status)
                  )
                "
              >
                {{ status }}
              </DropdownMenuItem>
            </div>
          </DropdownMenuContent>
        </DropdownMenu>
        <!-- New Dairy Box Button -->
        <Link
          :href="route('dairy-boxes.create')"
          as="button"
          class="inline-flex w-full cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          <span class="flex-1 text-left">New Dairy Box</span>
        </Link>
      </div>
    </div>

    <!-- Table -->
    <div class="w-full overflow-x-auto rounded-lg border border-slate-200 bg-white">
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
                  <Icon icon="lucide:package" class="h-12 w-12 text-slate-300" />
                  <div class="text-center">
                    <h3 class="mb-1 text-lg font-medium text-slate-900">No dairy boxes found</h3>
                    <p class="mb-4 text-slate-500">Get started by adding your first dairy box.</p>
                    <Link
                      :href="route('dairy-boxes.create')"
                      as="button"
                      class="inline-flex cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
                    >
                      <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
                      <span class="hidden sm:inline">New Dairy Box</span>
                    </Link>
                  </div>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
    <!-- Pagination -->
    <div
      v-if="dairyBoxes?.links"
      class="flex flex-col justify-between gap-4 pt-4 text-xs font-semibold uppercase sm:flex-row sm:items-center"
    >
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        <span class="text-sm">
          Showing {{ dairyBoxes.from || 0 }}–{{ dairyBoxes.to || 0 }} of
          {{ dairyBoxes.total || 0 }}
        </span>
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          :disabled="!dairyBoxes.prev_page_url"
          @click="handlePagination(dairyBoxes.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="hidden sm:inline">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="sm"
          :disabled="!dairyBoxes.next_page_url"
          @click="handlePagination(dairyBoxes.next_page_url)"
        >
          <span class="hidden sm:inline">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
