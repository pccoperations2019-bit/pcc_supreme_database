<script setup>
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate, label, productNameLabel } from '@/lib/utils'
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
  products: {
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

  router.get(route('products.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const searchParams = new URLSearchParams(window.location.search)
const currentFilters = reactive({
  [`filter[category]`]: searchParams.get('filter[category]'),
  [`filter[sub_category]`]: searchParams.get('filter[sub_category]'),
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

  router.get(route('products.index'), params, {
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

const deleteProduct = product => {
  if (confirm(`Are you sure you want to delete ${product.name}?`)) {
    router.delete(route('products.destroy', product.id))
  }
}

const getCategoryBadgeColor = category => {
  const colors = {
    milk: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
    meat: 'bg-red-100 text-red-800 hover:bg-red-200',
    manure: 'bg-amber-100 text-amber-800 hover:bg-amber-200',
  }
  return colors[category] || 'bg-gray-100 text-gray-800'
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
        () => ['Product Name', h(Icon, { icon: getSortIcon('name'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const product = row.original
      return h('div', { class: 'flex flex-col space-y-1' }, [
        h(
          'p',
          { class: 'text-sm font-medium text-slate-900' },
          productNameLabel(product, props.enums)
        ),
        h('p', { class: 'text-xs text-slate-500' }, product.code || 'N/A'),
      ])
    },
  },
  {
    accessorKey: 'category',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('category'),
          class: 'p-0 h-auto font-medium',
        },
        () => ['Category', h(Icon, { icon: getSortIcon('category'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const product = row.original
      if (!product.category) return h('span', { class: 'text-sm text-slate-500' }, 'N/A')
      return h(
        Badge,
        { class: `capitalize ${getCategoryBadgeColor(product.category)}` },
        () => product.category
      )
    },
  },
  {
    accessorKey: 'sub_category',
    header: 'Sub Category',
    cell: ({ row }) => {
      const product = row.original

      if (!product.sub_category) return h('span', { class: 'text-sm text-slate-500' }, 'N/A')

      return h(
        'span',
        { class: 'text-sm text-slate-900' },
        label(props.enums?.productSubCategory, product.sub_category)
      )
    },
  },
  {
    accessorKey: 'size',
    header: 'Size',
    cell: ({ row }) => {
      const product = row.original
      return h('span', { class: 'text-sm text-slate-900' }, product.size || 'N/A')
    },
  },
  {
    accessorKey: 'cost_per_qty',
    header: 'Cost per Qty',
    cell: ({ row }) => {
      const product = row.original
      return h(
        'span',
        { class: 'text-sm font-medium text-slate-900' },
        product.cost_per_qty ? `₱${Number(product.cost_per_qty).toFixed(2)}` : '₱0.00'
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
      const product = row.original

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
                      onClick: () => router.visit(route('products.show', product.id)),
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
                      onClick: () => router.visit(route('products.edit', product.id)),
                      class: 'cursor-pointer hover:bg-gray-100',
                    },
                    {
                      default: () => [
                        h(Icon, {
                          icon: 'lucide:pencil',
                          class: 'mr-2 h-4 w-4',
                        }),
                        'Edit Product',
                      ],
                    }
                  ),
                  h(DropdownMenuSeparator),
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () => deleteProduct(product),
                      class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                    },
                    {
                      default: () => [
                        h(Icon, {
                          icon: 'lucide:trash-2',
                          class: 'mr-2 h-4 w-4',
                        }),
                        'Delete Product',
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
    return props.products.data || []
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
  <div class="space-y-6 rounded-lg bg-white px-6 py-12 text-black shadow-sm">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Products</h1>
        <p class="text-slate-600">Manage and view product information and inventory.</p>
      </div>
      <div class="flex items-center space-x-2">
        <!-- Filter -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" class="ml-auto">
              <Icon icon="lucide:filter" class="mr-2 h-4 w-4" />
              Filters
              <span
                v-show="Object.keys(currentFilterIndicator).length > 0"
                class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800"
                >{{ Object.keys(currentFilterIndicator).length }}</span
              >
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-64 bg-white">
            <DropdownMenuLabel>Category</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuCheckboxItem
              v-for="category in ['milk', 'meat', 'manure']"
              :key="category"
              class="capitalize"
              :model-value="currentFilters[`filter[category]`] === category"
              @update:model-value="value => handleFilter('category', value ? category : null)"
            >
              {{ category }}
            </DropdownMenuCheckboxItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <!-- Column Visibility -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" class="ml-auto">
              <Icon icon="lucide:columns" class="mr-2 h-4 w-4" />
              Columns
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="bg-white">
            <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuCheckboxItem
              v-for="column in table
                .getAllColumns()
                .filter(column => typeof column.accessorFn !== 'undefined' && column.getCanHide())"
              :key="column.id"
              class="capitalize"
              :checked="column.getIsVisible()"
              @update:checked="value => column.toggleVisibility(!!value)"
            >
              {{ column.id.replace('_', ' ') }}
            </DropdownMenuCheckboxItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <Link
          :href="route('products.create')"
          as="button"
          class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          New Product
        </Link>
      </div>
    </div>

    <!-- Table -->
    <div
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
              @click="router.visit(route('products.show', row.original.id))"
            >
              <TableCell
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                class="align-center border-none"
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
                <Icon icon="lucide:package" class="h-12 w-12 text-slate-300" />
                <div class="text-center">
                  <h3 class="mb-1 text-lg font-medium text-slate-900">No products found</h3>
                  <p class="mb-4 text-slate-500">Get started by adding your first product.</p>
                </div>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
    <div class="flex items-center justify-between pt-4 text-xs font-semibold uppercase">
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        <span>
          Showing {{ products.from || 0 }}–{{ products.to || 0 }} of
          {{ products.total || 0 }}
        </span>
      </div>
      <div class="flex items-center space-x-2">
        <Button
          variant="ghost"
          size="xs"
          :disabled="!products.prev_page_url"
          @click="handlePagination(products.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          Prev
        </Button>
        <Button
          variant="ghost"
          size="xs"
          :disabled="!products.next_page_url"
          @click="handlePagination(products.next_page_url)"
        >
          Next
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
