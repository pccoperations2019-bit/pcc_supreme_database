<script setup>
import { usePage, router, Link } from '@inertiajs/vue3'
import { reactive, watch, defineEmits, computed } from 'vue'
import DateRange from './DateRange.vue'
import { formatCustomDateToISO } from '@/lib/utils'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import SearchableSelect from './SearchableSelect.vue'
import PsgcAddressFilter from './PsgcAddressFilter.vue'
import { useAuth } from '@/composable/useAuth'
import { useFilterSync } from '@/composable/useFilterSync'

const props = defineProps({
  filters: { type: Object, required: false },
  elements: { type: Array, required: false, default: () => [] },
})

const page = usePage()
const { isCenterAdmin, user } = useAuth()

const filters = computed(() => {
  return props.filters || page.props.filters || {}
})

// Check if current user is a center admin and get their center ID
const userCenterId = computed(() => {
  return user.value?.center_id || null
})
const elements = computed(() => {
  return props.elements || page.props.elements || []
})

const flattedCenters = computed(() => {
  const clustered = filters.value.clusteredCenters || {}
  const flatList = []
  for (const clusterKey in clustered) {
    const centers = clustered[clusterKey]
    centers.forEach(center => {
      flatList.push(center)
    })
  }
  return flatList
})

const isCentersRoute = computed(() => {
  const pathname = window.location.pathname || ''
  return pathname.startsWith('/centers')
})

const ELEMENTS = {
  clusteredCenters: 'clusteredCenters',
  dateRange: 'dateRange',
  yearRange: 'yearRange',
  selectCenters: 'selectCenters',
  years: 'years',
  months: 'months',
  programs: 'programs',
  partners: 'partners',
  selectPartners: 'selectPartners',
  selectFarmers: 'selectFarmers',
  farmers: 'farmers',
  milkingTimes: 'milkingTimes',
  weeks: 'weeks',
  status: 'status',
  fundedBy: 'fundedBy',
  region: 'region',
  milkType: 'milkType',
  modeOfProcurement: 'modeOfProcurement',
  suppliers: 'suppliers',
  provinces: 'provinces',
  cities: 'cities',
  divisions: 'divisions',
  district: 'district',
  masterlistType: 'masterlistType',
  projects: 'projects',
  templateFile: 'templateFile',
  product: 'product',
  reportType: 'reportType',
  reportTemplate: 'reportTemplate',
  listAndFigures: 'listAndFigures',
  psgcAddress: 'psgcAddress',
  partnerType: 'partnerType',
  businessType: 'partnerType',
  accreditation: 'accreditation',
}

const { pushUrl, handleMulti, handleSingle } = useFilterSync()

// Centralized push — replaces 30-line copy-pasted `updateFilters`. All filter components
// (template, years, PSGC) funnel through here so ?filter[region]=all and ?filter[year]=all
// share the same single-click contract.
const updateFilters = (filterKey, filterValue) => {
  isUpdatingFilters = true
  pushUrl(filterKey, filterValue)
  setTimeout(() => {
    isUpdatingFilters = false
  }, 0)
}

const handleDateRangeUpdate = value => {
  if (value && value.start && value.end) {
    const startDate = formatCustomDateToISO(value.start)
    const endDate = formatCustomDateToISO(value.end)

    if (startDate && endDate) {
      const dateRangeString = `${startDate},${endDate}`
      filter.dateRange = dateRangeString
      updateFilters('dateRange', dateRangeString)
    }
  }
}

// Single-select toggle — delegated to shared composable so template/year and PSGC district share toggle semantics
const handleSingleSelect = (filterKey, value) => {
  handleSingle(filter, filterKey, value)
}

// Multi-select All handler — PSGC-style: single click → ?filter[year]=all (mirrors PsgcAddressFilter.onRegionChange)
// Centralizes the `all`/`0` sentinel logic previously duplicated in 12 watchers.
const handleMultiSelect = (filterKey, value) => {
  handleMulti(filter, filterKey, value)
}

