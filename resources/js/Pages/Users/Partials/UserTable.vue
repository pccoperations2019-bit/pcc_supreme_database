<script setup>
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate, getInitials } from '@/lib/utils'
import { displayCenterLabel } from '@/lib/center'
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
import { useAuth } from '@/composable/useAuth'
import { roleBadgeColor } from '@/enums/UserRoleEnum'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

const props = defineProps({
  users: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  can: {
    type: Object,
    default: () => ({}),
  },
})

const page = usePage()
const { user: authUser, hasPermission } = useAuth()

/** Pending and rejected accounts are both still awaiting an approval decision. */
const awaitsApproval = status => ['pending', 'rejected'].includes(status)

/** The backend refuses self-approval, so never offer it on your own row. */
const canDecide = user =>
  hasPermission('approve-users') && authUser.value?.id !== user.id && awaitsApproval(user.status)

const decidingId = ref(null)

/**
 * `users.destroy` deactivates rather than deletes: the account's authored records
 * and its audit trail must survive, so the menu item says so rather than
 * promising a delete the backend will not perform.
 */
/** Only a deactivated account can be brought back. */
const isDeactivated = status => status === 'deactivated'

const reactivateUser = user => {
  router.patch(`/users/${user.id}/reactivate`, {}, { preserveState: true, preserveScroll: true })
}

const deactivateUser = user => {
  if (
    confirm(
      `Are you sure you want to deactivate ${user.name}? They will no longer be able to sign in, but their records are kept.`
    )
  ) {
    router.delete(route('users.destroy', user.id), { preserveScroll: true })
  }
}

