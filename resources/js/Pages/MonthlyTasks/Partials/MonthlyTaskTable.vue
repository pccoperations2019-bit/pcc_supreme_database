<script setup>
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { computed, h, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'
import { displayCenterLabel } from '@/lib/center'
import { formatDate, getInitials } from '@/lib/utils'
import { Button } from '@/components/ui/button'
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
import { useAuth } from '@/composable/useAuth'
import {
  monthlyTaskDueLabel,
  monthlyTaskProductIcon,
  monthlyTaskProductLabel,
  monthlyTaskProgramLabel,
  monthlyTaskReviewBadgeClass,
  monthlyTaskReviewIcon,
  monthlyTaskReviewLabel,
  monthlyTaskTypeLabel,
} from '@/enums/MonthlyTaskEnum'
import GenerateTasksMenu from './GenerateTasksMenu.vue'
import ReviewTaskDialog from './ReviewTaskDialog.vue'
import ReturnReasonButton from './ReturnReasonButton.vue'
import TaskStatusBadge from './TaskStatusBadge.vue'

const props = defineProps({
  tasks: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  summary: { type: Object, default: () => ({}) },
  entryRoutes: { type: Object, default: () => ({}) },
})

const { hasPermission } = useAuth()

const canUpdate = computed(() => hasPermission('update-monthly-tasks'))

const canManage = computed(() => hasPermission('generate-monthly-tasks'))

const parseQuery = () => Object.fromEntries(new URLSearchParams(window.location.search))

const currentQuery = () => parseQuery()

const currentFilters = ref({
  'filter[center_id]': parseQuery()['filter[center_id]'] || null,
  'filter[year]': parseQuery()['filter[year]'] || null,
  'filter[month]': parseQuery()['filter[month]'] || null,
  'filter[program]': parseQuery()['filter[program]'] || null,
  'filter[product_type]': parseQuery()['filter[product_type]'] || null,
  'filter[task_type]': parseQuery()['filter[task_type]'] || null,
  'filter[status]': parseQuery()['filter[status]'] || null,
})

const visit = params => {
  delete params.page

  router.get(route('monthly-tasks.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const pushFilters = debounce(() => {
  const params = currentQuery()

  delete params['filter[search]']
  delete params['filter[overdue]']

  Object.entries(currentFilters.value).forEach(([key, value]) => {
    if (value) {
      params[key] = value
    } else {
      delete params[key]
    }
  })

  visit(params)
}, 300)

const toggleFilter = (key, value) => {
  currentFilters.value[`filter[${key}]`] =
    currentFilters.value[`filter[${key}]`] === value ? null : value
  pushFilters()
}

const activeFilterCount = computed(() => Object.values(currentFilters.value).filter(Boolean).length)

const sections = computed(() => {
  const options = list =>
    (list || []).map(item => ({
      value: String(item.value ?? item.id),
      label: item.label ?? item.name,
    }))

  const months = Object.entries(props.filters.months || {}).map(([value, label]) => ({
    value: String(value),
    label,
  }))

  return [
    {
      key: 'center_id',
      label: 'Center',
      options: props.centers.map(center => ({ value: String(center.id), label: center.name })),
      current: currentFilters.value['filter[center_id]'],
    },
    {
      key: 'year',
      label: 'Year',
      options: (props.filters.years || []).map(year => ({
        value: String(year),
        label: String(year),
      })),
      current: currentFilters.value['filter[year]'],
    },
    {
      key: 'month',
      label: 'Month',
      options: months,
      current: currentFilters.value['filter[month]'],
    },
    {
      key: 'program',
      label: 'Program',
      options: options(props.filters.programs),
      current: currentFilters.value['filter[program]'],
    },
    {
      key: 'product_type',
      label: 'Product',
      options: options(props.filters.products),
      current: currentFilters.value['filter[product_type]'],
    },
    {
      key: 'task_type',
      label: 'Task type',
      options: options(props.filters.task_types),
      current: currentFilters.value['filter[task_type]'],
    },
    {
      key: 'status',
      label: 'Status',
      options: options(props.filters.statuses),
      current: currentFilters.value['filter[status]'],
    },
  ].filter(section => section.options.length > 0)
})

const openFilterSection = ref(null)

const toggleFilterSection = section => {
  openFilterSection.value = openFilterSection.value === section ? null : section
}

const handlePagination = url => {
  if (url) {
    router.get(url, {}, { preserveState: true, preserveScroll: true })
  }
}

const reportNoData = (task, noDataReported) => {
  router.patch(
    route('monthly-tasks.status', task.id),
    { no_data_reported: noDataReported },
    { preserveScroll: true }
  )
}

const deleteTask = task => {
  if (
    !confirm(
      `Delete the ${monthlyTaskTypeLabel(task.task_type).toLowerCase()} task for ${task.period}?`
    )
  ) {
    return
  }

  router.delete(route('monthly-tasks.destroy', task.id), { preserveScroll: true })
}

const canDelete = computed(() => hasPermission('generate-monthly-tasks'))

const initials = name => {
  const [first = '', ...rest] = (name || '').trim().split(/\s+/)

  return getInitials(first, rest.join(' ')) || '?'
}

const columns = [
  {
    accessorKey: 'period',
    header: 'Period',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'whitespace-nowrap text-sm font-medium text-slate-900' },
        row.original.period
      ),
  },
  {
    accessorKey: 'center',
    header: 'Center',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'whitespace-nowrap text-sm text-slate-700' },
        displayCenterLabel(row.original.center?.code) || '—'
      ),
  },
  {
    accessorKey: 'program',
    header: 'Program',
    cell: ({ row }) =>
      h(
        'span',
        {
          class: `whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-medium ${
            row.original.program === 'ih'
              ? 'bg-blue-100 text-blue-800'
              : 'bg-green-100 text-green-800'
          }`,
        },
        monthlyTaskProgramLabel(row.original.program)
      ),
  },
  {
    accessorKey: 'product_type',
    header: 'Task',
    cell: ({ row }) => {
      const task = row.original
      const form = entryRouteFor(task)

      const label = [
        h(Icon, {
          icon: monthlyTaskProductIcon(task.product_type),
          class: 'h-4 w-4 text-slate-500',
          'aria-hidden': 'true',
        }),
        h(
          'span',
          { class: 'text-sm font-medium text-slate-900' },
          monthlyTaskTypeLabel(task.task_type)
        ),
        h(
          'span',
          { class: 'text-sm text-slate-500' },
          `· ${monthlyTaskProductLabel(task.product_type)}`
        ),
      ]

      const content = h(
        'span',
        {
          class: 'flex items-center gap-2 whitespace-nowrap underline',
          title: form ? `${form.label} — ${task.period}` : undefined,
        },
        label
      )

      if (!form) return h('div', { class: 'whitespace-nowrap' }, label)

      return h(
        'a',
        {
          href: form.url,
          target: '_blank',
          rel: 'noopener noreferrer',
          class:
            'inline-flex rounded focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none',
          'aria-label': `${form.label} for ${task.period}`,
          onClick: event => event.stopPropagation(),
        },
        content
      )
    },
  },
  {
    accessorKey: 'is_completed',
    header: 'Status',
    cell: ({ row }) => h(TaskStatusBadge, { task: row.original }),
  },
  {
    id: 'review_status',
    header: 'Review',
    cell: ({ row }) => {
      const task = row.original
      const reviewLabel = monthlyTaskReviewLabel(task)

      const content = []

      if (reviewLabel && task.status !== 'under_review') {
        content.push(
          h(
            'span',
            {
              class: `inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${monthlyTaskReviewBadgeClass(task)}`,
            },
            [
              h(Icon, {
                icon: monthlyTaskReviewIcon(task),
                class: 'h-3 w-3',
                'aria-hidden': 'true',
              }),
              reviewLabel,
            ]
          )
        )
      }

      if (task.return_reason) {
        content.push(h(ReturnReasonButton, { reason: task.return_reason }))
      }

      if (canReview.value && task.has_entered_data && task.is_awaiting_review) {
        content.push(
          h(
            Button,
            {
              size: 'sm',
              class: 'h-7 w-fit text-xs',
              onClick: event => {
                event.stopPropagation()
                openReview(task)
              },
            },
            {
              default: () => [
                h(Icon, { icon: 'lucide:clipboard-check', class: 'mr-1 h-3 w-3' }),
                'Review',
              ],
            }
          )
        )
      }

      if (content.length === 0) {
        return h('span', { class: 'text-sm text-slate-400' }, '—')
      }

      return h('div', { class: 'flex flex-col items-start gap-1' }, content)
    },
  },
  {
    accessorKey: 'due_date',
    header: 'Due',
    cell: ({ row }) => {
      const task = row.original

      const isLate = !task.is_resolved && task.status === 'overdue'

      return h('div', { class: 'flex flex-col whitespace-nowrap' }, [
        h('span', { class: 'text-sm text-slate-700' }, task.due_date || '—'),
        h(
          'span',
          {
            class: 'text-xs',
            'class:text-red-600': isLate,
            'class:text-slate-400': !isLate,
          },
          monthlyTaskDueLabel(task)
        ),
      ])
    },
  },
  {
    accessorKey: 'total_formatted',
    header: 'Total',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'whitespace-nowrap text-sm font-medium text-slate-900' },
        row.original.total_formatted || '—'
      ),
  },
  {
    accessorKey: 'reviewed_by_name',
    header: 'Reviewed by',
    cell: ({ row }) => {
      const name = row.original.reviewed_by_name
      const role = row.original.reviewed_by_role

      if (!name) {
        return h('span', { class: 'text-sm text-slate-400' }, '—')
      }

      return h('div', { class: 'flex max-w-[12rem] items-center gap-2', title: name }, [
        h(
          'span',
          {
            class:
              'flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-medium text-slate-600',
          },
          initials(name)
        ),
        h('div', { class: 'flex min-w-0 flex-col' }, [
          h('span', { class: 'truncate text-sm text-gray-900' }, name),
          role && h('span', { class: 'truncate text-xs text-slate-500' }, role),
        ]),
      ])
    },
  },
  {
    accessorKey: 'reviewed_at',
    header: 'Reviewed at',
    cell: ({ row }) =>
      row.original.reviewed_at
        ? h('div', { class: 'flex flex-col whitespace-nowrap' }, [
            h('span', { class: 'text-sm text-slate-500' }, formatDate(row.original.reviewed_at)),
            h(
              'span',
              { class: 'text-xs text-slate-400' },
              new Date(row.original.reviewed_at).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
              })
            ),
          ])
        : h('span', { class: 'text-sm text-slate-400' }, '—'),
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const task = row.original
      const form = entryRouteFor(task)
      const items = [
        h(
          DropdownMenuItem,
          {
            class: 'cursor-pointer',
            onSelect: () => router.visit(route('monthly-tasks.show', task.id)),
          },
          {
            default: () => [h(Icon, { icon: 'lucide:eye', class: 'mr-2 h-4 w-4' }), 'View details'],
          }
        ),
      ]

      if (form) {
        items.push(
          h(
            DropdownMenuItem,
            {
              class: 'cursor-pointer',
              onSelect: () => window.open(form.url, '_blank'),
            },
            {
              default: () => [
                h(Icon, { icon: 'lucide:plus-circle', class: 'mr-2 h-4 w-4' }),
                'Enter Data',
              ],
            }
          )
        )
      }

      if (canReview.value && task.has_entered_data && task.is_awaiting_review) {
        items.push(
          h(
            DropdownMenuItem,
            { class: 'cursor-pointer', onSelect: () => openReview(task) },
            {
              default: () => [
                h(Icon, { icon: 'lucide:clipboard-check', class: 'mr-2 h-4 w-4' }),
                'Review figures',
              ],
            }
          )
        )
      }

      if (canUpdate.value) {
        if (task.is_no_data_report) {
          if (!task.has_entered_data) {
            items.push(
              h(
                DropdownMenuItem,
                { class: 'cursor-pointer', onSelect: () => reportNoData(task, false) },
                {
                  default: () => [
                    h(Icon, { icon: 'lucide:undo-2', class: 'mr-2 h-4 w-4' }),
                    'Withdraw no-data report',
                  ],
                }
              )
            )
          }
        } else if (!task.has_entered_data) {
          items.push(
            h(
              DropdownMenuItem,
              { class: 'cursor-pointer', onSelect: () => reportNoData(task, true) },
              {
                default: () => [
                  h(Icon, { icon: 'lucide:circle-minus', class: 'mr-2 h-4 w-4' }),
                  'Report nothing to report',
                ],
              }
            )
          )
        }
      }

      if (canDelete.value) {
        items.push(
          h(DropdownMenuSeparator),
          h(
            DropdownMenuItem,
            {
              class: 'cursor-pointer text-red-600 focus:text-red-600',
              onSelect: () => deleteTask(task),
            },
            {
              default: () => [h(Icon, { icon: 'lucide:trash-2', class: 'mr-2 h-4 w-4' }), 'Delete'],
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
                    { default: () => h(Icon, { icon: 'lucide:more-horizontal', class: 'h-4 w-4' }) }
                  ),
              }
            ),
            h(
              DropdownMenuContent,
              { align: 'end', class: 'w-52 cursor-pointer bg-white' },
              { default: () => items }
            ),
          ],
        }
      )
    },
  },
]