// Get template variants for the currently selected template
// Supports both BDP (object) and directory (object from reports.directory.templates) shapes.
// Normalizes array-shaped variants (if any) to object keyed by value for v-for compatibility.
const getTemplateVariants = () => {
  const isDirectory = filters.value?.report_type === 'directory'
  const defaultKey = isDirectory ? 'centers' : 'date-annual'
  const templateKey = filter.template || defaultKey
  const selectedTemplate = filters.value?.report_templates?.find(t => t.value === templateKey)
  const raw = selectedTemplate?.variants || {}
  if (Array.isArray(raw)) {
    // legacy array shape: [{value,label}] -> {value:{label}}
    return Object.fromEntries(raw.map(v => [v.value, { label: v.label }]))
  }
  return raw
}

const isTemplateVariantDisabled = computed(() => !filter.template)

const hasTemplateVariants = computed(() => Object.keys(getTemplateVariants()).length > 0)

const getFilterFromUrl = () => {
  const url = new URL(window.location.href)

  const parseFilterValue = (key, value) => {
    if (!value) return []
    if (value.includes(',')) {
      const values = value.split(',').filter(v => v.trim() !== '')
      if (
        (key === 'year' ||
          key === 'funded_by' ||
          key === 'region' ||
          key === 'center_id' ||
          key === 'mode_of_procurement' ||
          key === 'supplier') &&
        (values.includes('all') || values.includes('0'))
      ) {
        return ['all']
      }
      return values
    }
    // Handle 'all' value for location filters
    if (
      (key === 'region' || key === 'province' || key === 'city' || key === 'district') &&
      value === 'all'
    ) {
      return ['all']
    }
    return [value]
  }

  return {
    center_id: parseFilterValue('center_id', url.searchParams.get('filter[center_id]')),
    dateRange: url.searchParams.get('filter[dateRange]') || '',
    year: parseFilterValue('year', url.searchParams.get('filter[year]')),
    month: parseFilterValue('month', url.searchParams.get('filter[month]')),
    program: url.searchParams.get('filter[program]') || '',
    partner_id: parseFilterValue('partner_id', url.searchParams.get('filter[partner_id]')),
    partner: parseFilterValue('partner', url.searchParams.get('filter[partner]')),
    farmer: parseFilterValue('farmer', url.searchParams.get('filter[farmer]')),
    farmer_id: parseFilterValue('farmer_id', url.searchParams.get('filter[farmer_id]')),
    milking_time: url.searchParams.get('filter[milking_time]') || '',
    week: url.searchParams.get('filter[week]') || '',
    status: url.searchParams.get('filter[status]') || '',
    funded_by: parseFilterValue('funded_by', url.searchParams.get('filter[funded_by]')),
    region: parseFilterValue('region', url.searchParams.get('filter[region]')),
    milk_type: url.searchParams.get('filter[milk_type]') || '',
    mode_of_procurement: parseFilterValue(
      'mode_of_procurement',
      url.searchParams.get('filter[mode_of_procurement]')
    ),
    supplier: parseFilterValue('supplier', url.searchParams.get('filter[supplier]')),
    province: parseFilterValue('province', url.searchParams.get('filter[province]')),
    city: parseFilterValue('city', url.searchParams.get('filter[city]')),
    division: parseFilterValue('division', url.searchParams.get('filter[division]')),
    masterlist_type: parseFilterValue(
      'masterlist_type',
      url.searchParams.get('filter[masterlist_type]')
    ),
    project: parseFilterValue('project', url.searchParams.get('filter[project]')),
    year_range: url.searchParams.get('filter[year_range]') || '',
    template_file: url.searchParams.get('filter[template_file]') || '',
    product: url.searchParams.get('filter[product]') || '',
    type: url.searchParams.get('filter[type]') || '',
    district: parseFilterValue('district', url.searchParams.get('filter[district]')),
    template: url.searchParams.get('filter[template]') || '',
    template_variant: url.searchParams.get('filter[template_variant]') || '',
    list_and_figures: url.searchParams.get('filter[list_and_figures]') || '',
    accreditation: parseFilterValue('accreditation', url.searchParams.get('filter[accreditation]')),
    partner_category:
      url.searchParams.get('filter[partner_category]') ||
      url.searchParams.get('filter[business_type]') ||
      '',
  }
}