const decide = (user, action) => {
  decidingId.value = user.id

  router.patch(
    `/users/${user.id}/${action}`,
    {},
    {
      preserveState: true,
      preserveScroll: true,
      onFinish: () => {
        decidingId.value = null
      },
    }
  )
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

  router.get(route('users.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const getSortIcon = column => {
  const currentSort =
    page.props.ziggy?.query?.sort || new URLSearchParams(window.location.search).get('sort')

  if (currentSort === column) return 'lucide:chevron-up'
  if (currentSort === `-${column}`) return 'lucide:chevron-down'
  return 'lucide:chevrons-up-down'
}

const searchParams = new URLSearchParams(window.location.search)
const globalSearch = ref(searchParams.get('search') || '')

const currentFilters = reactive({
  'filter[status]': searchParams.get('filter[status]'),
  'filter[role]': searchParams.get('filter[role]'),
  'filter[center_id]': searchParams.get('filter[center_id]'),
})

const currentFilterIndicator = computed(() => {
  return Object.keys(currentFilters).reduce((acc, key) => {
    if (currentFilters[key]) {
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

  router.get(route('users.index'), params, {
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

  router.get(route('users.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const getStatusColor = status => {
  const colors = {
    active: 'bg-green-100 text-green-800 hover:bg-green-200',
    pending: 'bg-amber-100 text-amber-800 hover:bg-amber-200',
    rejected: 'bg-red-100 text-red-800 hover:bg-red-200',
    deactivated: 'bg-gray-100 text-gray-800 hover:bg-gray-200',
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

const roleOptions = computed(() =>
  Object.entries(props.filters.role || {}).map(([value, label]) => ({ value, label }))
)

const statusOptions = computed(() =>
  Object.entries(props.filters.status || {}).map(([value, label]) => ({ value, label }))
)

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

const columns = [
  {
    accessorKey: 'name',
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
      const user = row.original
      return h('div', { class: 'flex items-center space-x-3' }, [
        h(
          'div',
          {
            class:
              'flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-medium text-slate-700',
          },
          getInitials(user.name)
        ),
        h('div', [
          h('div', { class: 'font-medium text-slate-900 underline' }, user.name || 'N/A'),
          h('div', { class: 'text-xs text-slate-500' }, user.email),
        ]),
      ])
    },
  },
  {
    accessorKey: 'username',
    header: () =>
      h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('username'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Username', h(Icon, { icon: getSortIcon('username'), class: 'ml-2 h-4 w-4' })]
      ),
    cell: ({ row }) => h('div', { class: 'text-slate-700' }, row.original.username),
  },
  {
    accessorKey: 'role',
    header: 'Role',
    cell: ({ row }) => {
      const roleName = row.original.roles?.[0]?.name
      return h(
        Badge,
        {
          variant: 'secondary',
          class: `${roleBadgeColor(roleName)} text-xs font-medium px-2 py-1`,
        },
        () => props.filters.role?.[roleName] || roleName || 'N/A'
      )
    },
  },
  {
    accessorKey: 'center',
    header: 'Center',
    cell: ({ row }) => {
      const center = row.original.center
      return h(
        'div',
        { class: 'text-slate-700' },
        center ? displayCenterLabel(center.code, center.name) : 'Whole agency'
      )
    },
  },
  {
    accessorKey: 'status',
    header: () =>
      h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('status'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Status', h(Icon, { icon: getSortIcon('status'), class: 'ml-2 h-4 w-4' })]
      ),
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
    accessorKey: 'created_at',
    header: () =>
      h(
        Button,
        {
          variant: 'ghost',
          onClick: () => handleSort('created_at'),
          class: 'p-0 h-auto font-medium text-left justify-start',
        },
        () => ['Created', h(Icon, { icon: getSortIcon('created_at'), class: 'ml-2 h-4 w-4' })]
      ),
    cell: ({ row }) => h('div', { class: 'text-slate-600' }, formatDate(row.original.created_at)),
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const user = row.original

      const menuItems = [
        h(
          DropdownMenuItem,
          {
            onClick: () => router.visit(route('users.show', user.id)),
            class: 'cursor-pointer hover:bg-gray-100',
          },
          {
            default: () => [h(Icon, { icon: 'lucide:eye', class: 'mr-2 h-4 w-4' }), 'View Details'],
          }
        ),
      ]

      if (hasPermission('update-users') && authUser.value?.id !== user.id) {
        menuItems.push(
          h(
            DropdownMenuItem,
            {
              onClick: () => router.visit(route('users.edit', user.id)),
              class: 'cursor-pointer hover:bg-gray-100',
            },
            {
              default: () => [
                h(Icon, { icon: 'lucide:pencil', class: 'mr-2 h-4 w-4' }),
                'Edit User',
              ],
            }
          )
        )
      }

      if (canDecide(user)) {
        menuItems.push(h(DropdownMenuSeparator))
        menuItems.push(
          h(
            DropdownMenuItem,
            {
              disabled: decidingId.value === user.id,
              onClick: () => decide(user, 'approve'),
              class: 'cursor-pointer hover:bg-gray-100',
            },
            {
              default: () => [h(Icon, { icon: 'lucide:check', class: 'mr-2 h-4 w-4' }), 'Approve'],
            }
          ),
          h(
            DropdownMenuItem,
            {
              disabled: decidingId.value === user.id,
              onClick: () => decide(user, 'reject'),
              class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
            },
            {
              default: () => [h(Icon, { icon: 'lucide:x', class: 'mr-2 h-4 w-4' }), 'Reject'],
            }
          )
        )
      }

      if (hasPermission('deactivate-users') && authUser.value?.id !== user.id) {
        menuItems.push(h(DropdownMenuSeparator))

        // Deactivation is reversible, so a deactivated row offers Reactivate in
        // place of Deactivate rather than both.
        menuItems.push(
          isDeactivated(user.status)
            ? h(
                DropdownMenuItem,
                {
                  onClick: () => reactivateUser(user),
                  class: 'cursor-pointer hover:bg-gray-100',
                },
                {
                  default: () => [
                    h(Icon, { icon: 'lucide:rotate-ccw', class: 'mr-2 h-4 w-4' }),
                    'Reactivate',
                  ],
                }
              )
            : h(
                DropdownMenuItem,
                {
                  onClick: () => deactivateUser(user),
                  class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                },
                {
                  default: () => [
                    h(Icon, { icon: 'lucide:trash-2', class: 'mr-2 h-4 w-4' }),
                    'Deactivate',
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

const columnVisibility = ref({})

const handleCellClick = (event, columnId) => {
  if (columnId === 'actions') {
    event.stopPropagation()
  }
}

const table = useVueTable({
  get data() {
    return props.users.data || []
  },
  columns,
  getCoreRowModel: getCoreRowModel(),
  onColumnVisibilityChange: updaterOrValue => valueUpdater(updaterOrValue, columnVisibility),
  state: {
    get columnVisibility() {
      return columnVisibility.value
    },
  },
})
</script>

<template>
  <div
    class="space-y-4 rounded-lg bg-white px-4 py-6 text-black shadow-sm sm:space-y-6 sm:px-6 sm:py-12"
  >
    <!-- Header -->
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">User Accounts</h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Accounts awaiting approval cannot sign in until they are approved.
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
            placeholder="Search by name or email..."
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
          <DropdownMenuContent align="end" class="w-72 bg-white">
            <DropdownMenuLabel>Filters</DropdownMenuLabel>
            <DropdownMenuSeparator class="border-b border-slate-100" />

            <!-- Role -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('role')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Role</span>
                <Icon
                  :icon="openFilterSection === 'role' ? 'lucide:chevron-up' : 'lucide:chevron-down'"
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'role'">
              <DropdownMenuItem
                v-for="role in roleOptions"
                :key="role.value"
                class="cursor-pointer pl-8"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[role]`] === role.value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'role',
                    currentFilters[`filter[role]`] === role.value ? null : role.value
                  )
                "
              >
                {{ role.label }}
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
            <div v-if="openFilterSection === 'status'">
              <DropdownMenuItem
                v-for="status in statusOptions"
                :key="status.value"
                class="cursor-pointer pl-8"
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

        <Link
          v-if="can.create"
          :href="route('users.create')"
          as="button"
          class="inline-flex w-full cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          <span class="flex-1 text-left">New User</span>
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
                class="cursor-pointer border-none transition-colors hover:bg-slate-50"
                @click="router.visit(route('users.show', row.original.id))"
              >
                <TableCell
                  v-for="cell in row.getVisibleCells()"
                  :key="cell.id"
                  class="align-center border-none"
                  @click="handleCellClick($event, cell.column.id)"
                >
                  <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                </TableCell>
              </TableRow>
            </template>
            <TableRow v-else class="border-none hover:bg-transparent">
              <TableCell :colspan="columns.length" class="h-32 border-none text-center">
                <div class="flex flex-col items-center justify-center space-y-3">
                  <Icon icon="lucide:user-cog" class="h-12 w-12 text-slate-300" />
                  <div class="text-center">
                    <h3 class="mb-1 text-lg font-medium text-slate-900">No users found</h3>
                    <p class="mb-4 text-slate-500">
                      User accounts will appear here once they are provisioned.
                    </p>
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
      v-if="users"
      class="flex flex-col justify-between gap-4 pt-4 text-xs font-semibold uppercase sm:flex-row sm:items-center"
    >
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        <span class="text-sm">
          Showing {{ users.from || 0 }}–{{ users.to || 0 }} of {{ users.total || 0 }}
        </span>
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          :disabled="!users.prev_page_url"
          @click="handlePagination(users.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          <span class="hidden sm:inline">Prev</span>
        </Button>
        <Button
          variant="ghost"
          size="sm"
          :disabled="!users.next_page_url"
          @click="handlePagination(users.next_page_url)"
        >
          <span class="hidden sm:inline">Next</span>
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
