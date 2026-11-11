<script setup>
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate } from '@/lib/utils'
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
  projects: {
    type: Object,
    required: true,
  },
  enums: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
})

const page = usePage()
const { isCenterScoped } = useAuth()

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

  router.get(route('projects.index'), params, {
    only: ['projects'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const searchParams = new URLSearchParams(window.location.search)
const currentFilters = reactive({
  [`filter[program]`]: searchParams.get('filter[program]'),
  [`filter[is_funded]`]: searchParams.get('filter[is_funded]'),
})

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

const handleFilter = debounce((key, value) => {
  const params = { ...page.props.ziggy?.query }

  if (value) {
    params[`filter[${key}]`] = value
    currentFilters[`filter[${key}]`] = value
  } else {
    delete params[`filter[${key}]`]
    delete currentFilters[`filter[${key}]`]
  }

  router.get(route('projects.index'), params, {
    only: ['projects'],
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
        only: ['projects'],
        preserveState: true,
        preserveScroll: true,
      }
    )
  }
}

const deleteProject = project => {
  if (confirm(`Are you sure you want to delete ${project.name}?`)) {
    router.delete(route('projects.destroy', project.id))
  }
}

const getProgramLabel = programValue => {
  const programs = props.enums?.programs || {}
  return programs[programValue] || programValue
}

const getProgramBadgeColor = program => {
  const colors = {
    cbed: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
    pl480: 'bg-green-100 text-green-800 hover:bg-green-200',
    dhb: 'bg-purple-100 text-purple-800 hover:bg-purple-200',
    cbin_ccdp: 'bg-orange-100 text-orange-800 hover:bg-orange-200',
  }
  return colors[program] || 'bg-gray-100 text-gray-800'
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
        () => ['Project Name', h(Icon, { icon: getSortIcon('name'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const project = row.original
      return h('div', { class: 'flex flex-col space-y-1' }, [
        h('p', { class: 'text-sm font-medium text-slate-900' }, project.name || 'N/A'),
        h(
          'p',
          { class: 'text-xs text-slate-500' },
          project.description ? project.description.substring(0, 50) + '...' : 'No description'
        ),
      ])
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
          class: 'p-0 h-auto font-medium',
        },
        () => ['Program', h(Icon, { icon: getSortIcon('program'), class: 'ml-2 h-4 w-4' })]
      )
    },
    cell: ({ row }) => {
      const project = row.original
      return h(
        'span',
        { class: 'text-sm font-medium' },
        project.program ? getProgramLabel(project.program) : 'N/A'
      )
    },
  },
  {
    accessorKey: 'is_funded',
    header: 'Funding Status',
    cell: ({ row }) => {
      const project = row.original
      return h(
        Badge,
        {
          class: project.is_funded
            ? 'bg-green-100 text-green-800 hover:bg-green-200'
            : 'bg-gray-100 text-gray-800 hover:bg-gray-200',
        },
        () => (project.is_funded ? 'Funded' : 'Unfunded')
      )
    },
  },
  {
    accessorKey: 'project_involvement',
    header: 'Project Involvement',
    cell: ({ row }) => {
      const project = row.original
      const involvement = project.project_involvement

      if (!involvement || !Array.isArray(involvement) || involvement.length === 0) {
        return h('span', { class: 'text-sm text-slate-500' }, 'N/A')
      }

      return h(
        'div',
        { class: 'flex flex-wrap gap-1' },
        involvement.map(item =>
          h(
            Badge,
            {
              class: 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200 text-xs',
              variant: 'secondary',
            },
            () => item
          )
        )
      )
    },
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const project = row.original

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
                      onClick: () => router.visit(route('projects.show', project.id)),
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
                      onClick: () => router.visit(route('projects.edit', project.id)),
                      class: 'cursor-pointer hover:bg-gray-100',
                    },
                    {
                      default: () => [
                        h(Icon, {
                          icon: 'lucide:pencil',
                          class: 'mr-2 h-4 w-4',
                        }),
                        'Edit Project',
                      ],
                    }
                  ),
                  h(DropdownMenuSeparator),
                  h(
                    DropdownMenuItem,
                    {
                      onClick: () => deleteProject(project),
                      class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                    },
                    {
                      default: () => [
                        h(Icon, {
                          icon: 'lucide:trash-2',
                          class: 'mr-2 h-4 w-4',
                        }),
                        'Delete Project',
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
    return props.projects.data || []
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
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Projects</h1>
        <p class="text-slate-600">Manage and view project information and program details.</p>
      </div>
      <div class="flex items-center space-x-2">
        <!-- Filter -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline">
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
                v-for="(labelText, value) in enums?.programs || {}"
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
            <DropdownMenuSeparator />

            <!-- Funding Status -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('is_funded')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Funding Status</span>
                <Icon
                  :icon="
                    openFilterSection === 'is_funded' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'is_funded'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="status in [
                  { label: 'Funded', value: '1' },
                  { label: 'Unfunded', value: '0' },
                ]"
                :key="status.value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700':
                    currentFilters[`filter[is_funded]`] === status.value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'is_funded',
                    currentFilters[`filter[is_funded]`] === status.value ? null : status.value
                  )
                "
              >
                {{ status.label }}
              </DropdownMenuItem>
            </div>
          </DropdownMenuContent>
        </DropdownMenu>
        <Link
          v-if="!isCenterScoped"
          :href="route('projects.create')"
          as="button"
          class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          New Project
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
              @click="router.visit(route('projects.show', row.original.id))"
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
                <Icon icon="lucide:folder-kanban" class="h-12 w-12 text-slate-300" />
                <div class="text-center">
                  <h3 class="mb-1 text-lg font-medium text-slate-900">No projects found</h3>
                  <p class="mb-4 text-slate-500">Get started by adding your first project.</p>
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
          Showing {{ projects.from || 0 }}–{{ projects.to || 0 }} of
          {{ projects.total || 0 }}
        </span>
      </div>
      <div class="flex items-center space-x-2">
        <Button
          variant="ghost"
          size="xs"
          :disabled="!projects.prev_page_url"
          @click="handlePagination(projects.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          Prev
        </Button>
        <Button
          variant="ghost"
          size="xs"
          :disabled="!projects.next_page_url"
          @click="handlePagination(projects.next_page_url)"
        >
          Next
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
