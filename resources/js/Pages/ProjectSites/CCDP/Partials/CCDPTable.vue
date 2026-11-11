<script setup>
import { displayCenterLabel } from '@/lib/center'
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate } from '@/lib/utils'
import debounce from 'lodash/debounce'
import { useAuth } from '@/composable/useAuth'

import { Button } from '@/components/ui/button'
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
  projectSites: { type: Object, required: true },
  enums: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const page = usePage()
const { hasPermission } = useAuth()

const searchParams = new URLSearchParams(window.location.search)
const globalSearch = ref(searchParams.get('search') || '')

const handleGlobalSearch = debounce(value => {
  const params = { ...page.props.ziggy?.query }

  if (value) {
    params.search = value
  } else {
    delete params.search
  }

  router.get(route('ccdp-project-sites.index'), params, {
    only: ['projectSites'],
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

  router.get(route('ccdp-project-sites.index'), params, {
    only: ['projectSites'],
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

const deleteProjectSite = projectSite => {
  if (
    confirm(
      `Are you sure you want to delete ${projectSite.project_site_code || 'this project site'}?`
    )
  ) {
    router.delete(route('ccdp-project-sites.destroy', projectSite.id))
  }
}

const getCenterLabel = centerId => {
  const centers = props.enums?.centers || {}
  return centers[centerId] || 'Unknown Center'
}

const getPartnerLabel = partnerId => {
  const partners = props.enums?.partners || {}
  return partners[partnerId] || 'Unknown Partner'
}

const getProjectLabel = projectId => {
  const projects = props.enums?.projects || {}
  return projects[projectId] || 'Unknown Project'
}

const columns = [
  {
    accessorKey: 'center_id',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('center_id'),
          class: 'p-0 h-auto font-medium',
        },
        () => ['Center', h(Icon, { icon: getSortIcon('center_id'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const projectSite = row.original
      return h(
        'span',
        { class: 'text-sm font-medium' },
        projectSite.center
          ? displayCenterLabel(projectSite.center.code)
          : getCenterLabel(projectSite.center_id)
      )
    },
  },
  {
    accessorKey: 'production_partner_id',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('production_partner_id'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Partner',
          h(Icon, { icon: getSortIcon('production_partner_id'), class: 'ml-2 h-4 w-4' }),
        ]
      )
    },
    cell: ({ row }) => {
      const projectSite = row.original
      return h(
        'span',
        { class: 'text-sm font-medium' },
        projectSite.partner
          ? projectSite.partner.name
          : getPartnerLabel(projectSite.production_partner_id)
      )
    },
  },
  {
    accessorKey: 'project_id',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('project_id'),
          class: 'p-0 h-auto font-medium',
        },
        () => ['Project', h(Icon, { icon: getSortIcon('project_id'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const projectSite = row.original
      return h(
        'span',
        { class: 'text-sm font-medium' },
        projectSite.project && projectSite.project.program
          ? projectSite.project.program.toUpperCase()
          : getProjectLabel(projectSite.project_id)
      )
    },
  },
  {
    accessorKey: 'year',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('year'),
          class: 'p-0 h-auto font-medium',
        },
        () => ['Year', h(Icon, { icon: getSortIcon('year'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const projectSite = row.original
      return h('span', { class: 'text-sm' }, projectSite.year || 'N/A')
    },
  },
  {
    accessorKey: 'inauguration_date',
    header: () => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('inauguration_date'),
          class: 'p-0 h-auto font-medium',
        },
        () => [
          'Inauguration Date',
          h(Icon, { icon: getSortIcon('inauguration_date'), class: 'ml-2 h-4 w-4' }),
        ]
      )
    },
    cell: ({ row }) => {
      const projectSite = row.original
      return h(
        'span',
        { class: 'text-sm' },
        projectSite.inauguration_date ? formatDate(projectSite.inauguration_date) : 'N/A'
      )
    },
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const projectSite = row.original

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
                      default: () => h(Icon, { icon: 'lucide:more-horizontal', class: 'h-4 w-4' }),
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
                          router.visit(route('ccdp-project-sites.show', projectSite.id)),
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
                            router.visit(route('ccdp-project-sites.edit', projectSite.id)),
                          class: 'cursor-pointer hover:bg-gray-100',
                        },
                        {
                          default: () => [
                            h(Icon, { icon: 'lucide:pencil', class: 'mr-2 h-4 w-4' }),
                            'Edit Project Site',
                          ],
                        }
                      ),
                      h(DropdownMenuSeparator),
                      h(
                        DropdownMenuItem,
                        {
                          onClick: () => deleteProjectSite(projectSite),
                          class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                        },
                        {
                          default: () => [
                            h(Icon, { icon: 'lucide:trash-2', class: 'mr-2 h-4 w-4' }),
                            'Delete Project Site',
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

const columnVisibility = ref({})
const rowSelection = ref({})

const table = useVueTable({
  get data() {
    return props.projectSites.data || []
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
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">CCDP Project Sites</h1>
        <p class="text-slate-600">Manage and view CCDP project site information and details.</p>
      </div>
      <div class="flex items-center space-x-2">
        <div class="relative w-full sm:w-64">
          <Icon
            icon="lucide:search"
            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
          />
          <Input
            :model-value="globalSearch"
            type="text"
            placeholder="Search by partner name..."
            class="w-full pl-9"
            @input="handleSearchInput"
          />
        </div>
        <Link
          v-if="hasPermission('enter-data')"
          :href="
            route(
              'ccdp-project-sites.create',
              page.props.program ? { program: page.props.program } : {}
            )
          "
          as="button"
          class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          New CCDP Project Site
        </Link>
      </div>
    </div>

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
              @click="router.visit(route('ccdp-project-sites.show', row.original.id))"
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
                <Icon icon="lucide:map-pin" class="h-12 w-12 text-slate-300" />
                <div class="text-center">
                  <h3 class="mb-1 text-lg font-medium text-slate-900">
                    No CCDP project sites found
                  </h3>
                  <p class="mb-4 text-slate-500">
                    Get started by adding your first CCDP project site.
                  </p>
                </div>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
    <div class="flex items-center justify-between pt-4 text-xs font-semibold uppercase">
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        <span
          >Showing {{ projectSites.from || 0 }}–{{ projectSites.to || 0 }} of
          {{ projectSites.total || 0 }}</span
        >
      </div>
      <div class="flex items-center space-x-2">
        <Button
          variant="ghost"
          size="xs"
          :disabled="!projectSites.prev_page_url"
          @click="handlePagination(projectSites.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          Prev
        </Button>
        <Button
          variant="ghost"
          size="xs"
          :disabled="!projectSites.next_page_url"
          @click="handlePagination(projectSites.next_page_url)"
        >
          Next
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
