<script setup>
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate, formatDateTime } from '@/lib/utils'
import debounce from 'lodash/debounce'
import { useAuth } from '@/composable/useAuth'
import {
  documentStatusBadgeClass,
  documentStatusLabels,
  isEditableByUploader,
} from '@/lib/document'
import DocumentReviewDialog from '@/components/DocumentReviewDialog.vue'
import DocumentRejectionReasonDialog from '@/components/DocumentRejectionReasonDialog.vue'
import DocumentDetailDialog from '@/components/DocumentDetailDialog.vue'
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'

const { hasPermission } = useAuth()

const canReviewCenter = computed(() => props.documentPermissions?.can_review_center === true)
const canReviewAgency = computed(() => props.documentPermissions?.can_review_agency === true)
const canEdit = computed(() => props.documentPermissions?.can_edit === true)

/**
 * Deleting a document cascades to its file, so DocumentController::destroy()
 * allows only the PCC administrator. The page already ships that answer as
 * `documentPermissions.can_delete`; a scope check is not the same question, and
 * reading it as one offered Delete to the read-only Executive.
 */
const canDelete = computed(() => props.documentPermissions?.can_delete === true)

/**
 * Uploading is gated on 'submit-documents' (StoreDocumentRequest::authorize),
 * which the reviewer and the Executive do not hold. This button used to be
 * ungated, so both saw an Add Document button that 403'd on submit.
 */
const canUpload = computed(() => hasPermission('submit-documents'))

const reviewDialog = ref({ open: false, mode: 'approve', document: null, stage: 'center' })
const reasonDialog = ref({ open: false, document: null })
const detailDialog = ref({ open: false, document: null })

const openDetail = document => {
  detailDialog.value = { open: true, document }
}

const handleCellClick = (event, columnId) => {
  if (
    ['select', 'actions', 'review', 'detail'].includes(columnId) ||
    event.target?.closest?.('button, a, input, select, textarea, [role="button"]')
  ) {
    event.stopPropagation()
  }
}

const openReview = (mode, document) => {
  const status = document.status?.value || document.status
  reviewDialog.value = {
    open: true,
    mode,
    document,
    stage: status === 'pending_agency_review' ? 'agency' : 'center',
  }
}

const openReason = document => {
  reasonDialog.value = { open: true, document }
}