const entryRouteFor = task => {
  const target = props.entryRoutes?.[`${task?.product_type}:${task?.task_type}`]

  if (!target) return null

  const axes = {
    center_id: task.center_id,
    program: task.program,
    year: task.year,
    month: task.month,
  }

  const query = target.prefilled
    ? axes
    : {
        'filter[center_id]': axes.center_id,
        'filter[year]': axes.year,
        'filter[month]': axes.month,
        'filter[program]': axes.program,
      }

  return { ...target, url: route(target.route, query) }
}

const canReview = computed(() => hasPermission('review-monthly-tasks'))

const reviewingTask = ref(null)

const reviewOpen = computed({
  get: () => reviewingTask.value !== null,
  set: isOpen => {
    if (!isOpen) reviewingTask.value = null
  },
})

const openReview = task => {
  reviewingTask.value = task
}

const table = useVueTable({
  get data() {
    return props.tasks.data || []
  },
  columns,
  getCoreRowModel: getCoreRowModel(),
  manualSorting: true,
  manualFiltering: true,
  manualPagination: true,
})
</script>

<template>
  <div class="w-full space-y-6 rounded-lg bg-white px-6 py-12 text-black shadow-sm">
    <dl
      class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
      aria-label="Monthly task summary"
    >
      <div class="rounded-lg border border-slate-200 px-4 py-3">
        <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Completion rate</dt>
        <dd class="mt-1 text-2xl font-semibold text-slate-900">
          {{ summary.completion_rate ?? 0 }}%
        </dd>
      </div>
      <div class="rounded-lg border border-slate-200 px-4 py-3">
        <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Completed</dt>
        <dd class="mt-1 text-2xl font-semibold text-emerald-700">{{ summary.completed ?? 0 }}</dd>
      </div>
      <div class="rounded-lg border border-blue-200 bg-blue-50/60 px-4 py-3">
        <dt class="text-xs font-medium tracking-wide text-blue-700 uppercase">Awaiting review</dt>
        <dd class="mt-1 text-2xl font-semibold text-blue-700">{{ summary.under_review ?? 0 }}</dd>
      </div>
      <div class="rounded-lg border border-rose-200 bg-rose-50/60 px-4 py-3">
        <dt class="text-xs font-medium tracking-wide text-rose-700 uppercase">Returned</dt>
        <dd class="mt-1 text-2xl font-semibold text-rose-700">{{ summary.returned ?? 0 }}</dd>
      </div>
      <div class="rounded-lg border border-slate-200 px-4 py-3">
        <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Pending</dt>
        <dd class="mt-1 text-2xl font-semibold text-amber-700">{{ summary.pending ?? 0 }}</dd>
      </div>
      <div class="rounded-lg border border-slate-200 px-4 py-3">
        <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Overdue</dt>
        <dd class="mt-1 text-2xl font-semibold text-red-700">{{ summary.overdue ?? 0 }}</dd>
      </div>
    </dl>

    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Monthly Tasks</h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Monthly production and sales reporting for Milk, Meat and Manure.
        </p>
      </div>

      <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-4">
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

        <GenerateTasksMenu
          v-if="canManage"
          :months="filters.months || {}"
          :years="filters.years || []"
        />

        <Link
          v-if="canManage"
          :href="route('monthly-tasks.create')"
          as="button"
          class="inline-flex w-full cursor-pointer items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          New Task
        </Link>
      </div>
    </div>

    <div class="w-full overflow-hidden overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <Table>
        <TableHeader>
          <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
            <TableHead
              v-for="header in headerGroup.headers"
              :key="header.id"
              class="bg-slate-50 font-semibold whitespace-nowrap text-slate-700"
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
              @click="router.visit(route('monthly-tasks.show', row.original.id))"
            >
              <TableCell
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                class="border-none align-middle whitespace-nowrap"
                @click.stop="cell.column.id === 'actions' ? null : undefined"
              >
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>
          <TableRow v-else class="border-none hover:bg-transparent">
            <TableCell :colspan="columns.length" class="h-32 border-none text-center">
              <div class="flex flex-col items-center justify-center space-y-2">
                <Icon icon="lucide:list-checks" class="h-10 w-10 text-slate-300" />
                <h3 class="text-lg font-medium text-slate-900">No monthly tasks found</h3>
                <p class="text-slate-500">Try changing or clearing the filters.</p>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div
      class="flex flex-col justify-between gap-4 pt-4 text-xs font-semibold uppercase sm:flex-row sm:items-center"
    >
      <span class="text-sm font-normal text-zinc-600">
        Showing {{ tasks.from || 0 }}–{{ tasks.to || 0 }} of {{ tasks.total || 0 }}
      </span>
      <div class="flex items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          :disabled="!tasks.prev_page_url"
          @click="handlePagination(tasks.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="hidden sm:inline">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="sm"
          :disabled="!tasks.next_page_url"
          @click="handlePagination(tasks.next_page_url)"
        >
          <span class="hidden sm:inline">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>

    <ReviewTaskDialog v-model:open="reviewOpen" :task="reviewingTask" />
  </div>
</template>
