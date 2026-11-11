<script setup>
import { displayCenterLabel } from '@/lib/center'
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate, label } from '@/lib/utils'
import debounce from 'lodash/debounce'
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core'

import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
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

const props = defineProps({
  serviceRecords: {
    type: Object,
    required: true,
  },
  centers: {
    type: Array,
    required: true,
  },
  services: {
    type: Array,
    required: true,
  },
  farmers: {
    type: Array,
    required: true,
  },
  enums: {
    type: Object,
    default: () => ({}),
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

  router.get(route('service-records.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const searchParams = new URLSearchParams(window.location.search)
const currentFilters = reactive({
  [`filter[center_id]`]: searchParams.get('filter[center_id]'),
  [`filter[service_id]`]: searchParams.get('filter[service_id]'),
  [`filter[program]`]: searchParams.get('filter[program]'),
})

const globalSearch = ref(searchParams.get('search') || '')

const selectedCenter = computed(() => currentFilters[`filter[center_id]`])
const selectedProgram = computed(() => currentFilters[`filter[program]`])
const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('md')

const openFilterSection = ref(null)

const toggleFilterSection = section => {
  openFilterSection.value = openFilterSection.value === section ? null : section
}

const currentFilterIndicator = computed(() => {
  return Object.keys(currentFilters).reduce((acc, key) => {
    if (currentFilters[key]) {
      acc[key] = currentFilters[key]
    }
    return acc
  }, {})
})

const handleGlobalSearch = debounce(value => {
  const params = { ...page.props.ziggy?.query }

  if (value) {
    params.search = value
  } else {
    delete params.search
  }

  router.get(route('service-records.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const handleFilter = debounce((key, value) => {
  const params = { ...page.props.ziggy?.query, ...currentFilters }

  if (value) {
    params[`filter[${key}]`] = value
    currentFilters[`filter[${key}]`] = value
  } else {
    delete params[`filter[${key}]`]
    delete currentFilters[`filter[${key}]`]
  }

  router.get(route('service-records.index'), params, {
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

const deleteServiceRecord = serviceRecord => {
  if (window.confirm(`Are you sure you want to delete this service record?`)) {
    router.delete(route('service-records.destroy', serviceRecord.id))
  }
}

const getCenterName = centerId => {
  const center = props.centers.find(c => c.id === centerId)
  return center ? displayCenterLabel(center.code) : 'N/A'
}

const getProgramBadgeColor = program => {
  const colors = {
    ih: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
    cbed: 'bg-green-100 text-green-800 hover:bg-green-200',
  }
  return colors[program] || 'bg-gray-100 text-gray-800'
}

const getServiceName = serviceId => {
  if (!serviceId) return 'No Service'
  const service = props.services.find(s => s.id === serviceId)
  return service ? service.name : 'N/A'
}

const columns = [
  {
    id: 'center',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('center_id'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Center', h(Icon, { icon: getSortIcon('center_id'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const serviceRecord = row.original
      return h(
        'div',
        { class: 'flex flex-col space-y-1' },
        h('p', { class: 'text-sm text-slate-700' }, getCenterName(serviceRecord.center_id))
      )
    },
  },
  {
    accessorKey: 'client_name',
    enableHiding: false,
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('client_name'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Client Name', h(Icon, { icon: getSortIcon('client_name'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const serviceRecord = row.original
      return h(
        Link,
        {
          class: 'flex flex-col space-y-1',
          href: route('service-records.show', serviceRecord.id),
        },
        [
          h(
            'p',
            { class: 'text-sm font-medium text-slate-900 underline' },
            serviceRecord.client_name
          ),
        ]
      )
    },
  },
  {
    accessorKey: 'mfo_code',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('mfo_code'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['MFO Code', h(Icon, { icon: getSortIcon('mfo_code'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const serviceRecord = row.original
      return h(
        'div',
        { class: 'flex flex-col space-y-1' },
        h('p', { class: 'text-sm text-slate-700' }, serviceRecord.service?.simplified_code || 'N/A')
      )
    },
  },
  {
    accessorKey: 'program',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('program'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Program', h(Icon, { icon: getSortIcon('program'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const serviceRecord = row.original
      const program = serviceRecord.program

      if (!program) {
        return h('span', { class: 'text-sm text-slate-500' }, 'N/A')
      }

      return h(Badge, { class: getProgramBadgeColor(program) }, () => program.toUpperCase())
    },
  },
  {
    id: 'service',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('service_id'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Service', h(Icon, { icon: getSortIcon('service_id'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const serviceRecord = row.original
      return h(
        'div',
        { class: 'flex flex-col space-y-1' },
        h('p', { class: 'text-sm text-slate-700' }, getServiceName(serviceRecord.service_id))
      )
    },
  },
  {
    accessorKey: 'date_conducted',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('date_conducted'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => [
          'Date Conducted',
          h(Icon, { icon: getSortIcon('date_conducted'), class: 'ml-2 h-4 w-4' }),
        ]
      )
    },
    cell: ({ row }) => {
      const serviceRecord = row.original
      return h(
        'div',
        { class: 'flex flex-col space-y-1' },
        h('p', { class: 'text-sm text-slate-700' }, formatDate(serviceRecord.date_conducted))
      )
    },
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const serviceRecord = row.original

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
                      onClick: () => router.visit(route('service-records.show', serviceRecord.id)),
                      class: 'cursor-pointer hover:bg-gray-100',
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
                      onClick: () => router.visit(route('service-records.edit', serviceRecord.id)),
                      class: 'cursor-pointer hover:bg-gray-100',
                    },
                    {
                      default: () => [
                        h(Icon, {
                          icon: 'lucide:pencil',
                          class: 'mr-2 h-4 w-4',
                        }),
                        'Edit Record',
                      ],
                    }
                  ),
                  h(DropdownMenuSeparator),
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () => deleteServiceRecord(serviceRecord),
                      class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                    },
                    {
                      default: () => [
                        h(Icon, {
                          icon: 'lucide:trash-2',
                          class: 'mr-2 h-4 w-4',
                        }),
                        'Delete Record',
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
    return props.serviceRecords?.data || []
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
})
</script>

<template>
  <div class="w-full space-y-6 rounded-lg bg-white px-6 py-12 text-black shadow-sm">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Service Records</h1>
        <p class="text-slate-600">Manage and view service records and data.</p>
      </div>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:space-x-2">
        <!-- Global Search -->
        <div class="relative">
          <Icon
            icon="lucide:search"
            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
          />
          <Input
            v-model="globalSearch"
            placeholder="Search by Client Name..."
            class="w-full pl-10 sm:w-80"
            @input="handleGlobalSearch(globalSearch)"
          />
        </div>
        <!-- Filter -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" class="ml-auto w-full sm:w-auto">
              <Icon icon="lucide:filter" class="mr-2 h-4 w-4" />
              Filters
              <span
                v-show="Object.keys(currentFilterIndicator).length > 0"
                class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800"
                >{{ Object.keys(currentFilterIndicator).length }}</span
              >
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="max-h-96 w-80 overflow-y-auto bg-white">
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
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700':
                    currentFilters[`filter[center_id]`] === center.id.toString(),
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'center_id',
                    currentFilters[`filter[center_id]`] === center.id.toString()
                      ? null
                      : center.id.toString()
                  )
                "
              >
                {{ displayCenterLabel(center.code) }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />

            <!-- Service -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('service_id')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Service</span>
                <Icon
                  :icon="
                    openFilterSection === 'service_id' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'service_id'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="service in services"
                :key="service.id"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700':
                    currentFilters[`filter[service_id]`] === service.id.toString(),
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'service_id',
                    currentFilters[`filter[service_id]`] === service.id.toString()
                      ? null
                      : service.id.toString()
                  )
                "
              >
                {{ service.name }}
              </DropdownMenuItem>
            </div>

            <!-- Program -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('program')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Program</span>
                <Icon
                  :icon="
                    openFilterSection === 'program' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'program'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="(labelText, value) in enums?.program || {}"
                :key="value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[program]`] === value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'program',
                    currentFilters[`filter[program]`] === value ? null : value
                  )
                "
              >
                {{ labelText }}
              </DropdownMenuItem>
            </div>
          </DropdownMenuContent>
        </DropdownMenu>
        <Link
          :href="route('service-records.create')"
          as="button"
          class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          New Service Record
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
              @click="router.visit(route('service-records.show', row.original.id))"
            >
              <TableCell
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                class="align-center border-none whitespace-nowrap"
                @click.stop="
                  cell.column.id === 'select' || cell.column.id === 'actions' ? null : undefined
                "
              >
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>
          <TableRow v-else class="border-none hover:bg-transparent">
            <TableCell :colspan="columns.length" class="h-32 border-none text-center">
              <div class="flex flex-col items-center justify-center space-y-3">
                <Icon icon="lucide:clipboard-list" class="h-12 w-12 text-slate-300" />
                <div class="text-center">
                  <h3 class="mb-1 text-lg font-medium text-slate-900">No service records found</h3>
                  <p class="mb-4 text-slate-500">
                    Get started by adding your first service record.
                  </p>
                </div>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- Pagination -->
    <div v-if="serviceRecords?.links" class="flex items-center justify-between space-x-2 py-4">
      <div class="text-sm text-slate-700">
        Showing {{ serviceRecords.from || 0 }} to {{ serviceRecords.to || 0 }} of
        {{ serviceRecords.total || 0 }} results
      </div>
      <div class="flex items-center space-x-2">
        <Link
          v-for="link in serviceRecords.links"
          :key="link.label"
          :href="link.url || '#'"
          :class="[
            'inline-flex items-center rounded-md px-3 py-1.5 text-sm font-medium',
            link.active
              ? 'bg-indigo-600 text-white'
              : link.url
                ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                : 'cursor-not-allowed bg-slate-100 text-slate-400',
          ]"
          :disabled="!link.url"
          as="button"
          v-html="link.label"
        />
      </div>
    </div>
  </div>
</template>
