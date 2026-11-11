<script setup>
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate, label } from '@/lib/utils'
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core'
import { useAuth } from '@/composable/useAuth'

import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

const props = defineProps({
  services: {
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
  simplifiedCodes: {
    type: Array,
    default: () => [],
  },
})

const page = usePage()
const { isCenterScoped, canEnterData } = useAuth()

/**
 * Who may change the service catalogue.
 *
 * `!isCenterScoped` alone is a scope check, not a permission one: it also let the
 * read-only Executive through, which showed a New button (and Edit / Delete on
 * every row) for an account that must not write. Both conditions are required, so
 * this stays the PCC Administrator's page exactly as before.
 */
const canManageServices = computed(() => !isCenterScoped.value && canEnterData.value)
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

  router.get(route('services.index'), params, {
    only: ['services'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('md')

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
        only: ['services'],
        preserveState: true,
        preserveScroll: true,
      }
    )
  }
}

const deleteService = service => {
  if (confirm(`Are you sure you want to delete "${service.name}"?`)) {
    router.delete(route('services.destroy', service.id))
  }
}

const createSortableHeader = (label, column) => {
  return () =>
    h(
      Button,
      {
        variant: 'ghost',
        onClick: () => handleSort(column),
        class: 'p-0 h-auto font-medium',
      },
      () => [
        label,
        h(Icon, {
          icon: getSortIcon(column),
          class: 'ml-2 h-4 w-4',
        }),
      ]
    )
}

const columns = computed(() => {
  return [
    {
      accessorKey: 'simplified_code',
      header: createSortableHeader('Simplified Code', 'simplified_code'),
      cell: ({ row }) => {
        return h('span', { class: 'text-sm text-slate-900' }, row.original.simplified_code || 'N/A')
      },
    },
    {
      accessorKey: 'mfo_code',
      header: createSortableHeader('MFO Code', 'mfo_code'),
      cell: ({ row }) => {
        return h('span', { class: 'text-sm text-slate-900' }, row.original.mfo_code || 'N/A')
      },
    },
    {
      accessorKey: 'name',
      header: createSortableHeader('Service Name', 'name'),
      cell: ({ row }) => {
        const service = row.original
        return h('div', { class: 'flex flex-col space-y-1' }, [
          h('p', { class: 'text-sm font-medium text-slate-900' }, service.name || 'N/A'),
        ])
      },
    },
    {
      accessorKey: 'program',
      header: createSortableHeader('Program', 'program'),
      cell: ({ row }) => {
        const program = row.original.program
        if (!program) return h('span', { class: 'text-sm text-slate-500' }, 'N/A')
        return h(
          Badge,
          { class: 'bg-blue-100 text-blue-800 hover:bg-blue-200' },
          () => label(props.enums?.program || {}, program)?.toUpperCase() || program?.toUpperCase()
        )
      },
    },
    {
      id: 'actions',
      enableHiding: false,
      cell: ({ row }) => {
        const service = row.original

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
                        onClick: () => router.visit(route('services.show', service.id)),
                        class: 'cursor-pointer hover:bg-gray-100',
                      },
                      {
                        default: () => [
                          h(Icon, { icon: 'lucide:eye', class: 'mr-2 h-4 w-4' }),
                          'View Details',
                        ],
                      }
                    ),
                    // View only for an account that may not write: the row still
                    // opens, it just offers nothing to change.
                    ...(canManageServices.value
                      ? [
                          h(
                            DropdownMenuItem,
                            {
                              onClick: () => router.visit(route('services.edit', service.id)),
                              class: 'cursor-pointer hover:bg-gray-100',
                            },
                            {
                              default: () => [
                                h(Icon, {
                                  icon: 'lucide:pencil',
                                  class: 'mr-2 h-4 w-4',
                                }),
                                'Edit Service',
                              ],
                            }
                          ),
                          h(DropdownMenuSeparator),
                          h(
                            DropdownMenuItem,
                            {
                              onClick: () => deleteService(service),
                              class:
                                'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                            },
                            {
                              default: () => [
                                h(Icon, {
                                  icon: 'lucide:trash-2',
                                  class: 'mr-2 h-4 w-4',
                                }),
                                'Delete Service',
                              ],
                            }
                          ),
                        ]
                      : []),
                  ],
                }
              ),
            ],
          }
        )
      },
    },
  ]
})

// Table setup for display and selection
const columnVisibility = ref({})
const rowSelection = ref({})

const applyResponsiveVisibility = () => {
  if (!isMobile.value) {
    columnVisibility.value = {}
    return
  }

  const visible = new Set(['simplified_code', 'name', 'program', 'category', 'actions'])

  const nextVisibility = {}
  for (const col of columns.value) {
    const id = col.id ?? col.accessorKey
    if (!id) continue
    nextVisibility[id] = visible.has(id)
  }

  columnVisibility.value = nextVisibility
}

watch([isMobile], applyResponsiveVisibility, { immediate: true })

const table = useVueTable({
  get data() {
    return props.services.data || []
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
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Services</h1>
        <p class="text-sm text-slate-600 sm:text-base">Manage and view service records and data.</p>
      </div>
      <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-4">
        <Link
          v-if="canManageServices"
          :href="route('services.create')"
          as="button"
          class="inline-flex w-full cursor-pointer items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          <span class="flex-1 text-left">New Service</span>
        </Link>
      </div>
    </div>

    <div
      v-if="services?.data?.length"
      class="w-full overflow-x-auto rounded-lg border border-slate-200 bg-white"
    >
      <div class="min-w-[800px] sm:min-w-full">
        <Table>
          <TableCaption class="sr-only">A list of services.</TableCaption>
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
                class="cursor-pointer transition-colors hover:bg-slate-50"
                @click="
                  router.visit(route('services.show', row.original.id), {
                    preserveScroll: true,
                  })
                "
              >
                <TableCell
                  v-for="cell in row.getVisibleCells()"
                  :key="cell.id"
                  class="align-center"
                  @click.stop="cell.column.id === 'actions' ? null : undefined"
                >
                  <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                </TableCell>
              </TableRow>
            </template>
          </TableBody>
        </Table>
      </div>
    </div>
    <div
      v-else
      class="flex flex-col items-center gap-2 rounded-lg border border-slate-200 bg-white py-12 text-center"
    >
      <Icon icon="lucide:file-x" class="h-12 w-12 text-slate-300" />
      <h3 class="mb-1 text-lg font-medium text-slate-900">No services found</h3>
      <p class="text-sm text-slate-600">Get started by adding your first service.</p>
      <Link
        v-if="canManageServices"
        :href="route('services.create')"
        as="button"
        class="inline-flex cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
      >
        <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
        <span class="hidden sm:inline">New Service</span>
      </Link>
    </div>

    <div
      v-if="services?.data?.length"
      class="flex flex-col justify-between gap-4 pt-4 text-xs font-semibold uppercase sm:flex-row sm:items-center"
    >
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        Showing {{ services?.from || 0 }}–{{ services?.to || 0 }} of
        {{ services?.total || 0 }}
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          :disabled="!services?.prev_page_url"
          @click="handlePagination(services?.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="hidden sm:inline">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="sm"
          :disabled="!services?.next_page_url"
          @click="handlePagination(services?.next_page_url)"
        >
          <span class="hidden sm:inline">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
