<script setup>
import { displayCenterLabel } from '@/lib/center'
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, reactive, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, label } from '@/lib/utils'
import debounce from 'lodash/debounce'
import { useAuth } from '@/composable/useAuth'

import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
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
  partners: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  enums: {
    type: Object,
    default: () => ({}),
  },
})

const page = usePage()
const { hasPermission } = useAuth()

const searchParams = new URLSearchParams(window.location.search)
const globalSearch = ref(searchParams.get('search') || searchParams.get('filter[key]') || '')
const currentFilters = reactive({
  [`filter[category]`]: searchParams.get('filter[category]'),
  [`filter[status]`]: searchParams.get('filter[status]'),
  [`filter[key]`]: searchParams.get('filter[key]'),
})

const currentFilterIndicator = computed(() => {
  return Object.keys(currentFilters).reduce((acc, key) => {
    if (currentFilters[key] && key !== 'filter[key]') {
      acc[key] = currentFilters[key]
    }
    return acc
  }, {})
})

const openFilterSection = ref(null)

const toggleFilterSection = section => {
  openFilterSection.value = openFilterSection.value === section ? null : section
}

const categoryOptions = computed(() => {
  const categories = props.enums?.categories || page.props?.enums?.categories || {}
  return Object.entries(categories).map(([value, label]) => ({ value, label }))
})

