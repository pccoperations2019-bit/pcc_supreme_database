<script setup>
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate, getInitials } from '@/lib/utils'
import debounce from 'lodash/debounce'

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
  profiles: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  valueChainSegments: {
    type: Object,
    default: () => ({
      labels: {
        production: 'Production',
        processing: 'Processing',
        marketing: 'Marketing',
      },
      colors: {
        production: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
        processing: 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200',
        marketing: 'bg-green-100 text-green-800 hover:bg-green-200',
      },
    }),
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

  router.get(route('profiles.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const searchParams = new URLSearchParams(window.location.search)
const globalSearch = ref(searchParams.get('search') || searchParams.get('filter[key]') || '')
const currentFilters = reactive({
  [`filter[profile_type]`]: searchParams.get('filter[profile_type]'),
  [`filter[name]`]: searchParams.get('filter[name]'),
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

const handleGlobalSearch = debounce(value => {
  const params = { ...page.props.ziggy?.query }

  if (value) {
    params.search = value
  } else {
    delete params.search
  }

  router.get(route('profiles.index'), params, {
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

const handleFilter = debounce((key, value) => {
  const params = { ...page.props.ziggy?.query, ...currentFilters }

  if (value) {
    params[`filter[${key}]`] = value
    currentFilters[`filter[${key}]`] = value
  } else {
    delete params[`filter[${key}]`]
    delete currentFilters[`filter[${key}]`]
  }

  router.get(route('profiles.index'), params, {
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

const getStatusColor = status => {
  const colors = {
    active: 'bg-green-100 text-green-800 hover:bg-green-200',
    inactive: 'bg-gray-100 text-gray-800 hover:bg-gray-200',
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

const getProfileTypeColor = profileType => {
  const colors = {
    center: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
    partner: 'bg-purple-100 text-purple-800 hover:bg-purple-200',
  }
  return colors[profileType] || 'bg-gray-100 text-gray-800'
}

const getProfileTypeIcon = profileType => {
  const icons = {
    center: 'lucide:building-2',
    partner: 'lucide:handshake',
  }
  return icons[profileType] || 'lucide:user'
}

const getCategorySizeColor = categorySize => {
  const colors = {
    micro: 'bg-purple-100 text-purple-800 hover:bg-purple-200',
    small: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
    medium: 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200',
    large: 'bg-green-100 text-green-800 hover:bg-green-200',
  }
  return colors[categorySize] || 'bg-gray-100 text-gray-800'
}

const getCategoryLabel = category => {
  const categoryLabels = {
    ih: 'Institutional Herd',
    cbed: 'CBED',
    cooperative: 'Cooperative',
    association: 'Association',
    private: 'Private Business',
    suc: 'Schools, Universities, and Colleges',
    lgu: 'Local Government Units',
    ngo: 'Non-Governmental Organizations',
    independent: 'Independent',
    fca_member: 'FCA Member',
  }
  return categoryLabels[category] || category
}

const deleteProfile = profile => {
  // Since profiles is a view, we need to redirect to the actual entity
  const entityRoutes = {
    center: 'centers.destroy',
    partner: 'partners.destroy',
  }

  const route_name = entityRoutes[profile.profile_type]
  if (route_name && confirm(`Are you sure you want to delete ${profile.display_name}?`)) {
    router.delete(route(route_name, profile.id))
  }
}

const columns = [
  {
    accessorKey: 'code',
    header: () =>
      h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('code'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Code', h(Icon, { icon: getSortIcon('code'), class: 'ml-2 h-4 w-4' })]
      ),
    cell: ({ row }) => {
      const profile = row.original
      return h('div', { class: 'flex items-center space-x-3' }, [
        h(
          'div',
          {
            class:
              'flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-medium text-slate-700',
          },
          getInitials(profile.display_name)
        ),
        h('div', [
          h('div', { class: 'font-medium text-slate-900' }, profile.code || 'N/A'),
          h(
            'div',
            { class: 'text-xs text-slate-500' },
            `${profile.profile_type.charAt(0).toUpperCase()}${profile.profile_type.slice(1)}`
          ),
        ]),
      ])
    },
  },
  {
    accessorKey: 'display_name',
    header: () =>
      h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('name'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Name', h(Icon, { icon: getSortIcon('name'), class: 'ml-2 h-4 w-4' })]
      ),
    cell: ({ row }) => {
      const profile = row.original
      return h('div', [
        h('div', { class: 'font-medium text-slate-900 underline' }, profile.display_name || 'N/A'),
        h(
          'div',
          { class: 'text-xs text-slate-500' },
          profile.category ? profile.category.toUpperCase() : ''
        ),
      ])
    },
  },
  {
    accessorKey: 'profile_type',
    header: 'Type',
    cell: ({ row }) => {
      const profile = row.original
      return h(
        Badge,
        {
          class: `${getProfileTypeColor(profile.profile_type)} text-xs font-medium px-2 py-1`,
          variant: 'secondary',
        },
        () => [
          h(Icon, {
            icon: getProfileTypeIcon(profile.profile_type),
            class: 'mr-1 h-3 w-3',
          }),
          profile.profile_type.charAt(0).toUpperCase() + profile.profile_type.slice(1),
        ]
      )
    },
  },
  {
    accessorKey: 'region',
    header: () =>
      h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('region'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Region', h(Icon, { icon: getSortIcon('region'), class: 'ml-2 h-4 w-4' })]
      ),
    cell: ({ row }) =>
      h(
        'span',
        { class: 'text-sm text-slate-700 hidden sm:inline' },
        row.original.region_label ||
          row.original.address?.region_label ||
          row.original.address?.region ||
          row.original.region ||
          'N/A'
      ),
  },
  {
    accessorKey: 'address',
    header: 'Province',
    cell: ({ row }) => {
      const profile = row.original
      const province =
        profile.address?.province ||
        profile.province ||
        (typeof profile.address === 'string' ? profile.address : null)

      return h(
        'span',
        { class: 'text-sm text-slate-700 break-words hidden lg:block' },
        province || 'N/A'
      )
    },
  },
  {
    accessorKey: 'value_chain_task',
    header: 'Value Chain',
    cell: ({ row }) => {
      const profile = row.original
      const segments = []

      if ((profile.production_units_count || 0) > 0) {
        segments.push('production')
      }
      if ((profile.processing_units_count || 0) > 0) {
        segments.push('processing')
      }
      if ((profile.marketing_units_count || 0) > 0) {
        segments.push('marketing')
      }

      if (segments.length === 0) {
        return h('span', { class: 'text-sm text-slate-500 hidden md:inline' }, 'N/A')
      }

      return h(
        'div',
        { class: 'hidden md:flex flex-wrap gap-1' },
        segments.map(segment => {
          const label = props.valueChainSegments.labels[segment] || segment
          const color = props.valueChainSegments.colors[segment] || 'bg-gray-100 text-gray-800'

          return h(
            Badge,
            {
              class: `${color} text-xs font-medium px-2 py-1`,
              variant: 'secondary',
            },
            () => label
          )
        })
      )
    },
  },
  {
    accessorKey: 'category_size',
    header: 'Category',
    cell: ({ row }) => {
      const categorySize = row.original.category_size
      if (!categorySize) return h('span', { class: 'text-sm text-slate-500' }, 'N/A')
      return h(
        Badge,
        {
          class: `${getCategorySizeColor(categorySize)} text-xs font-medium px-2 py-1`,
          variant: 'secondary',
        },
        () => categorySize.charAt(0).toUpperCase() + categorySize.slice(1)
      )
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
      const profile = row.original
      const viewRoutes = {
        center: 'centers.show',
        partner: 'partners.show',
      }
      const editRoutes = {
        center: 'centers.edit',
        partner: 'partners.edit',
      }

      const viewRoute = viewRoutes[profile.profile_type]
      const editRoute = editRoutes[profile.profile_type]

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
                    {
                      variant: 'ghost',
                      class: 'h-8 w-8 p-0',
                    },
                    () =>
                      h(Icon, {
                        icon: 'lucide:more-horizontal',
                        class: 'h-4 w-4',
                      })
                  ),
              }
            ),
            h(
              DropdownMenuContent,
              { align: 'end', class: 'w-56 bg-white cursor-pointer' },
              {
                default: () =>
                  [
                    viewRoute
                      ? h(
                          DropdownMenuItem,
                          {
                            onClick: () => router.visit(route(viewRoute, profile.id)),
                            class: 'cursor-pointer hover:bg-gray-100',
                          },
                          {
                            default: () => [
                              h(Icon, { icon: 'lucide:eye', class: 'mr-2 h-4 w-4' }),
                              'View Details',
                            ],
                          }
                        )
                      : null,
                    editRoute
                      ? h(
                          DropdownMenuItem,
                          {
                            onClick: () => router.visit(route(editRoute, profile.id)),
                            class: 'cursor-pointer hover:bg-gray-100',
                          },
                          {
                            default: () => [
                              h(Icon, {
                                icon: 'lucide:pencil',
                                class: 'mr-2 h-4 w-4',
                              }),
                              `Edit ${profile.profile_type.charAt(0).toUpperCase()}${profile.profile_type.slice(1)}`,
                            ],
                          }
                        )
                      : null,
                    h(DropdownMenuSeparator),
                    h(
                      DropdownMenuItem,
                      {
                        onClick: () => deleteProfile(profile),
                        class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                      },
                      {
                        default: () => [
                          h(Icon, {
                            icon: 'lucide:trash-2',
                            class: 'mr-2 h-4 w-4',
                          }),
                          `Delete ${profile.profile_type.charAt(0).toUpperCase()}${profile.profile_type.slice(1)}`,
                        ],
                      }
                    ),
                  ].filter(Boolean),
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
    return (props.profiles.data || []).filter(profile => profile.profile_type !== 'farmer')
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

const profileTypes = [
  { value: 'center', label: 'Centers' },
  { value: 'partner', label: 'Partners' },
]

const ihOptions = [{ value: 'ih', label: 'Institutional Herd' }]

const cbedOptions = [
  { value: 'cooperative', label: 'Cooperative' },
  { value: 'association', label: 'Association' },
  { value: 'private', label: 'Private Business' },
  { value: 'suc', label: 'Schools, Universities, and Colleges' },
  { value: 'lgu', label: 'Local Government Units' },
  { value: 'ngo', label: 'Non-Governmental Organizations' },
]

const statusOptions = [
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
]

const handleUrlVisit = (type, id) => {
  console.log(type, id)
  if (type === 'center') {
    return route('centers.show', id)
  } else if (type === 'partner') {
    return route('partners.show', id)
  }
  return '#'
}

const handleRowClick = row => {
  const url = handleUrlVisit(row.original.profile_type, row.original.id)
  console.log('Row clicked:', url)
  if (url && url !== '#') {
    router.visit(url)
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
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Profiles</h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Unified view of centers and partners in the system.
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
        <DropdownMenu>
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

            <!-- Type -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('profile_type')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Type</span>
                <Icon
                  :icon="
                    openFilterSection === 'profile_type'
                      ? 'lucide:chevron-up'
                      : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'profile_type'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="type in profileTypes"
                :key="type.value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700':
                    currentFilters[`filter[profile_type]`] === type.value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'profile_type',
                    currentFilters[`filter[profile_type]`] === type.value ? null : type.value
                  )
                "
              >
                {{ type.label }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />

            <!-- IH -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('ih')"
            >
              <div class="flex w-full items-center justify-between">
                <span>IH</span>
                <Icon
                  :icon="openFilterSection === 'ih' ? 'lucide:chevron-up' : 'lucide:chevron-down'"
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'ih'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="ih in ihOptions"
                :key="ih.value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[category]`] === ih.value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'category',
                    currentFilters[`filter[category]`] === ih.value ? null : ih.value
                  )
                "
              >
                {{ ih.label }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />

            <!-- CBED -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('cbed')"
            >
              <div class="flex w-full items-center justify-between">
                <span>CBED</span>
                <Icon
                  :icon="openFilterSection === 'cbed' ? 'lucide:chevron-up' : 'lucide:chevron-down'"
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'cbed'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="cbed in cbedOptions"
                :key="cbed.value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[category]`] === cbed.value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'category',
                    currentFilters[`filter[category]`] === cbed.value ? null : cbed.value
                  )
                "
              >
                {{ cbed.label }}
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
                @click="router.visit(row.original.url)"
              >
                <TableCell
                  v-for="cell in row.getVisibleCells()"
                  :key="cell.id"
                  class="align-center border-none"
                  @click="cell.column.id === 'actions' ? $event.stopPropagation() : null"
                >
                  <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                </TableCell>
              </TableRow>
            </template>
            <TableRow v-else class="border-none hover:bg-transparent">
              <TableCell :colspan="columns.length" class="h-32 border-none text-center">
                <div class="flex flex-col items-center justify-center space-y-3">
                  <Icon icon="lucide:users" class="h-12 w-12 text-slate-300" />
                  <div class="text-center">
                    <h3 class="mb-1 text-lg font-medium text-slate-900">No profiles found</h3>
                    <p class="mb-4 text-slate-500">
                      Profiles will appear here as you add centers and partners.
                    </p>
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
          Showing {{ profiles.from || 0 }}–{{ profiles.to || 0 }} of
          {{ profiles.total || 0 }}
        </span>
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          :disabled="!profiles.prev_page_url"
          @click="handlePagination(profiles.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="hidden sm:inline">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="sm"
          :disabled="!profiles.next_page_url"
          @click="handlePagination(profiles.next_page_url)"
        >
          <span class="hidden sm:inline">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
