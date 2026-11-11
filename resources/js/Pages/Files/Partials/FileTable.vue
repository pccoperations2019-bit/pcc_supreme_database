<script setup>
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate, getInitials } from '@/lib/utils'
import debounce from 'lodash/debounce'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
  files: {
    type: Object,
    required: true,
  },
  regions: {
    type: Array,
    default: () => [],
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

  router.get(route('files.index'), params, {
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

  router.get(route('files.index'), params, {
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

const deleteFile = file => {
  if (confirm(`Are you sure you want to delete ${file.name}?`)) {
    router.delete(route('files.destroy', file.id))
  }
}

const columns = [
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
        () => ['Name', h(Icon, { icon: getSortIcon('name'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const file = row.original
      return h('a', { href: file.path, class: 'text-xs text-blue-600 hover:underline' }, file.name)
    },
  },
  {
    accessorKey: 'uploaded_by.name',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('uploaded_by.name'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Uploaded By',
          h(Icon, {
            icon: getSortIcon('uploaded_by.name'),
            class: 'ml-2 h-4 w-4',
          }),
        ]
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
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const file = row.original

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
                      onClick: () => router.visit(route('files.show', file.id)),
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
                      onClick: () => router.visit(route('files.edit', file.id)),
                      class: 'cursor-pointer hover:bg-gray-100',
                    },
                    {
                      default: () => [
                        h(Icon, {
                          icon: 'lucide:pencil',
                          class: 'mr-2 h-4 w-4',
                        }),
                        'Edit File',
                      ],
                    }
                  ),
                  h(DropdownMenuSeparator),
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () => deleteFile(file),
                      class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                    },
                    {
                      default: () => [
                        h(Icon, {
                          icon: 'lucide:trash-2',
                          class: 'mr-2 h-4 w-4',
                        }),
                        'Delete File',
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

// Table setup for display and selection
const columnVisibility = ref({})
const rowSelection = ref({})

const table = useVueTable({
  get data() {
    return props.files.data || []
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
    <div class="items-file flex justify-end">
      <div class="items-file flex space-x-2">
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
          <DropdownMenuContent align="end" class="w-64 bg-white">
            <DropdownMenuLabel>Region</DropdownMenuLabel>
            <DropdownMenuSeparator class="border-b border-slate-100" />
            <DropdownMenuCheckboxItem
              v-for="region in regions"
              :key="region"
              class="capitalize"
              :model-value="currentFilters[`filter[region]`] === region"
              @update:model-value="value => handleFilter('region', value ? region : null)"
            >
              {{ region }}
            </DropdownMenuCheckboxItem>
            <DropdownMenuLabel>Status</DropdownMenuLabel>
            <DropdownMenuSeparator class="border-b border-slate-100" />
            <DropdownMenuCheckboxItem
              v-for="status in ['active', 'inactive']"
              :key="status"
              class="capitalize"
              :model-value="currentFilters[`filter[status]`] === status"
              @update:model-value="value => handleFilter('status', value ? status : null)"
            >
              {{ status }}
            </DropdownMenuCheckboxItem>
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
          :href="route('files.create')"
          as="button"
          class="items-file inline-flex rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          Upload
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
              @click="router.visit(route('files.show', row.original.id))"
            >
              <TableCell
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                class="align-file border-none"
                @click.stop="
                  cell.column.id === 'select' || cell.column.id === 'actions' ? null : undefined
                "
              >
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>

          <TableRow v-else class="border-none hover:bg-transparent">
            <TableCell :colspan="columns.length" class="text-file h-32 border-none">
              <div class="items-file justify-file flex flex-col space-y-3">
                <Icon icon="lucide:building" class="h-12 w-12 text-slate-300" />
                <div class="text-file">
                  <h3 class="mb-1 text-lg font-medium text-slate-900">No files found</h3>
                  <p class="mb-4 text-slate-500">Get started by adding your first file.</p>
                  <Link
                    :href="route('files.create')"
                    as="button"
                    class="items-file inline-flex border-collapse rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
                  >
                    <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
                    Upload
                  </Link>
                </div>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
    <div class="items-file flex justify-between pt-4 text-xs font-semibold uppercase">
      <div class="items-file text-muted-foreground flex flex-wrap gap-2 text-zinc-600">
        <span>
          Showing {{ files.from || 0 }}–{{ files.to || 0 }} of
          {{ files.total || 0 }}
        </span>
      </div>
      <div class="items-file flex space-x-2">
        <Button
          variant="ghost"
          size="xs"
          :disabled="!files.prev_page_url"
          @click="handlePagination(files.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="text-xs font-semibold text-zinc-600 uppercase">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="xs"
          :disabled="!files.next_page_url"
          @click="handlePagination(files.next_page_url)"
        >
          <span class="text-xs font-semibold text-zinc-600 uppercase">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