const initialFilters = getFilterFromUrl()
const filter = reactive({
  center_id: initialFilters.center_id,
  year: initialFilters.year,
  funded_by: initialFilters.funded_by,
  region: initialFilters.region,
  mode_of_procurement: initialFilters.mode_of_procurement,
  supplier: initialFilters.supplier,
  province: initialFilters.province || [],
  city: initialFilters.city || [],
  division: initialFilters.division || [],
  masterlist_type: initialFilters.masterlist_type || [],
  month: initialFilters.month || [],
  program: initialFilters.program,
  partner_id: initialFilters.partner_id || [],
  partner: initialFilters.partner || [],
  farmer: initialFilters.farmer || [],
  farmer_id: initialFilters.farmer_id || [],
  project: initialFilters.project || [],
  milking_time: initialFilters.milking_time,
  week: initialFilters.week,
  status: initialFilters.status,
  milk_type: initialFilters.milk_type,
  dateRange: initialFilters.dateRange,
  year_range: initialFilters.year_range,
  template_file: initialFilters.template_file,
  product: initialFilters.product,
  type: initialFilters.type,
  district: initialFilters.district || [],
  template: initialFilters.template,
  template_variant: initialFilters.template_variant || '',
  list_and_figures: initialFilters.list_and_figures,
  accreditation: initialFilters.accreditation || [],
  partner_category: initialFilters.partner_category || '',
})

const emit = defineEmits(['update:filter'])

const handleCenterChange = centerId => {
  const id = Number(centerId)

  // If we're on the centers index page, navigate to the center's show view.
  const pathname = window.location.pathname || ''
  if (pathname === '/centers' || pathname === '/centers/') {
    router.get(`/centers/${id}`)
    return
  }

  // Toggle: clicking the already-selected center clears the filter
  const idStr = String(centerId)
  const current = Array.isArray(filter.center_id) ? filter.center_id : []
  const isSelected = current.includes(idStr)
  const next = isSelected ? [] : [idStr]
  filter.center_id = next
  updateFilters('center_id', next)
  emit('update:filter', { ...filter })
  router.get(window.location.href, {}, { preserveState: true, replace: true })
}

watch(
  () => page.url,
  () => {
    // Skip if we're currently updating filters to prevent recursive resets
    if (isUpdatingFilters) {
      return
    }

    const newFilters = getFilterFromUrl()

    // Update filter values from URL
    filter.center_id = newFilters.center_id
    filter.year = newFilters.year
    filter.funded_by = newFilters.funded_by
    filter.region = newFilters.region
    filter.mode_of_procurement = newFilters.mode_of_procurement
    filter.supplier = newFilters.supplier
    filter.province = newFilters.province
    filter.city = newFilters.city
    filter.division = newFilters.division
    filter.masterlist_type = newFilters.masterlist_type
    filter.month = newFilters.month
    filter.program = newFilters.program
    filter.partner_id = newFilters.partner_id
    filter.partner = newFilters.partner
    filter.farmer = newFilters.farmer
    filter.farmer_id = newFilters.farmer_id
    filter.milking_time = newFilters.milking_time
    filter.week = newFilters.week
    filter.status = newFilters.status
    filter.milk_type = newFilters.milk_type
    filter.dateRange = newFilters.dateRange
    filter.year_range = newFilters.year_range
    filter.project = newFilters.project
    filter.template_file = newFilters.template_file
    filter.product = newFilters.product
    filter.type = newFilters.type
    filter.district = newFilters.district
    filter.template = newFilters.template
    filter.template_variant = newFilters.template_variant
    filter.list_and_figures = newFilters.list_and_figures
    filter.accreditation = newFilters.accreditation
    filter.partner_category = newFilters.partner_category
  },
  { immediate: false }
)

