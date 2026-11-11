<script setup>
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'
import { formatDate, getInitials } from '@/lib/utils'
import { useAuth } from '@/composable/useAuth'
import { auditActionBadgeClass, auditActionLabels } from '@/enums/AuditActionEnum'
import AuditDetailDialog from '@/components/AuditDetailDialog.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

/**
 * The audit listing: a header, filters, and one row per activity.
 *
 * Filtering and sorting happen on the server through Spatie\QueryBuilder, so the
 * sort and filter handlers here only translate the current table state into the
 * query string the backend already understands. Every row is already described
 * by the backend - labels instead of event slugs, a name instead of an id - so
 * this component never inspects raw activity properties.
 */
const props = defineProps({
  activities: { type: Object, required: true },
  filters: { type: Object, required: false, default: () => ({}) },
})

const page = usePage()

const { isCenterScoped } = useAuth()

const query = () => ({ ...page.props.ziggy?.query })

const searchParams = new URLSearchParams(window.location.search)

/**
 * The URL is the single source of truth for the table, so every control here
 * reads from it and writes back to it. That keeps the dropdown, the date range,
 * the pagination links and the browser back button in step.
 */
const currentFilters = reactive({
  'filter[category]': searchParams.get('filter[category]'),
  'filter[action]': searchParams.get('filter[action]'),
  'filter[causer]': searchParams.get('filter[causer]'),
  'filter[center]': searchParams.get('filter[center]'),
  'filter[from]': searchParams.get('filter[from]'),
  'filter[to]': searchParams.get('filter[to]'),
})

const fromDate = computed(() => currentFilters['filter[from]'] || '')
const toDate = computed(() => currentFilters['filter[to]'] || '')

const dateRangeLabel = computed(() => {
  if (fromDate.value && toDate.value) {
    return `${formatDate(fromDate.value)} - ${formatDate(toDate.value)}`
  }

  if (fromDate.value) {
    return `From ${formatDate(fromDate.value)}`
  }

  if (toDate.value) {
    return `To ${formatDate(toDate.value)}`
  }

  return 'Date Range'
})

/**
 * Apply filters and start again at the first page - a narrowing filter almost
 * always leaves the current page number out of range.
 */
