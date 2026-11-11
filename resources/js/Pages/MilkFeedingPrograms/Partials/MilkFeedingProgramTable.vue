<script setup>
import { displayCenterLabel } from '@/lib/center'
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { Icon } from '@iconify/vue'
import { h, ref, computed, reactive, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { valueUpdater, formatDate, label } from '@/lib/utils'
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
  milkFeedingPrograms: {
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
const { hasPermission } = useAuth()

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

  router.get(route('milk-feeding-programs.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const searchParams = new URLSearchParams(window.location.search)
const currentFilters = reactive({
  [`filter[funded_by]`]: searchParams.get('filter[funded_by]'),
  [`filter[region]`]: searchParams.get('filter[region]'),
  [`filter[province]`]: searchParams.get('filter[province]'),
  [`filter[division]`]: searchParams.get('filter[division]'),
  [`filter[municipality]`]: searchParams.get('filter[municipality]'),
  [`filter[milk_type]`]: searchParams.get('filter[milk_type]'),
  [`filter[milk_subcategory]`]: searchParams.get('filter[milk_subcategory]'),
  [`filter[partner_id]`]: searchParams.get('filter[partner_id]'),
  [`filter[mode_of_procurement]`]: searchParams.get('filter[mode_of_procurement]'),
  [`filter[center_id]`]: searchParams.get('filter[center_id]'),
})

const globalSearch = ref(searchParams.get('search') || '')

const selectedMilkType = computed(() => currentFilters[`filter[milk_type]`])
const selectedFundedBy = computed(() => currentFilters[`filter[funded_by]`])
const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('md')

const availableSubcategories = computed(() => {
  return props.enums?.milkTypeSubcategories || {}
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

const handleGlobalSearch = debounce(value => {
  const params = { ...page.props.ziggy?.query }

  if (value) {
    params.search = value
  } else {
    delete params.search
  }

  router.get(route('milk-feeding-programs.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 300)

const handleFilter = debounce((key, value) => {
  const params = { ...page.props.ziggy?.query }

  if (value) {
    params[`filter[${key}]`] = value
    currentFilters[`filter[${key}]`] = value
  } else {
    delete params[`filter[${key}]`]
    delete currentFilters[`filter[${key}]`]
  }

  router.get(route('milk-feeding-programs.index'), params, {
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

const deleteMilkFeedingProgram = program => {
  if (confirm(`Are you sure you want to delete "${program.name}"?`)) {
    router.delete(route('milk-feeding-programs.destroy', program.id))
  }
}

const formatCurrency = amount => {
  if (!amount && amount !== 0) return '₱0.00'
  return `₱${Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const formatNumber = value => {
  if (!value && value !== 0) return '0'
  return Number(value).toLocaleString('en-US')
}

const formatDecimal = value => {
  if (!value && value !== 0) return '0.00'
  return Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const columns = computed(() => {
  const fundedBy = selectedFundedBy.value
  const showSdo = !fundedBy || fundedBy === 'depEd'
  const showMunicipality = !fundedBy || fundedBy === 'dswd'

  return [
    {
      accessorKey: 'year',
      header: createSortableHeader('Year', 'year'),
      cell: ({ row }) => {
        return h('span', { class: 'text-sm text-slate-900' }, row.original.year || 'N/A')
      },
    },
    {
      accessorKey: 'funded_by',
      header: createSortableHeader('Funded By', 'funded_by'),
      cell: ({ row }) => {
        const fundedBy = row.original.funded_by
        if (!fundedBy) return h('span', { class: 'text-sm text-slate-500' }, 'N/A')
        return h(
          Badge,
          { class: 'bg-blue-100 text-blue-800 hover:bg-blue-200' },
          () => fundedBy?.toUpperCase() || 'N/A'
        )
      },
    },
    {
      accessorKey: 'address.region',
      header: () => h('span', { class: 'font-medium' }, 'Region'),
      cell: ({ row }) => {
        const region = row.original.address?.region
        return h('span', { class: 'text-sm text-slate-900' }, region || 'N/A')
      },
    },
    {
      accessorKey: 'center.name',
      header: createSortableHeader('Center', 'center_id'),
      cell: ({ row }) => {
        const center = row.original.center
        return h(
          'span',
          { class: 'text-sm font-medium text-slate-900' },
          displayCenterLabel(center.code)
        )
      },
    },
    {
      accessorKey: 'address.province',
      header: () => h('span', { class: 'font-medium' }, 'Province'),
      cell: ({ row }) => {
        const province = row.original.address?.province
        return h('span', { class: 'text-sm text-slate-900' }, province || 'N/A')
      },
    },
    ...(showSdo
      ? [
          {
            accessorKey: 'division',
            header: () => h('span', { class: 'font-medium' }, 'SDO'),
            cell: ({ row }) => {
              return h('span', { class: 'text-sm text-slate-900' }, row.original.division || 'N/A')
            },
          },
        ]
      : []),
    ...(showMunicipality
      ? [
          {
            accessorKey: 'address.city',
            header: () => h('span', { class: 'font-medium' }, 'Municipality'),
            cell: ({ row }) => {
              return h(
                'span',
                { class: 'text-sm text-slate-900' },
                row.original.address?.city || 'N/A'
              )
            },
          },
        ]
      : []),
    {
      accessorKey: 'address.district',
      header: () => h('span', { class: 'font-medium' }, 'District'),
      cell: ({ row }) => {
        const district = row.original.address?.district
        const districtLabel = label(props.enums?.district || {}, district) || district
        return h('span', { class: 'text-sm text-slate-900' }, districtLabel || 'N/A')
      },
    },
    {
      accessorKey: 'milk_packs',
      header: createSortableHeader('Milk Packs', 'milk_packs'),
      cell: ({ row }) => {
        return h('span', { class: 'text-sm text-slate-900' }, formatNumber(row.original.milk_packs))
      },
    },
    {
      accessorKey: 'total_volume_requirements',
      header: createSortableHeader('Total Volume Requirements', 'total_volume_requirements'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900' },
          `${formatDecimal(row.original.total_volume_requirements)} L`
        )
      },
    },
    {
      accessorKey: 'raw_milk_used',
      header: createSortableHeader('Raw Milk Used (L)', 'raw_milk_used'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900' },
          `${formatDecimal(row.original.raw_milk_used)} L`
        )
      },
    },
    {
      accessorKey: 'whole_milk',
      header: createSortableHeader('Whole Milk (kg)', 'whole_milk'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900' },
          `${formatDecimal(row.original.whole_milk)} kg`
        )
      },
    },
    {
      accessorKey: 'skimmed_milk',
      header: createSortableHeader('Skimmed Milk (kg)', 'skimmed_milk'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900' },
          `${formatDecimal(row.original.skimmed_milk)} kg`
        )
      },
    },
    {
      accessorKey: 'sugar',
      header: createSortableHeader('Sugar', 'sugar'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900' },
          `${formatDecimal(row.original.sugar)} kg`
        )
      },
    },
    {
      accessorKey: 'feeding_days',
      header: createSortableHeader('Feeding Days', 'feeding_days'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900' },
          formatNumber(row.original.feeding_days)
        )
      },
    },
    {
      accessorKey: 'number_of_beneficiaries',
      header: createSortableHeader('Beneficiaries', 'number_of_beneficiaries'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900' },
          formatNumber(row.original.number_of_beneficiaries)
        )
      },
    },
    {
      accessorKey: 'milk_type',
      header: createSortableHeader('Milk Product', 'milk_type'),
      cell: ({ row }) => {
        const milkType = row.original.milk_type
        if (!milkType) return h('span', { class: 'text-sm text-slate-500' }, 'N/A')

        const milkLabel = label(props.enums?.milkType || {}, milkType) || milkType
        const subcategories = props.enums?.milkTypeSubcategories?.[milkType] || []
        const subcategory = row.original.milk_subcategory

        return h('div', { class: 'flex flex-col space-y-1' }, [
          h(Badge, { class: 'bg-purple-100 text-purple-800 hover:bg-purple-200' }, () => milkLabel),
          subcategory && subcategories[subcategory]
            ? h('span', { class: 'text-xs text-slate-600' }, `(${subcategories[subcategory]})`)
            : null,
        ])
      },
    },
    {
      accessorKey: 'price_per_milk_pack',
      header: createSortableHeader('Price', 'price_per_milk_pack'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900 font-medium' },
          formatCurrency(row.original.price_per_milk_pack)
        )
      },
    },
    {
      accessorKey: 'partner.name',
      header: createSortableHeader('Supplier', 'partner_id'),
      cell: ({ row }) => {
        const partner = row.original.partner
        return h('div', { class: 'flex flex-col space-y-1' }, [
          h('p', { class: 'text-sm font-medium text-slate-900' }, partner?.name || 'N/A'),
          h('p', { class: 'text-xs text-slate-500' }, partner?.code || ''),
        ])
      },
    },
    {
      accessorKey: 'gross_income',
      header: createSortableHeader('Milk Cost', 'gross_income'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900 font-medium' },
          formatCurrency(row.original.gross_income)
        )
      },
    },
    {
      accessorKey: 'service_fee',
      header: createSortableHeader('Service Fee/ Admin Cost', 'service_fee'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900 font-medium' },
          formatCurrency(row.original.service_fee)
        )
      },
    },
    {
      accessorKey: 'total_contract_amount',
      header: createSortableHeader('Total Funds Transferred', 'total_contract_amount'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-900 font-bold' },
          formatCurrency(row.original.total_contract_amount)
        )
      },
    },
    {
      accessorKey: 'mode_of_procurement',
      header: createSortableHeader('Mode of Procurement', 'mode_of_procurement'),
      cell: ({ row }) => {
        const mode = row.original.mode_of_procurement
        if (!mode) return h('span', { class: 'text-sm text-slate-500' }, 'N/A')
        return h(
          'span',
          { class: 'text-sm text-slate-900' },
          label(props.enums?.modeOfProcurement || {}, mode) || mode
        )
      },
    },
    {
      accessorKey: 'moa_signing_date',
      header: createSortableHeader('Date of MOA Signing', 'moa_signing_date'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-500' },
          formatDate(row.original.moa_signing_date) || 'N/A'
        )
      },
    },
    {
      accessorKey: 'fund_transfer_date',
      header: createSortableHeader('Date of Fund Transfer', 'fund_transfer_date'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-500' },
          formatDate(row.original.fund_transfer_date) || 'N/A'
        )
      },
    },
    {
      accessorKey: 'date_started',
      header: createSortableHeader('Start of Delivery', 'date_started'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-500' },
          formatDate(row.original.date_started) || 'N/A'
        )
      },
    },
    {
      accessorKey: 'date_completed',
      header: createSortableHeader('End of Delivery', 'date_completed'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-500' },
          formatDate(row.original.date_completed) || 'N/A'
        )
      },
    },
    {
      accessorKey: 'liquidation_date',
      header: createSortableHeader('Date of Liquidation', 'liquidation_date'),
      cell: ({ row }) => {
        return h(
          'span',
          { class: 'text-sm text-slate-500' },
          formatDate(row.original.liquidation_date) || 'N/A'
        )
      },
    },
    {
      id: 'actions',
      enableHiding: false,
      cell: ({ row }) => {
        const program = row.original

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
                            router.visit(route('milk-feeding-programs.show', program.id)),
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
                              router.visit(route('milk-feeding-programs.edit', program.id)),
                            class: 'cursor-pointer hover:bg-gray-100',
                          },
                          {
                            default: () => [
                              h(Icon, {
                                icon: 'lucide:pencil',
                                class: 'mr-2 h-4 w-4',
                              }),
                              'Edit Program',
                            ],
                          }
                        ),
                        h(DropdownMenuSeparator),
                        h(
                          DropdownMenuItem,
                          {
                            onClick: () => deleteMilkFeedingProgram(program),
                            class: 'cursor-pointer text-red-600 focus:text-red-600 hover:bg-gray-100',
                          },
                          {
                            default: () => [
                              h(Icon, {
                                icon: 'lucide:trash-2',
                                class: 'mr-2 h-4 w-4',
                              }),
                              'Delete Program',
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

// Table setup for display and selection
const columnVisibility = ref({})
const rowSelection = ref({})

const applyResponsiveVisibility = () => {
  if (!isMobile.value) {
    columnVisibility.value = {}
    return
  }

  const fundedBy = selectedFundedBy.value
  const showSdo = !fundedBy || fundedBy === 'depEd'
  const showMunicipality = !fundedBy || fundedBy === 'dswd'

  const visible = new Set([
    'year',
    'funded_by',
    'center.name',
    'address.region',
    'address.province',
    ...(showSdo ? ['division'] : []),
    ...(showMunicipality ? ['address.city'] : []),
    'milk_packs',
    'number_of_beneficiaries',
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

watch([isMobile, selectedFundedBy], applyResponsiveVisibility, { immediate: true })

const table = useVueTable({
  get data() {
    return props.milkFeedingPrograms?.data || []
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
  <div class="space-y-6 rounded-lg bg-white px-6 py-12 text-black shadow-sm">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Milk Feeding Programs</h1>
        <p class="text-slate-600">Manage and view milk feeding program records and data.</p>
      </div>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:space-x-2">
        <!-- Global Search -->
        <div class="relative">
          <Icon
            icon="lucide:search"
            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
          />
          <Input
            v-model="globalSearch"
            placeholder="Search by Center, Province, Supplier, Mode of Procurement..."
            class="w-full pl-10 sm:w-50"
            @input="handleGlobalSearch(globalSearch)"
          />
        </div>
        <!-- Filter -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" class="ml-auto w-full sm:w-auto">
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
            <!-- Funded By -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('funded_by')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Funded By</span>
                <Icon
                  :icon="
                    openFilterSection === 'funded_by' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'funded_by'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="(labelText, value) in enums?.fundedBy || {}"
                :key="value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[funded_by]`] === value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'funded_by',
                    currentFilters[`filter[funded_by]`] === value ? null : value
                  )
                "
              >
                {{ labelText }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />

            <!-- Center -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('center_id')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Center</span>
                <Icon
                  :icon="
                    openFilterSection === 'center_id' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'center_id'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="(labelText, value) in enums?.center || {}"
                :key="value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[center_id]`] === value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'center_id',
                    currentFilters[`filter[center_id]`] === value ? null : value
                  )
                "
              >
                {{ labelText }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />

            <!-- Region -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('region')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Region</span>
                <Icon
                  :icon="
                    openFilterSection === 'region' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'region'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="(labelText, value) in enums?.region || {}"
                :key="value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[region]`] === value,
                }"
                @select.prevent
                @click="
                  handleFilter('region', currentFilters[`filter[region]`] === value ? null : value)
                "
              >
                {{ labelText }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />

            <!-- Province -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('province')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Province</span>
                <Icon
                  :icon="
                    openFilterSection === 'province' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'province'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="(labelText, value) in enums?.province || {}"
                :key="value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[province]`] === value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'province',
                    currentFilters[`filter[province]`] === value ? null : value
                  )
                "
              >
                {{ labelText }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />

            <!-- SDO (DepEd) -->
            <template
              v-if="
                currentFilters[`filter[funded_by]`] === 'depEd' ||
                !currentFilters[`filter[funded_by]`]
              "
            >
              <DropdownMenuItem
                class="cursor-pointer"
                @select.prevent
                @click="toggleFilterSection('division')"
              >
                <div class="flex w-full items-center justify-between">
                  <span>SDO</span>
                  <Icon
                    :icon="
                      openFilterSection === 'division' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                    "
                    class="h-4 w-4 text-slate-500"
                  />
                </div>
              </DropdownMenuItem>
              <div v-if="openFilterSection === 'division'" class="max-h-56 overflow-y-auto">
                <DropdownMenuItem
                  v-for="(labelText, value) in enums?.division || {}"
                  :key="value"
                  class="cursor-pointer pl-8 capitalize"
                  :class="{
                    'bg-indigo-50 text-indigo-700': currentFilters[`filter[division]`] === value,
                  }"
                  @select.prevent
                  @click="
                    handleFilter(
                      'division',
                      currentFilters[`filter[division]`] === value ? null : value
                    )
                  "
                >
                  {{ labelText }}
                </DropdownMenuItem>
              </div>
              <DropdownMenuSeparator />
            </template>

            <!-- Municipality/City (DSWD) -->
            <template
              v-if="
                currentFilters[`filter[funded_by]`] === 'dswd' ||
                !currentFilters[`filter[funded_by]`]
              "
            >
              <DropdownMenuItem
                class="cursor-pointer"
                @select.prevent
                @click="toggleFilterSection('municipality')"
              >
                <div class="flex w-full items-center justify-between">
                  <span>Municipality</span>
                  <Icon
                    :icon="
                      openFilterSection === 'municipality'
                        ? 'lucide:chevron-up'
                        : 'lucide:chevron-down'
                    "
                    class="h-4 w-4 text-slate-500"
                  />
                </div>
              </DropdownMenuItem>
              <div v-if="openFilterSection === 'municipality'" class="max-h-56 overflow-y-auto">
                <DropdownMenuItem
                  v-for="(labelText, value) in enums?.municipality || {}"
                  :key="value"
                  class="cursor-pointer pl-8 capitalize"
                  :class="{
                    'bg-indigo-50 text-indigo-700':
                      currentFilters[`filter[municipality]`] === value,
                  }"
                  @select.prevent
                  @click="
                    handleFilter(
                      'municipality',
                      currentFilters[`filter[municipality]`] === value ? null : value
                    )
                  "
                >
                  {{ labelText }}
                </DropdownMenuItem>
              </div>
              <DropdownMenuSeparator />
            </template>

            <!-- Milk Type -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('milk_type')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Milk Type</span>
                <Icon
                  :icon="
                    openFilterSection === 'milk_type' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'milk_type'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="(labelText, value) in enums?.milkType || {}"
                :key="value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[milk_type]`] === value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'milk_type',
                    currentFilters[`filter[milk_type]`] === value ? null : value
                  )
                "
              >
                {{ labelText }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />

            <!-- Subcategory -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('milk_subcategory')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Subcategory</span>
                <Icon
                  :icon="
                    openFilterSection === 'milk_subcategory'
                      ? 'lucide:chevron-up'
                      : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'milk_subcategory'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="(labelText, value) in availableSubcategories"
                :key="value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700':
                    currentFilters[`filter[milk_subcategory]`] === value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'milk_subcategory',
                    currentFilters[`filter[milk_subcategory]`] === value ? null : value
                  )
                "
              >
                {{ labelText }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />

            <!-- Supplier -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('partner_id')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Supplier</span>
                <Icon
                  :icon="
                    openFilterSection === 'partner_id' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div v-if="openFilterSection === 'partner_id'" class="max-h-56 overflow-y-auto">
              <DropdownMenuItem
                v-for="(labelText, value) in enums?.partner || {}"
                :key="value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700': currentFilters[`filter[partner_id]`] === value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'partner_id',
                    currentFilters[`filter[partner_id]`] === value ? null : value
                  )
                "
              >
                {{ labelText }}
              </DropdownMenuItem>
            </div>
            <DropdownMenuSeparator />

            <!-- Mode of Procurement -->
            <DropdownMenuItem
              class="cursor-pointer"
              @select.prevent
              @click="toggleFilterSection('mode_of_procurement')"
            >
              <div class="flex w-full items-center justify-between">
                <span>Mode of Procurement</span>
                <Icon
                  :icon="
                    openFilterSection === 'mode_of_procurement'
                      ? 'lucide:chevron-up'
                      : 'lucide:chevron-down'
                  "
                  class="h-4 w-4 text-slate-500"
                />
              </div>
            </DropdownMenuItem>
            <div
              v-if="openFilterSection === 'mode_of_procurement'"
              class="max-h-56 overflow-y-auto"
            >
              <DropdownMenuItem
                v-for="(labelText, value) in enums?.modeOfProcurement || {}"
                :key="value"
                class="cursor-pointer pl-8 capitalize"
                :class="{
                  'bg-indigo-50 text-indigo-700':
                    currentFilters[`filter[mode_of_procurement]`] === value,
                }"
                @select.prevent
                @click="
                  handleFilter(
                    'mode_of_procurement',
                    currentFilters[`filter[mode_of_procurement]`] === value ? null : value
                  )
                "
              >
                {{ labelText }}
              </DropdownMenuItem>
            </div>
          </DropdownMenuContent>
        </DropdownMenu>
        <Link
          v-if="hasPermission('enter-data')"
          :href="route('milk-feeding-programs.create')"
          as="button"
          class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        >
          <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
          New Program
        </Link>
      </div>
    </div>

    <!-- Table -->
    <div
      class="mx-auto w-full max-w-full overflow-hidden overflow-x-auto rounded-lg border border-slate-200 bg-white"
    >
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
              :data-state="row.getIsSelected() && 'selected'"
              class="cursor-pointer border-none transition-colors hover:bg-slate-50"
              @click="router.visit(route('milk-feeding-programs.show', row.original.id))"
            >
              <TableCell
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                class="align-center border-none whitespace-nowrap"
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
                <Icon icon="lucide:cup-soda" class="h-12 w-12 text-slate-300" />
                <div class="text-center">
                  <h3 class="mb-1 text-lg font-medium text-slate-900">
                    No milk feeding programs found
                  </h3>
                  <p class="mb-4 text-slate-500">
                    Get started by adding your first milk feeding program.
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
        <span>
          Showing {{ milkFeedingPrograms?.from || 0 }}–{{ milkFeedingPrograms?.to || 0 }} of
          {{ milkFeedingPrograms?.total || 0 }}
        </span>
      </div>
      <div class="flex items-center space-x-2">
        <Button
          variant="ghost"
          size="xs"
          :disabled="!milkFeedingPrograms?.prev_page_url"
          @click="handlePagination(milkFeedingPrograms?.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          Prev
        </Button>
        <Button
          variant="ghost"
          size="xs"
          :disabled="!milkFeedingPrograms?.next_page_url"
          @click="handlePagination(milkFeedingPrograms?.next_page_url)"
        >
          Next
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