const props = defineProps({
  documentPermissions: {
    type: Object,
    required: false,
    default: () => ({}),
  },
  documents: {
    type: Object,
    required: true,
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

  router.get(route('documents.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const statusOptions = computed(() => {
  const statuses = props.enums?.documentStatuses || page.props?.enums?.documentStatuses || {}
  return Object.entries(statuses).map(([value, label]) => ({ value, label }))
})

const openFilterSection = ref(null)

const toggleFilterSection = section => {
  openFilterSection.value = openFilterSection.value === section ? null : section
}

const searchParams = new URLSearchParams(window.location.search)
const currentFilters = reactive({
  [`filter[title]`]: searchParams.get('filter[title]'),
  [`filter[status]`]: searchParams.get('filter[status]'),
})

const currentFilterIndicator = computed(() => {
  return Object.keys(currentFilters).reduce((acc, key) => {
    if (currentFilters[key]) acc[key] = currentFilters[key]
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
  router.get(route('documents.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const getSortIcon = column => {
  const currentSort =
    page.props.ziggy?.query?.sort || new URLSearchParams(window.location.search).get('sort')
  if (currentSort === column) return 'lucide:chevron-up'
  if (currentSort === `-${column}`) return 'lucide:chevron-down'
  return 'lucide:chevrons-up-down'
}

const handlePagination = url => {
  if (url) {
    router.get(url, {}, { preserveState: true, preserveScroll: true })
  }
}

const deleteDocument = document => {
  if (confirm(`Are you sure you want to delete "${document.title}"?`)) {
    router.delete(route('documents.destroy', document.id))
  }
}

// Documents mix uploads waiting to be imported with files generated by report
// workflows, so the list is split the same way. Imports are the default and act
// as the catch-all; only generated files carry a `report_type` tag.
const documentKind = ref(
  new URLSearchParams(window.location.search).get('filter[kind]') === 'export' ? 'export' : 'import'
)

const handleKindChange = value => {
  documentKind.value = value

  const params = { ...page.props.ziggy?.query, 'filter[kind]': value }
  delete params.page

  router.get(route('documents.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const uploaderColumnLabel = computed(() =>
  documentKind.value === 'export' ? 'Exported by' : 'Imported by'
)

const columns = [
  {
    accessorKey: 'title',
    header: () =>
      h(
        Button,
        { variant: 'ghost', onClick: () => handleSort('title'), class: 'p-0 h-auto font-medium' },
        () => ['Title', h(Icon, { icon: getSortIcon('title'), class: 'ml-2 h-4 w-4' })]
      ),
    cell: ({ row }) => {
      const document = row.original
      return h('div', { class: 'flex max-w-[14rem] flex-col', title: document.title }, [
        h('span', { class: 'truncate font-medium text-gray-900' }, document.title),
        document.description &&
          h(
            'span',
            { class: 'truncate text-xs text-gray-500', title: document.description },
            document.description
          ),
      ])
    },
  },
  {
    id: 'originating_center',
    header: 'Center',
    cell: ({ row }) => {
      const center = row.original.originating_center
      return center
        ? h('span', { class: 'text-sm text-gray-700', title: center.code }, center.name)
        : h('span', { class: 'text-sm text-slate-400' }, 'Unresolved')
    },
  },
  {
    accessorKey: 'file.name',
    header: 'File',
    cell: ({ row }) => {
      const document = row.original
      if (document.file) {
        return h(
          'a',
          {
            href: document.file.path,
            class:
              'inline-flex max-w-[10rem] items-center gap-1 text-xs text-blue-600 hover:underline',
            target: '_blank',
            title: document.file.name,
          },
          [
            h(Icon, { icon: 'lucide:file', class: 'h-3 w-3 shrink-0' }),
            h('span', { class: 'truncate' }, document.file.name),
          ]
        )
      }
      return h('span', { class: 'text-xs text-gray-400' }, 'No file')
    },
  },
  {
    accessorKey: 'file.uploaded_by.name',
    header: () => uploaderColumnLabel.value,
    cell: ({ row }) => {
      const document = row.original
      const uploader = document.file?.uploaded_by?.name

      if (!uploader) {
        return h('span', { class: 'text-sm text-slate-400' }, '—')
      }

      return h(
        'span',
        { class: 'block max-w-[9rem] truncate text-sm text-gray-700', title: uploader },
        uploader
      )
    },
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const status = row.getValue('status')?.value || row.getValue('status') || 'draft'
      return h(
        Badge,
        { class: `${documentStatusBadgeClass(status)} border` },
        () => documentStatusLabels[status] || status
      )
    },
  },
  {
    accessorKey: 'center_reviewed_by.name',
    header: 'Center Reviewer',
    cell: ({ row }) => {
      const document = row.original
      const reviewer = document.center_reviewed_by?.name
      const reviewedAt = document.center_reviewed_at

      if (!reviewer) {
        return h('span', { class: 'text-slate-400' }, () => '—')
      }

      // Children must be passed as a plain array, not a function returning an
      // array - the latter renders an empty div here (same shape as Title).
      return h('div', { class: 'flex max-w-[10rem] flex-col', title: reviewer }, [
        h('span', { class: 'truncate font-medium text-gray-900' }, reviewer),
        h(
          'span',
          { class: 'truncate text-xs text-gray-500', title: formatDateTime(reviewedAt) },
          formatDateTime(reviewedAt)
        ),
      ])
    },
  },
  {
    accessorKey: 'agency_reviewed_by.name',
    header: 'Agency Reviewer',
    cell: ({ row }) => {
      const document = row.original
      const reviewer = document.agency_reviewed_by?.name

      if (!reviewer) return h('span', { class: 'text-slate-400' }, '—')

      return h('div', { class: 'flex max-w-[10rem] flex-col', title: reviewer }, [
        h('span', { class: 'truncate font-medium text-gray-900' }, reviewer),
        h(
          'span',
          { class: 'truncate text-xs text-gray-500' },
          formatDateTime(document.agency_reviewed_at)
        ),
      ])
    },
  },
  {
    id: 'detail',
    enableHiding: false,
    header: 'Detail',
    // The list carries a title, a status and a filename; what the file actually
    // is - which kind of document, what it is attached to, how it was tagged -
    // needs the document and the records its tags point at, so it is fetched for
    // the one row being looked at rather than for the whole page.
    cell: ({ row }) =>
      h(
        Button,
        {
          variant: 'ghost',
          size: 'xs',
          class:
            'gap-1 text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500',
          title: 'View document details',
          onClick: () => openDetail(row.original),
        },
        { default: () => [h(Icon, { icon: 'lucide:eye', class: 'h-3.5 w-3.5' }), 'Details'] }
      ),
  },
  {
    id: 'review',
    enableHiding: false,
    header: 'Review',
    cell: ({ row }) => {
      const document = row.original
      const status = document.status?.value || document.status || 'draft'
      // Each stage is actionable only for its matching server-authorized role.
      const canAct =
        (status === 'pending_center_review' && canReviewCenter.value) ||
        (status === 'pending_agency_review' && canReviewAgency.value)
      if (canAct) {
        return h('div', { class: 'flex items-center gap-1.5' }, [
          h(
            Button,
            {
              variant: 'default',
              size: 'xs',
              class:
                'gap-1 border border-emerald-600 bg-emerald-600 font-medium text-white shadow-xs hover:border-emerald-700 hover:bg-emerald-700 hover:text-white focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-1',
              title:
                status === 'pending_center_review'
                  ? 'Approve and forward to agency review'
                  : 'Approve and start processing',
              onClick: () => openReview('approve', document),
            },
            { default: () => [h(Icon, { icon: 'lucide:check', class: 'h-3.5 w-3.5' }), 'Approve'] }
          ),
          h(
            Button,
            {
              variant: 'default',
              size: 'xs',
              class:
                'gap-1 border border-red-600 bg-red-600 font-medium text-white shadow-xs hover:border-red-700 hover:bg-red-700 hover:text-white focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-1',
              title: 'Reject this document',
              onClick: () => openReview('reject', document),
            },
            { default: () => [h(Icon, { icon: 'lucide:x', class: 'h-3.5 w-3.5' }), 'Reject'] }
          ),
        ])
      }

      // Rejected: the decision is already made, so only surface why.
      if (['rejected', 'rejected_by_center', 'rejected_by_agency'].includes(status)) {
        return h(
          Button,
          {
            variant: 'ghost',
            size: 'xs',
            class:
              'gap-1 text-red-700 hover:bg-red-50 hover:text-red-800 focus-visible:ring-2 focus-visible:ring-red-500',
            title: document.rejection_reason || 'No reason recorded',
            onClick: () => openReason(document),
          },
          { default: () => [h(Icon, { icon: 'lucide:eye', class: 'h-3.5 w-3.5' }), 'View Reason'] }
        )
      }

      return h(
        'span',
        { class: 'text-sm text-slate-400' },
        status === 'pending_review' ? 'Legacy review needs classification' : '—'
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
          class: 'p-0 h-auto font-medium',
        },
        () => ['Created At', h(Icon, { icon: getSortIcon('created_at'), class: 'ml-2 h-4 w-4' })]
      ),
    cell: ({ row }) => {
      const createdAt = row.getValue('created_at')

      return h('div', { class: 'flex flex-col whitespace-nowrap' }, [
        h('span', { class: 'text-sm text-slate-500' }, formatDate(createdAt)),
        h(
          'span',
          { class: 'text-xs text-slate-400' },
          new Date(createdAt).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
        ),
      ])
    },
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const document = row.original
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
              { align: 'end', class: 'w-56 bg-white cursor-pointer' },
              {
                default: () => {
                  const items = []
                  const status = document.status?.value || document.status || 'draft'

                  // Status-based review actions.
                  if (isEditableByUploader(status) && canEdit.value) {
                    items.push(
                      h(
                        DropdownMenuItem,
                        {
                          onClick: () =>
                            router.post(
                              route('documents.submit', document.id),
                              {},
                              {
                                preserveScroll: true,
                              }
                            ),
                          class: 'cursor-pointer hover:bg-gray-100',
                        },
                        {
                          default: () => [
                            h(Icon, { icon: 'lucide:send', class: 'mr-2 h-4 w-4' }),
                            'Submit for Review',
                          ],
                        }
                      )
                    )
                  }

                  // Approve / Reject / Retry live in the Review column so the
                  // primary workflow decision has a single, obvious home.
                  if (document.file) {
                    items.push(
                      h(
                        DropdownMenuItem,
                        {
                          onClick: () => window.open(document.file.path, '_blank'),
                          class: 'cursor-pointer hover:bg-gray-100',
                        },
                        {
                          default: () => [
                            h(Icon, { icon: 'lucide:download', class: 'mr-2 h-4 w-4' }),
                            'Download File',
                          ],
                        }
                      )
                    )
                  }

                  // Deleting an uploaded document stays an agency-level job.
                  if (canDelete.value) {
                    items.push(h(DropdownMenuSeparator))
                    items.push(
                      h(
                        DropdownMenuItem,
                        {
                          onClick: () => deleteDocument(document),
                          class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                        },
                        {
                          default: () => [
                            h(Icon, { icon: 'lucide:trash-2', class: 'mr-2 h-4 w-4' }),
                            'Delete Document',
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

// Exported documents are generated by report workflows and never enter the
// review queue, so the reviewer columns would be permanently empty on that tab.
// Generated exports never enter either approval stage.
const columnVisibility = computed(() =>
  documentKind.value === 'export'
    ? {
        'center_reviewed_by.name': false,
        'agency_reviewed_by.name': false,
        review: false,
      }
    : {}
)
const rowSelection = ref({})

const table = useVueTable({
  get data() {
    return props.documents.data || []
  },
  columns,
  getCoreRowModel: getCoreRowModel(),
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
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Documents</h1>
        <p class="text-sm text-slate-600 sm:text-base">Manage and view all document files.</p>
      </div>
      <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-4">
        <!-- Import / Export -->
        <Tabs v-model="documentKind" class="bordered w-fit" @update:model-value="handleKindChange">
          <TabsList class="h-8 sm:h-9">
            <TabsTrigger value="import" class="px-2 text-xs sm:px-3 sm:text-sm">Import</TabsTrigger>
            <TabsTrigger value="export" class="px-2 text-xs sm:px-3 sm:text-sm">Export</TabsTrigger>
          </TabsList>
        </Tabs>
        <!-- Filter by Status -->
        <DropdownMenu class="w-full sm:w-auto">
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
          <DropdownMenuContent
            align="end"
            class="max-h-[50vh] w-72 overflow-y-auto bg-white sm:max-h-[70vh] sm:w-80"
          >
            <DropdownMenuLabel>Filters</DropdownMenuLabel>
            <DropdownMenuSeparator class="border-b border-slate-100" />

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
            <div v-if="openFilterSection === 'status'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="status in statusOptions"
                :key="status.value"
                class="cursor-pointer pl-8 capitalize"
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

        <!-- Add Document Button -->
        <Link
          v-if="canUpload"
          :href="route('documents.create')"
          as="button"
          class="inline-flex w-full cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          <span class="flex-1 text-left">Add Document</span>
        </Link>
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
                :data-state="row.getIsSelected() && 'selected'"
                class="border-none transition-colors hover:bg-slate-50"
              >
                <TableCell
                  v-for="cell in row.getVisibleCells()"
                  :key="cell.id"
                  class="border-none align-top"
                  @click="handleCellClick($event, cell.column.id)"
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
                <div class="flex flex-col items-center justify-center space-y-3">
                  <Icon icon="lucide:file-text" class="h-12 w-12 text-slate-300" />
                  <div class="text-center">
                    <h3 class="mb-1 text-lg font-medium text-slate-900">No documents found</h3>
                    <p class="text-slate-500">Get started by adding your first document.</p>
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
          Showing {{ documents.from || 0 }}–{{ documents.to || 0 }} of
          {{ documents.total || 0 }}
        </span>
      </div>
      <div class="flex items-center space-x-2">
        <Button
          variant="ghost"
          size="xs"
          :disabled="!documents.prev_page_url"
          @click="handlePagination(documents.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          Prev
        </Button>
        <Button
          variant="ghost"
          size="xs"
          :disabled="!documents.next_page_url"
          @click="handlePagination(documents.next_page_url)"
        >
          Next
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>

    <DocumentReviewDialog
      v-model:open="reviewDialog.open"
      :mode="reviewDialog.mode"
      :document-id="reviewDialog.document?.id"
      :document-title="reviewDialog.document?.title"
      :review-stage="reviewDialog.stage"
    />

    <DocumentRejectionReasonDialog
      v-model:open="reasonDialog.open"
      :document-title="reasonDialog.document?.title"
      :reason="reasonDialog.document?.rejection_reason"
      :reviewer="reasonDialog.document?.reviewed_by?.name"
      :reviewed-at="reasonDialog.document?.reviewed_at"
    />

    <DocumentDetailDialog
      v-model:open="detailDialog.open"
      :document-id="detailDialog.document?.id"
      :document-title="detailDialog.document?.title"
    />
  </div>
</template>