// Handle browser back/forward when using pushState instead of router.get()
window.addEventListener('popstate', () => {
  if (isUpdatingFilters) return

  const newFilters = getFilterFromUrl()
  filter.center_id = newFilters.center_id
  filter.year = newFilters.year
  filter.funded_by = newFilters.funded_by
  filter.region = newFilters.region
  filter.mode_of_procurement = newFilters.mode_of_procurement
  filter.supplier = newFilters.supplier
  filter.province = newFilters.province
  filter.city = newFilters.city
  filter.division = newFilters.division
  filter.masterlist_type = newFilters.masterlist_type
  filter.month = newFilters.month
  filter.program = newFilters.program
  filter.partner_id = newFilters.partner_id
  filter.partner = newFilters.partner
  filter.farmer = newFilters.farmer
  filter.farmer_id = newFilters.farmer_id
  filter.milking_time = newFilters.milking_time
  filter.week = newFilters.week
  filter.status = newFilters.status
  filter.milk_type = newFilters.milk_type
  filter.dateRange = newFilters.dateRange
  filter.year_range = newFilters.year_range
  filter.project = newFilters.project
  filter.template_file = newFilters.template_file
  filter.product = newFilters.product
  filter.type = newFilters.type
  filter.district = newFilters.district
  filter.template = newFilters.template
  filter.template_variant = newFilters.template_variant
  filter.list_and_figures = newFilters.list_and_figures
  filter.accreditation = newFilters.accreditation
  filter.partner_category = newFilters.partner_category
})

let isUpdatingFilters = false
// All multi-selects (year, funded_by, region, center_id, supplier, province, city, division,
// month, partner, farmer, project, accreditation, etc.) and single-selects (template,
// template_variant, product, type) are now synced via handleMulti / handleSingle directly
// on @update:model-value — single-click URL contract, like PsgcAddressFilter.
// Removed 12+ copy-pasted `watch(..., isInitialLoad)` blocks for production maintainability.