const visit = params => {
  delete params.page

  router.get(route('audits.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

/**
 * Push every staged filter at once rather than the one that just changed, so
 * two quick clicks cannot overwrite each other while the request is debounced.
 */
const pushFilters = debounce(() => {
  const params = query()

  Object.entries(currentFilters).forEach(([key, value]) => {
    if (value) {
      params[key] = value
    } else {
      delete params[key]
    }
  })

  visit(params)
}, 300)

// Filter options arrive in the same value/label shape the existing select
// components expect. There is no "all" entry: picking the selected option again
// clears it, the same way the center table resets its region filter.
const categoryOptions = computed(() =>
  (props.filters.categories || []).map(item => ({ value: String(item.value), label: item.label }))
)
const actionOptions = computed(() =>
  (props.filters.actions || []).map(item => ({ value: String(item.value), label: item.label }))
)
const userOptions = computed(() =>
  (props.filters.users || []).map(user => ({ value: String(user.id), label: user.label }))
)
const centerOptions = computed(() =>
  (props.filters.centers || []).map(center => ({ value: String(center.id), label: center.label }))
)

const sections = computed(() =>
  [
    {
      key: 'category',
      label: 'Activity',
      options: categoryOptions.value,
      current: currentFilters['filter[category]'],
    },
    {
      key: 'action',
      label: 'Action',
      options: actionOptions.value,
      current: currentFilters['filter[action]'],
    },
    {
      key: 'causer',
      label: 'User',
      options: userOptions.value,
      current: currentFilters['filter[causer]'],
    },
    // A center-scoped viewer's listing is already pinned to its own center, so
    // there is nothing to choose here.
    isCenterScoped.value
      ? null
      : {
          key: 'center',
          label: 'Center',
          options: centerOptions.value,
          current: currentFilters['filter[center]'],
        },
  ].filter(Boolean)
)

/**
 * Only one filter section stays open at a time - the sections hold long option
 * lists (users, centers) and keeping them collapsed makes the dropdown
 * navigable.
 */
const openFilterSection = ref(null)

const toggleFilterSection = section => {
  openFilterSection.value = openFilterSection.value === section ? null : section
}

const activeFilterCount = computed(() => sections.value.filter(section => section.current).length)

/**
 * Selecting the already-selected option clears it, so the dropdown carries no
 * separate "all" row or clear button.
 */
const toggleFilter = (key, value) => {
  currentFilters[`filter[${key}]`] = currentFilters[`filter[${key}]`] === value ? null : value
  pushFilters()
}

const setDate = (key, value) => {
  currentFilters[`filter[${key}]`] = value || null

  // Dates are discrete picks, not keystrokes: navigate at once instead of
  // through the shared debounce, so a pick can never be swallowed by a
  // pending filter push.
  const params = query()

  Object.entries(currentFilters).forEach(([param, paramValue]) => {
    if (paramValue) {
      params[param] = paramValue
    } else {
      delete params[param]
    }
  })

  visit(params)
}

const clearDates = () => {
  currentFilters['filter[from]'] = null
  currentFilters['filter[to]'] = null
  pushFilters()
}

const handleSort = column => {
  const current = query().sort

  let next = column
  if (current === column) {
    next = `-${column}`
  } else if (current === `-${column}`) {
    next = null
  }

  const params = query()

  if (next) {
    params.sort = next
  } else {
    delete params.sort
  }

  visit(params)
}

const getSortIcon = column => {
  const current = query().sort

  if (current === column) return 'lucide:chevron-up'
  if (current === `-${column}`) return 'lucide:chevron-down'

  return 'lucide:chevrons-up-down'
}

const handlePagination = url => {
  if (url) {
    router.get(url, {}, { preserveState: true, preserveScroll: true })
  }
}

const initials = name => {
  const [first = '', ...rest] = (name || '').trim().split(/\s+/)

  return getInitials(first, rest.join(' ')) || '?'
}

const detail = ref({ open: false, activity: null })

const openDetail = activity => {
  detail.value = { open: true, activity }
}

const columns = [
  {
    accessorKey: 'activity',
    header: 'Activity',
    cell: ({ row }) => {
      const activity = row.original

      return h('div', { class: 'flex max-w-[16rem] flex-col', title: activity.activity }, [
        h('span', { class: 'truncate font-medium text-gray-900' }, activity.activity),
        activity.action &&
          h(
            Badge,
            {
              variant: 'secondary',
              class: `w-fit border ${auditActionBadgeClass(activity.action)}`,
            },
            () => auditActionLabels[activity.action] || activity.action
          ),
      ])
    },
  },
  {
    accessorKey: 'user',
    header: 'User',
    cell: ({ row }) => {
      const { user } = row.original

      if (!user) {
        return h('span', { class: 'text-sm text-slate-400' }, 'System')
      }

      return h('div', { class: 'flex max-w-[12rem] items-center gap-2', title: user.name }, [
        h(
          'span',
          {
            class:
              'flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-medium text-slate-600',
          },
          initials(user.name)
        ),
        h('div', { class: 'flex min-w-0 flex-col' }, [
          h('span', { class: 'truncate text-sm text-gray-900' }, user.name),
          user.role && h('span', { class: 'truncate text-xs text-slate-500' }, user.role),
        ]),
      ])
    },
  },
  {
    accessorKey: 'subject',
    header: 'Subject',
    cell: ({ row }) => {
      const { subject } = row.original

      if (!subject) {
        return h('span', { class: 'text-sm text-slate-400' }, '—')
      }

      return h('div', { class: 'flex max-w-[16rem] flex-col', title: subject.label || '' }, [
        subject.type && h('span', { class: 'truncate text-xs text-slate-500' }, subject.type),
        h('span', { class: 'truncate text-sm text-gray-900' }, subject.label || '—'),
      ])
    },
  },
  {
    accessorKey: 'organization',
    header: 'Organization',
    cell: ({ row }) => {
      const { organization } = row.original

      if (!organization) {
        return h('span', { class: 'text-sm text-slate-400' }, '—')
      }

      return h('div', { class: 'flex max-w-[14rem] flex-col', title: organization.name }, [
        h('span', { class: 'truncate text-sm text-gray-900' }, organization.name),
        h('span', { class: 'truncate text-xs text-slate-500' }, organization.code),
      ])
    },
  },
  {
    accessorKey: 'occurred_at',
    header: () =>
      h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('created_at'),
          class: 'p-0 h-auto font-medium',
        },
        () => ['Date & Time', h(Icon, { icon: getSortIcon('created_at'), class: 'ml-2 h-4 w-4' })]
      ),
    cell: ({ row }) =>
      h('div', { class: 'flex flex-col whitespace-nowrap' }, [
        h('span', { class: 'text-sm text-slate-500' }, formatDate(row.original.occurred_at)),
        h(
          'span',
          { class: 'text-xs text-slate-400' },
          new Date(row.original.occurred_at).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
          })
        ),
      ]),
  },
  {
    id: 'detail',
    enableHiding: false,
    cell: ({ row }) =>
      h(
        Button,
        {
          variant: 'ghost',
          size: 'xs',
          class:
            'gap-1 text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500',
          title: 'View activity details',
          onClick: () => openDetail(row.original),
        },
        { default: () => [h(Icon, { icon: 'lucide:eye', class: 'h-3.5 w-3.5' }), 'Details'] }
      ),
  },
]

