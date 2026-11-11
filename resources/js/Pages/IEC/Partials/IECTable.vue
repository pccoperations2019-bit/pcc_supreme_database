<script setup>
import { displayCenterLabel } from '@/lib/center'
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate } from '@/lib/utils'
import debounce from 'lodash/debounce'
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core'
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
const { hasPermission } = useAuth()

const searchParams = new URLSearchParams(window.location.search)

const globalSearch = ref(searchParams.get('search') || '')

const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('md')

const handleGlobalSearch = debounce(value => {
  const params = { ...page.props.ziggy?.query }

  if (value) {
    params.search = value
  } else {
    delete params.search
  }

  router.get(route('iec.index'), params, {
    only: ['serviceRecords'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const handlePagination = url => {
  if (url) {
    router.get(
      url,
      {},
      {
        only: ['serviceRecords'],
        preserveState: true,
        preserveScroll: true,
      }
    )
  }
}

const deleteServiceRecord = serviceRecord => {
  if (confirm(`Are you sure you want to delete this service record?`)) {
    router.delete(route('service-records.destroy', serviceRecord.id))
  }
}

const columns = computed(() => {
  return [
    {
      accessorKey: 'center',
      header: 'Center',
      cell: ({ row }) => {
        const center = row.original.center
        return h('span', { class: 'text-sm text-slate-900' }, displayCenterLabel(center.code))
      },
    },
    {
      accessorKey: 'client_name',
      header: 'Client Name',
      cell: ({ row }) => {
        const farmer = row.original.farmer
        const name = row.original.client_name || 'N/A'
        if (farmer?.id) {
          return h(
            Link,
            {
              href: route('farmers.show', farmer.id),
              class: 'text-sm font-medium text-slate-900 underline',
            },
            name
          )
        }
        return h('span', { class: 'text-sm text-slate-900' }, name)
      },
    },
    {
      accessorKey: 'no_animals_served',
      header: 'Animals Served',
      cell: ({ row }) => {
        const count = row.original.no_animals_served
        return h('span', { class: 'text-sm text-slate-900' }, count ?? 0)
      },
    },
    {
      accessorKey: 'month_submitted',
      header: 'Month Submitted',
      cell: ({ row }) => {
        const date = row.original.month_submitted
        const month = date ? new Date(date).toLocaleDateString('en-US', { month: 'long' }) : null
        return h('span', { class: 'text-sm text-slate-900' }, month || 'N/A')
      },
    },
    {
      accessorKey: 'region',
      header: 'Region',
      cell: ({ row }) => {
        const address = row.original.address
        return h(
          'span',
          { class: 'text-sm text-slate-900' },
          address?.region_label || address?.region || 'N/A'
        )
      },
    },
    {
      accessorKey: 'province',
      header: 'Province',
      cell: ({ row }) => {
        const address = row.original.address
        return h('span', { class: 'text-sm text-slate-900' }, address?.province || 'N/A')
      },
    },
    {
      accessorKey: 'municipality',
      header: 'Municipality',
      cell: ({ row }) => {
        const address = row.original.address
        return h('span', { class: 'text-sm text-slate-900' }, address?.city || 'N/A')
      },
    },
    {
      accessorKey: 'date_conducted',
      header: 'Date Conducted',
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900' },
          formatDate(row.original.date_conducted) || 'N/A'
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
                  default: () => {
                    const items = [
                      h(
                        DropdownMenuItem,
                        {
                          onClick: () =>
                            router.visit(route('iec.show', serviceRecord.id)),
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
                            onClick: () =>
                              router.visit(route('iec.edit', serviceRecord.id)),
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
})

const columnVisibility = ref({})
const rowSelection = ref({})

const applyResponsiveVisibility = () => {
  if (!isMobile.value) {
    columnVisibility.value = {}
    return
  }

  const visible = new Set([
    'center',
    'client_name',
    'project',
    'no_animals_served',
    'region',
    'province',
    'municipality',
    'month_submitted',
    'date_conducted',
    'actions',
  ])

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
    return props.serviceRecords?.data || []
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
  manualFiltering: true,
  manualPagination: true,
})
</script>

<template>
  <div
    class="space-y-4 rounded-lg bg-white px-4 py-6 text-black shadow-sm sm:space-y-6 sm:px-6 sm:py-12"
  >
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">
          Information, Education, and Communication
        </h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Manage and view IEC service records and data.
        </p>
      </div>
      <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-4">
        <div class="relative w-full sm:w-64">
          <Icon
            icon="lucide:search"
            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
          />
          <Input
            v-model="globalSearch"
            placeholder="Search by Client Name..."
            class="w-full pl-9"
            @input="handleGlobalSearch(globalSearch)"
          />
        </div>

        <Link
          v-if="hasPermission('enter-data')"
          :href="route('iec.create')"
          as="button"
          class="inline-flex w-full cursor-pointer items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          <span class="flex-1 text-left">New Service Record</span>
        </Link>
      </div>
    </div>

    <div
      v-if="serviceRecords?.data?.length"
      class="w-full overflow-x-auto rounded-lg border border-slate-200 bg-white"
    >
      <div class="min-w-[800px] sm:min-w-full">
        <Table>
          <TableCaption class="sr-only">A list of service records.</TableCaption>
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
                  router.visit(route('iec.show', row.original.id), {
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
      <h3 class="mb-1 text-lg font-medium text-slate-900">No service records found</h3>
      <p class="text-sm text-slate-600">Get started by creating a new service record.</p>
      <Link
        v-if="hasPermission('enter-data')"
        :href="route('iec.create')"
        as="button"
        class="inline-flex cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
      >
        <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
        <span class="hidden sm:inline">New Service Record</span>
      </Link>
    </div>

    <div
      v-if="serviceRecords?.data?.length"
      class="flex flex-col justify-between gap-4 pt-4 text-xs font-semibold uppercase sm:flex-row sm:items-center"
    >
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        Showing {{ serviceRecords?.from || 0 }}–{{ serviceRecords?.to || 0 }} of
        {{ serviceRecords?.total || 0 }}
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          :disabled="!serviceRecords?.prev_page_url"
          @click="handlePagination(serviceRecords?.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="hidden sm:inline">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="sm"
          :disabled="!serviceRecords?.next_page_url"
          @click="handlePagination(serviceRecords?.next_page_url)"
        >
          <span class="hidden sm:inline">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