const statusOptions = computed(() => {
  const statuses = props.enums?.statuses || page.props?.enums?.statuses || {}
  const mapped = Object.entries(statuses).map(([value, label]) => ({ value, label }))
  return mapped.length
    ? mapped
    : [
        { value: 'active', label: 'Active' },
        { value: 'inactive', label: 'Inactive' },
      ]
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

  router.get(route('partners.index'), params, {
    only: ['partners'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const handleGlobalSearch = debounce(value => {
  const params = { ...page.props.ziggy?.query }

  if (value) {
    params.search = value
  } else {
    delete params.search
  }

  router.get(route('partners.index'), params, {
    only: ['partners'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const handleSearchInput = event => {
  const value = event?.target?.value ?? ''
  globalSearch.value = value
  handleGlobalSearch(value)
}

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

  router.get(route('partners.index'), params, {
    only: ['partners'],
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
    router.get(
      url,
      {},
      {
        only: ['partners'],
        preserveState: true,
        preserveScroll: true,
      }
    )
  }
}

const getStatusColor = status => {
  const colors = {
    active: 'bg-green-100 text-green-800 hover:bg-green-200',
    inactive: 'bg-gray-100 text-gray-800 hover:bg-gray-200',
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

const deletePartner = partner => {
  if (confirm(`Are you sure you want to delete ${partner.name}?`)) {
    // Keep the list state on screen: the server redirects back to this same
    // URL (search, filters, sort, page) and only the table rows change.
    router.delete(route('partners.destroy', partner.id), {
      preserveScroll: true,
      preserveState: true,
    })
  }
}

const columns = [
  {
    id: 'center',
    header: 'Center',
    cell: ({ row }) => {
      const partner = row.original
      const centerCode = partner.center?.code
      return h('span', { class: 'text-sm text-slate-900' }, displayCenterLabel(centerCode))
    },
  },
  {
    accessorKey: 'name',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('name'),
          class: 'p-0 h-auto font-medium',
        },
        () => ['Coop Name', h(Icon, { icon: getSortIcon('name'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const partner = row.original
      return h(
        Link,
        { class: 'flex flex-col space-y-1 ', href: route('partners.show', partner.id) },
        [
          h('p', { class: 'text-sm font-medium text-slate-900 underline' }, partner.name || 'N/A'),
          h('p', { class: 'text-xs text-slate-500' }, [partner.code || 'N/A']),
        ]
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
      const partner = row.original
      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        partner.address?.region_label || partner.address?.region || 'N/A'
      )
    },
  },
  {
    id: 'address',
    header: 'Province',
    cell: ({ row }) => {
      const partner = row.original
      const addr = partner.address
      if (!addr) return h('span', { class: 'text-sm text-slate-900' }, 'N/A')

      const province = addr.province
      return h('span', { class: 'text-sm text-slate-900 break-words' }, province || 'N/A')
    },
  },
  {
    id: 'project',
    header: 'Project',
    cell: ({ row }) => {
      const partner = row.original
      const assignedProjects = partner.projects || (partner.project ? [partner.project] : [])
      const projectLabels = assignedProjects
        .map(project => project.program ? label(props.enums?.projectPrograms || {}, project.program) : null)
        .filter(Boolean)
      return h('span', { class: 'text-sm text-slate-900' }, projectLabels.length ? projectLabels.join(', ') : 'N/A')
    },
  },
  {
    accessorKey: 'partnership_type',
    header: 'Partner Type',
    cell: ({ row }) => {
      const partner = row.original
      const partnershipTypes =
        props.enums?.partnershipTypes || page.props?.enums?.partnershipTypes || {}
      const categories = props.enums?.categories || page.props?.enums?.categories || {}
      const type = partner.partnership_type || partner.category
      const typeLabel = partnershipTypes?.[type] || categories?.[type] || type

      return h('span', { class: 'text-sm text-slate-900' }, typeLabel || 'N/A')
    },
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const status = row.getValue('status')
      return h(
        Badge,
        { class: `${getStatusColor(status)} text-xs font-medium px-2 py-1`, variant: 'secondary' },
        () => (status ? status.charAt(0).toUpperCase() + status.slice(1) : 'N/A')
      )
    },
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const partner = row.original

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
                default: () => {
                  const items = [
                    h(
                      DropdownMenuItem,
                      {
                        onClick: () => router.visit(route('partners.show', partner.id)),
                        class: 'cursor-pointer hover:bg-gray-100',
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
                          onClick: () => router.visit(route('partners.edit', partner.id)),
                          class: 'cursor-pointer hover:bg-gray-100',
                        },
                        {
                          default: () => [
                            h(Icon, {
                              icon: 'lucide:pencil',
                              class: 'mr-2 h-4 w-4',
                            }),
                            'Edit Partner',
                          ],
                        }
                      ),
                      h(DropdownMenuSeparator),
                      h(
                        DropdownMenuItem,
                        {
                          onClick: () => deletePartner(partner),
                          class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                        },
                        {
                          default: () => [
                            h(Icon, {
                              icon: 'lucide:trash-2',
                              class: 'mr-2 h-4 w-4',
                            }),
                            'Delete Partner',
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

// Table setup for display and selection
const columnVisibility = ref({})
const rowSelection = ref({})

const table = useVueTable({
  get data() {
    return props.partners.data || []
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
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Partners</h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Manage and view partner profiles and information.
        </p>
      </div>
      <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-4">
        <!-- Search -->
        <div class="relative w-full sm:w-64">
          <Icon
            icon="lucide:search"
            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
          />
          <Input
            :model-value="globalSearch"
            type="text"
            placeholder="Search by name or code..."
            class="w-full pl-9"
            @input="handleSearchInput"
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

            <!-- Category -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('category')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Category</span>
                <Icon
                  :icon="
                    openFilterSection === 'category' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'category'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="category in categoryOptions"
                :key="category.value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700':
                    currentFilters[`filter[category]`] === category.value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'category',
                    currentFilters[`filter[category]`] === category.value ? null : category.value
                  )
                "
              >
                {{ category.label }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />

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
                :key="status.value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[status]`] === status.value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'status',
                    currentFilters[`filter[status]`] === status.value ? null : status.value
                  )
                "
              >
                {{ status.label }}
              </DropdownMenuItem>
            </div>
          </DropdownMenuContent>
        </DropdownMenu>
        <!-- New Partner Button -->
        <Link
          v-if="hasPermission('enter-data')"
          :href="route('partners.create')"
          as="button"
          class="inline-flex w-full cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          <span class="flex-1 text-left">New Partner</span>
        </Link>
      </div>
    </div>

    <!-- Table -->
    <div class="w-full overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <div class="min-w-[800px] sm:min-w-full">
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
                @click="
                  router.visit(route('partners.show', row.original.id), { preserveScroll: true })
                "
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
                  <Icon icon="lucide:building" class="h-12 w-12 text-slate-300" />
                  <div class="text-center">
                    <h3 class="mb-1 text-lg font-medium text-slate-900">No partners found</h3>
                    <p class="mb-4 text-slate-500">Get started by adding your first partner.</p>
                    <Link
                      :href="route('partners.create')"
                      as="button"
                      class="inline-flex cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
                    >
                      <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
                      <span class="hidden sm:inline">New Partner</span>
                    </Link>
                  </div>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
    <div
      class="flex flex-col justify-between gap-4 pt-4 text-xs font-semibold uppercase sm:flex-row sm:items-center"
    >
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        <span class="text-sm">
          Showing {{ partners.from || 0 }}–{{ partners.to || 0 }} of
          {{ partners.total || 0 }}
        </span>
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          :disabled="!partners.prev_page_url"
          @click="handlePagination(partners.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="hidden sm:inline">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="sm"
          :disabled="!partners.next_page_url"
          @click="handlePagination(partners.next_page_url)"
        >
          <span class="hidden sm:inline">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
