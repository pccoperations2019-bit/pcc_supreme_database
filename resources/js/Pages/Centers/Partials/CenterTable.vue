<script setup>
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater } from '@/lib/utils'
import debounce from 'lodash/debounce'
import { useAuth } from '@/composable/useAuth'

import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
  centers: {
    type: Array,
    required: true,
  },
  enums: {
    type: Object,
    default: () => ({}),
  },
  regions: {
    type: Array,
    default: () => [],
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
  can: {
    type: Object,
    default: () => ({}),
  },
  user_center_id: {
    type: Number,
    default: null,
  },
})

const page = usePage()
const { isAgencyAdmin, isCenterScoped } = useAuth()

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

  router.get(route('centers.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const searchParams = new URLSearchParams(window.location.search)
const currentFilters = reactive({
  [`filter[region]`]: searchParams.get('filter[region]'),
  [`filter[status]`]: searchParams.get('filter[status]'),
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

  router.get(route('centers.index'), params, {
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

// Removed pagination handling as it's a finite list

const getStatusColor = status => {
  const colors = {
    active: 'bg-green-100 text-green-800 hover:bg-green-200',
    inactive: 'bg-gray-100 text-gray-800 hover:bg-gray-200',
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

const deleteCenter = center => {
  if (confirm(`Are you sure you want to delete ${center.name}?`)) {
    router.delete(route('centers.destroy', center.id))
  }
}

const formatCenterAddress = address => {
  if (!address) return 'N/A'
  const { street, barangay, city, province, region } = address
  const formatted = [street, barangay, city, province, region].filter(Boolean).join(', ')
  return formatted || 'N/A'
}

const columns = [
  {
    accessorKey: 'name',
    enableHiding: false,
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('name'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Name', h(Icon, { icon: getSortIcon('name'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const center = row.original
      const centerLabel = () => {
        if (!center.code) return 'N/A'
        return props.enums?.center?.[center.code] || center.name
      }
      return h(
        Link,
        {
          class: 'flex flex-col space-y-1',
          href: route('centers.show', center.id),
        },
        [h('p', { class: 'text-sm font-medium text-slate-900 underline' }, centerLabel())]
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
      const center = row.original
      return h(
        'p',
        { class: 'text-sm text-slate-700' },
        center.address?.region_label || center.address?.region || 'N/A'
      )
    },
  },
  {
    id: 'address',
    header: 'Province',
    cell: ({ row }) => {
      const center = row.original
      return h(
        'p',
        { class: 'text-sm text-slate-700 break-words' },
        center.address?.province || 'N/A'
      )
    },
  },
  {
    accessorKey: 'value_chain_task',
    header: 'Value Chain Task',
    cell: ({ row }) => {
      const center = row.original
      const segments = []

      if ((center.production_units_count || 0) > 0) {
        segments.push('production')
      }
      if ((center.processing_units_count || 0) > 0) {
        segments.push('processing')
      }
      if ((center.marketing_units_count || 0) > 0) {
        segments.push('marketing')
      }

      if (segments.length === 0) {
        return h('span', { class: 'text-sm text-slate-500' }, 'N/A')
      }

      return h(
        'div',
        { class: 'flex flex-wrap gap-1' },
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
      const center = row.original

      const menuItems = [
        h(
          DropdownMenuItem,
          {
            onClick: () => router.visit(route('centers.show', center.id)),
            class: 'cursor-pointer hover:bg-gray-100',
          },
          {
            default: () => [h(Icon, { icon: 'lucide:eye', class: 'mr-2 h-4 w-4' }), 'View Details'],
          }
        ),
      ]

      const canEditCenter =
        isAgencyAdmin.value ||
        (isCenterScoped.value && props.user_center_id && center.id === props.user_center_id)

      if (canEditCenter) {
        menuItems.push(
          h(
            DropdownMenuItem,
            {
              onClick: () => router.visit(route('centers.edit', center.id)),
              class: 'cursor-pointer hover:bg-gray-100',
            },
            {
              default: () => [
                h(Icon, {
                  icon: 'lucide:pencil',
                  class: 'mr-2 h-4 w-4',
                }),
                'Edit Center',
              ],
            }
          )
        )
        menuItems.push(h(DropdownMenuSeparator))
        menuItems.push(
          h(
            DropdownMenuItem,
            {
              onClick: () => deleteCenter(center),
              class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
            },
            {
              default: () => [
                h(Icon, {
                  icon: 'lucide:trash-2',
                  class: 'mr-2 h-4 w-4',
                }),
                'Delete Center',
              ],
            }
          )
        )
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
                default: () => menuItems,
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
    return props.centers || []
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
  <div
    class="space-y-4 rounded-lg bg-white px-4 py-6 text-black shadow-sm sm:space-y-6 sm:px-6 sm:py-12"
  >
    <!-- Header -->
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Centers</h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Manage and view center profiles and information.
        </p>
      </div>
      <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-4">
        <!-- Filter -->
        <DropdownMenu v-if="regions && regions.length > 0" class="w-full sm:w-auto">
          <DropdownMenuTrigger as-child>
            <Button variant="outline" class="w-full cursor-pointer sm:w-auto">
              <Icon icon="lucide:filter" class="mr-2 h-4 w-4" />
              <span class="flex-1 text-left">Filter</span>
              <Icon icon="lucide:chevron-down" class="ml-2 h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            align="end"
            class="max-h-[50vh] w-72 overflow-y-auto bg-white sm:max-h-[70vh] sm:w-80"
          >
            <DropdownMenuLabel>Filter by Region</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem class="cursor-pointer" @click="handleFilter('region', null)">
              <Icon icon="lucide:list" class="mr-2 h-4 w-4" />
              All Regions
            </DropdownMenuItem>
            <DropdownMenuItem
              v-for="region in regions"
              :key="region"
              class="cursor-pointer capitalize"
              @click="handleFilter('region', region)"
            >
              {{ region }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <!-- New Center Button -->
        <Link
          v-if="can.create_center"
          :href="route('centers.create')"
          as="button"
          class="inline-flex w-full cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          <span class="flex-1 text-left">New Center</span>
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
                class="border-none transition-colors hover:bg-slate-50"
              >
                <TableCell
                  v-for="cell in row.getVisibleCells()"
                  :key="cell.id"
                  :class="[
                    'align-center border-none',
                    cell.column.id !== 'actions' && cell.column.id !== 'select'
                      ? 'cursor-pointer'
                      : '',
                  ]"
                  @click="
                    cell.column.id === 'select' || cell.column.id === 'actions'
                      ? null
                      : router.visit(route('centers.show', row.original.id))
                  "
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
                    <h3 class="mb-1 text-lg font-medium text-slate-900">No centers found</h3>
                    <p class="mb-4 text-slate-500">Get started by adding your first center.</p>
                    <Link
                      v-if="can.create_center"
                      :href="route('centers.create')"
                      as="button"
                      class="inline-flex cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
                    >
                      <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
                      <span class="hidden sm:inline">New Center</span>
                    </Link>
                  </div>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </div>
</template>