const table = useVueTable({
  get data() {
    return props.activities.data || []
  },
  columns,
  getCoreRowModel: getCoreRowModel(),
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
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Audit Trail</h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Monitor and review important activities across the system.
        </p>
      </div>

      <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-4">
        <!-- Date range -->
        <Popover>
          <PopoverTrigger as-child>
            <Button variant="outline" class="w-full cursor-pointer sm:w-auto">
              <Icon icon="lucide:calendar" class="mr-2 h-4 w-4" />
              <span class="flex-1 text-left">{{ dateRangeLabel }}</span>
              <Icon icon="lucide:chevron-down" class="ml-2 h-4 w-4" />
            </Button>
          </PopoverTrigger>
          <PopoverContent align="end" class="w-72 space-y-3">
            <div class="space-y-1">
              <label for="audit_filter_from" class="block text-xs font-medium text-slate-700">
                From
              </label>
              <Input
                id="audit_filter_from"
                :model-value="fromDate"
                type="date"
                class="bg-white"
                @update:model-value="setDate('from', $event)"
              />
            </div>
            <div class="space-y-1">
              <label for="audit_filter_to" class="block text-xs font-medium text-slate-700"
                >To</label
              >
              <Input
                id="audit_filter_to"
                :model-value="toDate"
                type="date"
                class="bg-white"
                @update:model-value="setDate('to', $event)"
              />
            </div>
            <Button
              v-if="fromDate || toDate"
              variant="ghost"
              size="xs"
              class="w-full cursor-pointer"
              @click="clearDates"
            >
              Clear date range
            </Button>
          </PopoverContent>
        </Popover>

        <!-- Filters -->
        <DropdownMenu class="w-full sm:w-auto">
          <DropdownMenuTrigger as-child>
            <Button variant="outline" class="w-full cursor-pointer sm:w-auto">
              <Icon icon="lucide:filter" class="mr-2 h-4 w-4" />
              <span class="flex-1 text-left">Filters</span>
              <span
                v-show="activeFilterCount > 0"
                class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800"
              >
                {{ activeFilterCount }}
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

            <template v-for="(section, index) in sections" :key="section.key">
              <DropdownMenuSeparator v-if="index > 0" />
              <DropdownMenuItem
                class="cursor-pointer"
                @select.prevent
                @click="toggleFilterSection(section.key)"
              >
                <div class="flex w-full items-center justify-between">
                  <span>{{ section.label }}</span>
                  <Icon
                    :icon="
                      openFilterSection === section.key
                        ? 'lucide:chevron-up'
                        : 'lucide:chevron-down'
                    "
                    class="h-4 w-4 text-slate-500"
                  />
                </div>
              </DropdownMenuItem>
              <div v-if="openFilterSection === section.key" class="max-h-56 overflow-y-auto">
                <DropdownMenuItem
                  v-for="option in section.options"
                  :key="option.value"
                  class="cursor-pointer pl-8"
                  :class="{ 'bg-indigo-50 text-indigo-700': section.current === option.value }"
                  @select.prevent
                  @click="toggleFilter(section.key, option.value)"
                >
                  {{ option.label }}
                </DropdownMenuItem>
              </div>
            </template>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>

    <!-- ── Table ── -->
    <div class="w-full overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <div class="min-w-[700px] sm:min-w-full">
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
                class="cursor-pointer border-none transition-colors hover:bg-slate-50"
                @click="openDetail(row.original)"
              >
                <TableCell
                  v-for="cell in row.getVisibleCells()"
                  :key="cell.id"
                  class="border-none align-top"
                  @click.stop="cell.column.id === 'detail' ? null : undefined"
                >
                  <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                </TableCell>
              </TableRow>
            </template>
            <TableRow v-else class="border-none hover:bg-transparent">
              <TableCell
                :colspan="table.getVisibleLeafColumns().length"
                class="h-32 border-none text-center"
              >
                <div class="flex flex-col items-center justify-center space-y-2">
                  <Icon icon="lucide:file-search" class="h-10 w-10 text-slate-300" />
                  <div class="text-center">
                    <h3 class="mb-1 text-lg font-medium text-slate-900">No audit activity found</h3>
                    <p class="text-slate-500">Try changing your filters or date range.</p>
                  </div>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>

    <!-- Pagination -->
    <div
      class="flex flex-col justify-between gap-2 pt-2 text-xs font-semibold uppercase sm:flex-row sm:items-center"
    >
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        <span>
          Showing {{ activities.from || 0 }}–{{ activities.to || 0 }} of
          {{ activities.total || 0 }}
        </span>
      </div>
      <div class="flex items-center space-x-2">
        <Button
          variant="ghost"
          size="xs"
          :disabled="!activities.prev_page_url"
          @click="handlePagination(activities.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          Prev
        </Button>
        <Button
          variant="ghost"
          size="xs"
          :disabled="!activities.next_page_url"
          @click="handlePagination(activities.next_page_url)"
        >
          Next
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>

    <AuditDetailDialog v-model:open="detail.open" :activity="detail.activity" />
  </div>
</template>