watch(
  () => filter,
  newFilter => {
    emit('update:filter', { ...newFilter })
  },
  { deep: true }
)
</script>
<template>
  <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
    <template v-for="element in elements" :key="element">
      <div v-if="element == ELEMENTS.clusteredCenters" class="col-span-full w-full min-w-0">
        <div v-for="(centers, clusterKey) in filters[element]" :key="clusterKey" class="mb-4">
          <!-- Cluster name rendered as a section heading -->
          <h3 class="mb-1 text-sm font-semibold">{{ clusterKey }}</h3>
          <!-- Child centers rendered as indented bullet items -->
          <ul class="list-disc space-y-1 ps-4">
            <li v-for="center in centers" :key="center.id">
              <!-- For center admins: show non-clickable highlighted center -->
              <span
                v-if="isCenterAdmin && center.id === userCenterId"
                :title="center.label"
                class="text-warm-yellow block w-full text-left font-semibold break-words underline"
              >
                {{ center.label }}
              </span>
              <!-- For center admins: show disabled other centers -->
              <span
                v-else-if="isCenterAdmin"
                :title="center.label"
                class="block w-full text-left break-words text-gray-400"
              >
                {{ center.label }}
              </span>
              <!-- For non-center admins: normal clickable links -->
              <template v-else>
                <Link
                  v-if="isCentersRoute"
                  :href="`/centers/${center.id}`"
                  :title="center.label"
                  class="block w-full text-left break-words hover:text-yellow-600"
                >
                  {{ center.label }}
                </Link>
                <button
                  v-else
                  :title="center.label"
                  :class="[
                    'block w-full cursor-pointer text-left break-words hover:text-yellow-600',
                    {
                      'text-warm-yellow underline': parseInt(filter.center_id) === center.id,
                    },
                  ]"
                  @click="handleCenterChange(center.id)"
                >
                  {{ center.label }}
                </button>
              </template>
            </li>
          </ul>
        </div>
      </div>

      <div v-if="element == ELEMENTS.selectCenters">
        <label for="center-select" class="mb-1 block font-medium"
          >Center: <span class="text-red-500">*</span></label
        >
        <Select
          :model-value="filter.center_id"
          multiple
          @update:model-value="v => handleMultiSelect('center_id', v)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select centers" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="center in filters.centers"
              :key="center.id"
              :value="String(center.id)"
            >
              {{ center.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.yearRange">
        <label class="mb-1 block font-medium">Year Range:</label>
        <input
          v-model="filter.year_range"
          type="text"
          placeholder="e.g., 2020-2024"
          class="border-input w-full rounded-md border px-3 py-2 text-sm"
        />
      </div>

      <div v-if="element == ELEMENTS.dateRange">
        <label class="mb-1 block font-medium">Date Range:</label>
        <DateRange v-model="filter.dateRange" @update:model-value="handleDateRangeUpdate" />
      </div>

      <div v-if="element == ELEMENTS.years">
        <label for="year-select" class="mb-1 block font-medium">Year:</label>
        <Select
          :model-value="filter.year"
          multiple
          @update:model-value="v => handleMultiSelect('year', v)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select years" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="year in filters.years" :key="year.id" :value="String(year.id)">
              {{ year.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.months">
        <label for="month-select" class="mb-1 block font-medium">Month:</label>
        <Select
          :model-value="filter.month"
          multiple
          @update:model-value="v => handleMultiSelect('month', v)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select months" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(label, value) in filters.months"
              :key="value"
              :value="String(value)"
            >
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.programs">
        <label for="program-select" class="mb-1 block font-medium">Program:</label>
        <Select
          id="program-select"
          :model-value="filter.program"
          @update:model-value="value => handleSingleSelect('program', value)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select program" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(label, value) in filters.programs"
              :key="value"
              :value="String(value)"
            >
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.product">
        <label for="product-select" class="mb-1 block font-medium">Product:</label>
        <Select
          id="product-select"
          :model-value="filter.product"
          @update:model-value="value => handleSingleSelect('product', value)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select product" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(label, value) in filters.products"
              :key="value"
              :value="String(value)"
            >
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.reportType">
        <label for="report-type-select" class="mb-1 block font-medium">Type:</label>
        <Select
          id="report-type-select"
          :model-value="filter.type"
          @update:model-value="value => handleSingleSelect('type', value)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="(label, value) in filters.types" :key="value" :value="String(value)">
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.weeks">
        <label for="week-select" class="mb-1 block font-medium">Week:</label>
        <Select
          id="week-select"
          :model-value="filter.week"
          @update:model-value="value => handleSingleSelect('week', value)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select week" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="(label, value) in filters.weeks" :key="value" :value="String(value)">
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.status">
        <label for="status-select" class="mb-1 block font-medium">Status:</label>
        <Select
          id="status-select"
          :model-value="filter.status"
          @update:model-value="value => handleSingleSelect('status', value)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(label, value) in filters.status"
              :key="value"
              :value="String(value)"
            >
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.projects">
        <label for="project-select" class="mb-1 block font-medium">Project:</label>
        <Select
          :model-value="filter.project"
          multiple
          @update:model-value="v => handleMultiSelect('project', v)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select projects" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(label, value) in filters.projects"
              :key="value"
              :value="String(value)"
            >
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.fundedBy">
        <label for="funded-by-select" class="mb-1 block font-medium">Funded By:</label>
        <Select
          :model-value="filter.funded_by"
          multiple
          @update:model-value="v => handleMultiSelect('funded_by', v)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select funding" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(label, value) in filters.fundedBy"
              :key="value"
              :value="String(value)"
            >
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.psgcAddress" class="col-span-1 sm:col-span-2 lg:col-span-4">
        <PsgcAddressFilter />
      </div>

      <div v-if="element == ELEMENTS.region">
        <label for="region-select" class="mb-1 block font-medium">Region:</label>
        <Select
          :model-value="filter.region"
          multiple
          @update:model-value="v => handleMultiSelect('region', v)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select regions" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(label, value) in filters.region"
              :key="value"
              :value="String(value)"
            >
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.milkType">
        <label for="milk-type-select" class="mb-1 block font-medium">Milk Type:</label>
        <Select
          id="milk-type-select"
          :model-value="filter.milk_type"
          @update:model-value="value => handleSingleSelect('milk_type', value)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(label, value) in filters.milkType"
              :key="value"
              :value="String(value)"
            >
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.modeOfProcurement">
        <label for="mode-of-procurement-select" class="mb-1 block font-medium">Procurement:</label>
        <Select
          :model-value="filter.mode_of_procurement"
          multiple
          @update:model-value="v => handleMultiSelect('mode_of_procurement', v)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select modes" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(label, value) in filters.modeOfProcurement"
              :key="value"
              :value="String(value)"
            >
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.suppliers">
        <label for="supplier-select" class="mb-1 block font-medium">Supplier:</label>
        <div class="w-full">
          <SearchableSelect
            :model-value="filter.supplier"
            :endpoint="filters.suppliers"
            placeholder="Search suppliers"
            multiple
            @update:model-value="v => handleMultiSelect('supplier', v)"
          />
        </div>
      </div>

      <div v-if="element == ELEMENTS.provinces">
        <label for="province-select" class="mb-1 block font-medium">Province:</label>
        <div class="w-full">
          <SearchableSelect
            :model-value="filter.province"
            :endpoint="filters.provinces"
            placeholder="Search provinces"
            multiple
            @update:model-value="v => handleMultiSelect('province', v)"
          />
        </div>
      </div>

      <div v-if="element == ELEMENTS.cities">
        <label for="city-select" class="mb-1 block font-medium">Municipality:</label>
        <div class="w-full">
          <SearchableSelect
            :model-value="filter.city"
            :endpoint="filters.cities"
            placeholder="Search cities"
            multiple
            @update:model-value="v => handleMultiSelect('city', v)"
          />
        </div>
      </div>

      <div v-if="element == ELEMENTS.divisions">
        <label for="division-select" class="mb-1 block font-medium">Division:</label>
        <div class="w-full">
          <SearchableSelect
            :model-value="filter.division"
            :endpoint="filters.divisions"
            placeholder="Search divisions"
            multiple
            @update:model-value="v => handleMultiSelect('division', v)"
          />
        </div>
      </div>

      <div v-if="element == ELEMENTS.district">
        <label for="district-select" class="mb-1 block font-medium">District:</label>
        <div class="w-full">
          <SearchableSelect
            v-model="filter.district"
            :endpoint="filters.district"
            placeholder="Search districts"
            multiple
          />
        </div>
      </div>

      <div v-if="element == ELEMENTS.masterlistType">
        <label for="masterlist-type-select" class="mb-1 block font-medium">Masterlist Type:</label>
        <Select
          :model-value="filter.masterlist_type"
          multiple
          @update:model-value="v => handleMultiSelect('masterlist_type', v)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select types" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(label, value) in filters.masterlistTypes"
              :key="value"
              :value="String(value)"
            >
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.partners">
        <label for="partner-select" class="mb-1 block font-medium">Partner:</label>
        <div class="w-full">
          <SearchableSelect
            id="partner-select"
            :model-value="filter.partner_id"
            :endpoint="filters[element]"
            :prepend-options="[{ value: 'all', label: 'All Partners' }]"
            :filter-params="{
              center_id: filter.center_id || null,
              year: filter.year || null,
              month: filter.month || null,
              week: filter.week || null,
            }"
            placeholder="Search partner"
            multiple
            @update:model-value="v => handleMultiSelect('partner_id', v)"
          />
        </div>
      </div>

      <div v-if="element == ELEMENTS.selectPartners">
        <label for="partner-select" class="mb-1 block font-medium">Partner:</label>
        <div class="w-full">
          <SearchableSelect
            id="partner-select"
            :model-value="filter.partner"
            :endpoint="filters.partners"
            :prepend-options="[{ value: 'all', label: 'All Partners' }]"
            :filter-params="{
              center_id: filter.center_id || null,
            }"
            placeholder="Search partner"
            multiple
            @update:model-value="v => handleMultiSelect('partner', v)"
          />
        </div>
      </div>

      <div v-if="element == ELEMENTS.selectFarmers">
        <label for="farmer-select" class="mb-1 block font-medium">Farmer:</label>
        <div class="w-full">
          <SearchableSelect
            id="farmer-select"
            :model-value="filter.farmer"
            :endpoint="filters.farmers"
            :prepend-options="[{ value: 'all', label: 'All Farmers' }]"
            :filter-params="{
              center_id: filter.center_id || null,
              partner_id: filter.partner || null,
            }"
            placeholder="Search farmer"
            multiple
            @update:model-value="v => handleMultiSelect('farmer', v)"
          />
        </div>
      </div>

      <div v-if="element == ELEMENTS.farmers">
        <label for="farmer-select" class="mb-1 block font-medium">Farmer:</label>
        <div class="w-full">
          <SearchableSelect
            id="farmer-select"
            :model-value="filter.farmer_id"
            :endpoint="filters[element]"
            :prepend-options="[{ value: 'all', label: 'All Farmers' }]"
            :filter-params="{
              center_id: filter.center_id || null,
              partner_id: filter.partner_id || null,
            }"
            placeholder="Search farmer"
            multiple
            @update:model-value="v => handleMultiSelect('farmer_id', v)"
          />
        </div>
      </div>

      <div v-if="element == ELEMENTS.partnerType">
        <label for="business-type-select" class="mb-1 block font-medium">Partner Category:</label>
        <Select
          id="business-type-select"
          :model-value="filter.partner_category"
          @update:model-value="value => handleSingleSelect('partner_category', value)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select category" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Categories</SelectItem>
            <SelectItem
              v-for="type in filters.partnerTypes || filters.businessTypes"
              :key="type.value"
              :value="String(type.value)"
            >
              {{ type.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.accreditation">
        <label for="accreditation-select" class="mb-1 block font-medium">Accreditations:</label>
        <Select
          :model-value="filter.accreditation"
          multiple
          @update:model-value="v => handleMultiSelect('accreditation', v)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select accreditations" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="item in filters.accreditations"
              :key="item.value"
              :value="String(item.value)"
            >
              {{ item.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.reportTemplate">
        <label for="report-template-select" class="mb-1 block font-medium"
          >Template: <span class="text-red-500">*</span></label
        >
        <Select
          id="report-template-select"
          :model-value="filter.template"
          @update:model-value="value => handleSingleSelect('template', value)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select template" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="template in filters.report_templates"
              :key="template.value"
              :value="String(template.value)"
            >
              {{ template.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Template Variant: immediately after Template, disabled until a template is selected -->
      <!-- Shown for any report_type with variants (BDP + directory) -->
      <div v-if="element == ELEMENTS.reportTemplate && hasTemplateVariants">
        <label for="template-variant-select" class="mb-1 block font-medium"
          >Template Variant: <span class="text-red-500">*</span></label
        >
        <Select
          id="template-variant-select"
          :model-value="filter.template_variant"
          :disabled="isTemplateVariantDisabled"
          @update:model-value="value => handleSingleSelect('template_variant', value)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select variant" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(variant, key) in getTemplateVariants()"
              :key="key"
              :value="String(key)"
            >
              {{ variant.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.listAndFigures && filters.report_type !== 'bdp'">
        <label for="list-and-figures-select" class="mb-1 block font-medium"
          >List and Figures: <span class="text-red-500">*</span></label
        >
        <Select
          id="list-and-figures-select"
          :model-value="filter.list_and_figures"
          @update:model-value="value => handleSingleSelect('list_and_figures', value)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select list and figures" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="list"> List </SelectItem>
            <SelectItem value="figures"> Figures </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="element == ELEMENTS.templateFile">
        <label for="template-file-select" class="mb-1 block font-medium">Template:</label>
        <Select
          id="template-file-select"
          :model-value="filter.template_file"
          @update:model-value="value => handleSingleSelect('template_file', value)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select template" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="template in filters.templates"
              :key="template.value"
              :value="template.value"
            >
              {{ template.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </template>
  </div>
</template>
