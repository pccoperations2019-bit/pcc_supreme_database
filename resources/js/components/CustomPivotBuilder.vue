<script setup>
import draggable from 'vuedraggable'
import { computed, ref } from 'vue'
import { usePivotBuilder } from '@/composable/usePivotBuilder'
import PivotResultGrid from '@/components/PivotResultGrid.vue'
import FilterValuePicker from '@/components/FilterValuePicker.vue'
import {
  Download as DownloadIcon,
  Plus as PlusIcon,
  Search as SearchIcon,
  Table as TableIcon,
  List as ListIcon,
  Columns as ColumnsIcon,
  Sigma as SigmaIcon,
  Filter as FilterIcon,
  Database as DatabaseIcon,
  Grid as GridIcon,
} from 'lucide-vue-next'

const props = defineProps({
  sources: {
    type: Array,
    default: () => [],
  },
  initialSource: {
    type: String,
    default: 'center',
  },
})

const { source, fields, zones, result, loading, error, runPivot } = usePivotBuilder(
  props.initialSource
)

const showTotals = computed(
  () => zones.rows.some(f => f.showTotals) || zones.columns.some(f => f.showTotals)
)

const sourceOptions = computed(() => {
  if (props.sources && props.sources.length) {
    return props.sources
  }
  return [
    { key: 'center', label: 'Center' },
    { key: 'partners', label: 'Partners' },
    { key: 'farmers', label: 'Farmers' },
  ]
})

const showFieldSelector = ref(false)
const fieldSelectorZone = ref(null)
const selectedField = ref(null)
const openFilterFor = ref(null)
const filterChipRefs = ref({})
const fieldSearch = ref('')

// Default fallback list of fields if backend fields are loading/empty
const defaultFields = [
  { field: 'center_code', label: 'Center code', type: 'string' },
  { field: 'center_name', label: 'Center name', type: 'string' },
  { field: 'status', label: 'Status', type: 'string' },
  { field: 'program', label: 'Program', type: 'string' },
  { field: 'region', label: 'Region', type: 'string' },
  { field: 'province', label: 'Province', type: 'string' },
  { field: 'district', label: 'District', type: 'string' },
  { field: 'municipality', label: 'Municipality', type: 'string' },
  { field: 'barangay', label: 'Barangay', type: 'string' },
  { field: 'zip_code', label: 'Zip code', type: 'string' },
  { field: 'longitude', label: 'Longitude', type: 'number' },
  { field: 'latitude', label: 'Latitude', type: 'number' },
  { field: 'value_chain', label: 'Value chain', type: 'string' },
  { field: 'products', label: 'Products', type: 'string' },
  { field: 'mfp_participant', label: 'Milk Feeding Program Participant', type: 'boolean' },
  { field: 'date_engaged_mfp', label: 'Date engaged in MFP', type: 'date' },
  { field: 'fda_lto_status', label: 'FDA LTO status', type: 'string' },
  { field: 'fda_lto_date_accredited', label: 'FDA LTO date accredited', type: 'date' },
  { field: 'nda_lto_status', label: 'NDA LTO status', type: 'string' },
  { field: 'nda_lto_date_accredited', label: 'NDA LTO date accredited', type: 'date' },
]

const activeFields = computed(() =>
  fields.value && fields.value.length ? fields.value : defaultFields
)

const filteredFields = computed(() => {
  if (!fieldSearch.value.trim()) return activeFields.value
  const q = fieldSearch.value.toLowerCase()
  return activeFields.value.filter(f => f.label.toLowerCase().includes(q))
})

const availableFieldsForZone = computed(() => {
  if (!fieldSelectorZone.value) return []
  const usedFields = zones[fieldSelectorZone.value].map(f => f.field)
  return activeFields.value.filter(f => !usedFields.includes(f.field))
})

const addFieldToZone = field => {
  if (!fieldSelectorZone.value) return

  const newField = {
    field: field.field,
    label: field.label,
    type: field.type,
    order: 'asc',
    showTotals: false,
    repeatRowLabels: true,
  }

  if (fieldSelectorZone.value === 'values') {
    newField.agg = 'count'
    newField.showAs = 'default'
  }

  zones[fieldSelectorZone.value].push(newField)

  showFieldSelector.value = false
  fieldSelectorZone.value = null
  selectedField.value = null
}

const handleFieldSelection = () => {
  if (selectedField.value && fieldSelectorZone.value) {
    const field = activeFields.value.find(f => f.field === selectedField.value)
    if (field) {
      addFieldToZone(field)
    }
  }
}

const handleZoneChange = (zone, evt) => {
  if (!evt.added?.element) return

  const field = evt.added.element

  if (zone === 'values') {
    field.agg = field.agg ?? 'count'
    field.showAs = field.showAs ?? 'default'
  }
}

const removeFieldFromZone = (zone, index) => {
  zones[zone].splice(index, 1)
}

const openFieldSelector = zone => {
  if (fieldSelectorZone.value === zone && showFieldSelector.value) {
    showFieldSelector.value = false
    fieldSelectorZone.value = null
  } else {
    fieldSelectorZone.value = zone
    showFieldSelector.value = true
    selectedField.value = null
  }
}

const cloneField = field => {
  return {
    field: field.field,
    label: field.label,
    type: field.type,
    order: 'asc',
    showTotals: false,
    repeatRowLabels: true,
  }
}

function filterSummary(el) {
  if (!el.filterConfig) return 'All'
  const n = (el.filterConfig.values?.length || 0) + (el.filterConfig.includeBlanks ? 1 : 0)
  return n === 0 ? 'None' : `${n} selected`
}

const pivotExportForm = ref(null)

/**
 * The pivot export is generated on request rather than queued, so there is no
 * Document to poll. Surface it in the export queue card anyway for consistency:
 * the card's Download button re-issues this request and the card self-dismisses.
 */
function notifyPivotExportQueued() {
  const form = pivotExportForm.value
  if (!form) return

  const params = new window.URLSearchParams(new window.FormData(form)).toString()

  window.dispatchEvent(
    new window.CustomEvent('report-export-queued', {
      detail: {
        documentId: `local-pivot-${Date.now()}`,
        local: true,
        reportType: 'custom-pivot',
        status: 'processed',
        fileName: 'pivot-export.xlsx',
        fileSize: 0,
        downloadUrl: `/reports/pivot/export?${params}`,
      },
    })
  )
}
</script>

<template>
  <div class="flex w-full flex-col items-stretch gap-5 text-gray-900 select-none xl:flex-row">
    <!-- ── CARD 1: MAIN CENTER CARD (Pivot Result + Pivot Layout) ── -->
    <div
      class="flex min-w-0 flex-1 flex-col justify-between rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"
    >
      <div class="grid h-full flex-1 grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- ── Left Sub-Column: Pivot Result Table ── -->
        <div class="flex min-w-0 flex-col justify-between space-y-4 lg:col-span-8">
          <!-- Header Bar -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-sm font-bold text-gray-900">
              <TableIcon class="size-4 text-gray-600" />
              <span>Pivot Result</span>
            </div>

            <form ref="pivotExportForm" method="GET" action="/reports/pivot/export">
              <input type="hidden" name="source" :value="source" />
              <input type="hidden" name="show_totals" :value="showTotals ? '1' : '0'" />
              <input
                type="hidden"
                name="rows"
                :value="zones.rows.map(field => field.field).join(',')"
              />
              <input
                type="hidden"
                name="columns"
                :value="zones.columns.map(field => field.field).join(',')"
              />
              <input
                type="hidden"
                name="values"
                :value="
                  JSON.stringify(
                    zones.values.map(field => ({ field: field.field, agg: field.agg ?? 'count' }))
                  )
                "
              />
              <input
                type="hidden"
                name="filters"
                :value="
                  JSON.stringify(
                    zones.filters
                      .filter(f => f.filterConfig)
                      .map(f => ({
                        field: f.field,
                        values: f.filterConfig.values,
                        includeBlanks: f.filterConfig.includeBlanks,
                      }))
                  )
                "
              />
              <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-2xs transition-colors hover:bg-blue-700"
                @click="notifyPivotExportQueued"
              >
                <DownloadIcon class="size-3.5" />
                Export to Excel
              </button>
            </form>
          </div>

          <!-- Error Alert -->
          <div
            v-if="error"
            class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-600"
          >
            Unable to build pivot report:
            {{ error?.message || error?.response?.data?.message || 'Unknown error' }}
          </div>
          <!-- Pivot Result — loading centered inside table container -->
          <div class="relative flex flex-1 flex-col">
            <div
              v-if="loading"
              class="flex min-h-[480px] flex-1 items-center justify-center rounded-xl border border-gray-200 bg-white shadow-2xs"
            >
              <div class="flex flex-col items-center gap-3">
                <div
                  class="size-8 animate-spin rounded-full border-[3px] border-gray-200 border-t-blue-600"
                ></div>
                <span class="text-xs font-medium text-gray-500">Loading pivot data…</span>
              </div>
            </div>

            <PivotResultGrid
              v-else
              :result="result"
              :row-fields="zones.rows"
              :value-fields="zones.values"
              :column-fields="zones.columns"
              :show-totals="showTotals"
              class="flex flex-1 flex-col justify-between"
            />
          </div>
        </div>

        <!-- ── Right Sub-Column: Pivot Layout Zones ── -->
        <div class="flex flex-col space-y-2.5 lg:col-span-4">
          <h3 class="mb-1 text-sm font-bold text-gray-900">Pivot Layout</h3>

          <!-- Zone 1: Rows -->
          <div class="space-y-1">
            <div class="flex items-center justify-between text-xs font-semibold text-gray-700">
              <span class="flex items-center gap-1.5">
                <ListIcon class="size-3.5 text-gray-500" />
                Rows
              </span>
              <button
                type="button"
                class="cursor-pointer text-[11px] font-medium text-blue-600 hover:underline"
                @click="openFieldSelector('rows')"
              >
                + Add
              </button>
            </div>

            <div v-if="showFieldSelector && fieldSelectorZone === 'rows'" class="mb-1.5">
              <select
                v-model="selectedField"
                class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
                @change="handleFieldSelection"
              >
                <option :value="null">Select a field...</option>
                <option v-for="f in availableFieldsForZone" :key="f.field" :value="f.field">
                  {{ f.label }}
                </option>
              </select>
            </div>

            <draggable
              v-model="zones.rows"
              group="pivotFields"
              item-key="field"
              class="min-h-[38px] space-y-1"
              aria-label="Rows drop zone"
              :animation="150"
              :empty-insert-threshold="30"
              :force-fallback="true"
              :fallback-on-body="true"
              ghost-class="pivot-drag-ghost"
              fallback-class="pivot-drag-fallback"
              @change="evt => handleZoneChange('rows', evt)"
            >
              <template #item="{ element, index }">
                <div
                  class="zone-item rounded-lg border border-blue-100 bg-[#f0f6ff] p-1.5 px-2 text-xs shadow-2xs select-none"
                >
                  <div class="flex items-center justify-between font-medium text-gray-800">
                    <span class="truncate">{{ element.label }}</span>
                    <button
                      type="button"
                      class="ml-1 cursor-pointer text-sm font-bold text-gray-400 hover:text-red-500"
                      @click="removeFieldFromZone('rows', index)"
                    >
                      ×
                    </button>
                  </div>
                  <div
                    class="mt-1 space-y-1 border-t border-blue-100/70 pt-1 text-[11px] text-gray-600"
                  >
                    <div class="flex items-center justify-between">
                      <span>Order:</span>
                      <select
                        v-model="element.order"
                        class="w-28 cursor-pointer rounded border border-gray-300 bg-white px-2 py-0.5 text-[11px] font-normal text-gray-700 focus:border-blue-500 focus:outline-none"
                        @change="runPivot"
                      >
                        <option value="asc">Ascending</option>
                        <option value="desc">Descending</option>
                      </select>
                    </div>
                    <label class="flex cursor-pointer items-center gap-1.5">
                      <input
                        v-model="element.showTotals"
                        type="checkbox"
                        class="size-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      />
                      <span>Show totals</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5">
                      <input
                        v-model="element.repeatRowLabels"
                        type="checkbox"
                        class="size-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      />
                      <span>Repeat row labels</span>
                    </label>
                  </div>
                </div>
              </template>
              <template #header>
                <div
                  v-if="zones.rows.length === 0"
                  class="flex min-h-[38px] items-center justify-center rounded-lg border border-blue-100 bg-[#f0f6ff] px-3 py-2 text-center text-xs font-medium text-[#7b9ac0] select-none"
                >
                  Drag fields here
                </div>
              </template>
            </draggable>
          </div>

          <!-- Zone 2: Columns -->
          <div class="space-y-1">
            <div class="flex items-center justify-between text-xs font-semibold text-gray-700">
              <span class="flex items-center gap-1.5">
                <ColumnsIcon class="size-3.5 text-gray-500" />
                Columns
              </span>
              <button
                type="button"
                class="cursor-pointer text-[11px] font-medium text-blue-600 hover:underline"
                @click="openFieldSelector('columns')"
              >
                + Add
              </button>
            </div>

            <div v-if="showFieldSelector && fieldSelectorZone === 'columns'" class="mb-1.5">
              <select
                v-model="selectedField"
                class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
                @change="handleFieldSelection"
              >
                <option :value="null">Select a field...</option>
                <option v-for="f in availableFieldsForZone" :key="f.field" :value="f.field">
                  {{ f.label }}
                </option>
              </select>
            </div>

            <draggable
              v-model="zones.columns"
              group="pivotFields"
              item-key="field"
              class="min-h-[38px] space-y-1"
              aria-label="Columns drop zone"
              :animation="150"
              :empty-insert-threshold="30"
              :force-fallback="true"
              :fallback-on-body="true"
              ghost-class="pivot-drag-ghost"
              fallback-class="pivot-drag-fallback"
              @change="evt => handleZoneChange('columns', evt)"
            >
              <template #item="{ element, index }">
                <div
                  class="zone-item rounded-lg border border-blue-100 bg-[#f0f6ff] p-1.5 px-2 text-xs shadow-2xs select-none"
                >
                  <div class="flex items-center justify-between font-medium text-gray-800">
                    <span class="truncate">{{ element.label }}</span>
                    <button
                      type="button"
                      class="ml-1 cursor-pointer text-sm font-bold text-gray-400 hover:text-red-500"
                      @click="removeFieldFromZone('columns', index)"
                    >
                      ×
                    </button>
                  </div>
                </div>
              </template>
              <template #header>
                <div
                  v-if="zones.columns.length === 0"
                  class="flex min-h-[38px] items-center justify-center rounded-lg border border-blue-100 bg-[#f0f6ff] px-3 py-2 text-center text-xs font-medium text-[#7b9ac0] select-none"
                >
                  Drag fields here
                </div>
              </template>
            </draggable>
          </div>

          <!-- Zone 3: Values -->
          <div class="space-y-1">
            <div class="flex items-center justify-between text-xs font-semibold text-gray-700">
              <span class="flex items-center gap-1.5">
                <SigmaIcon class="size-3.5 text-gray-500" />
                Values
              </span>
              <button
                type="button"
                class="cursor-pointer text-[11px] font-medium text-blue-600 hover:underline"
                @click="openFieldSelector('values')"
              >
                + Add
              </button>
            </div>

            <div v-if="showFieldSelector && fieldSelectorZone === 'values'" class="mb-1.5">
              <select
                v-model="selectedField"
                class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
                @change="handleFieldSelection"
              >
                <option :value="null">Select a field...</option>
                <option v-for="f in availableFieldsForZone" :key="f.field" :value="f.field">
                  {{ f.label }}
                </option>
              </select>
            </div>

            <draggable
              v-model="zones.values"
              group="pivotFields"
              item-key="field"
              class="min-h-[38px] space-y-1"
              aria-label="Values drop zone"
              :animation="150"
              :empty-insert-threshold="30"
              :force-fallback="true"
              :fallback-on-body="true"
              ghost-class="pivot-drag-ghost"
              fallback-class="pivot-drag-fallback"
              @change="evt => handleZoneChange('values', evt)"
            >
              <template #item="{ element, index }">
                <div
                  class="zone-item rounded-lg border border-blue-100 bg-[#f0f6ff] p-1.5 px-2 text-xs shadow-2xs select-none"
                >
                  <div class="flex items-center justify-between font-medium text-gray-800">
                    <span class="truncate">{{ element.label }}</span>
                    <button
                      type="button"
                      class="ml-1 cursor-pointer text-sm font-bold text-gray-400 hover:text-red-500"
                      @click="removeFieldFromZone('values', index)"
                    >
                      ×
                    </button>
                  </div>
                  <div
                    class="mt-1 space-y-1 border-t border-blue-100/70 pt-1 text-[11px] text-gray-600"
                  >
                    <div class="flex items-center justify-between">
                      <span>Agg:</span>
                      <select
                        v-model="element.agg"
                        class="w-28 cursor-pointer rounded border border-gray-300 bg-white px-2 py-0.5 text-[11px] font-normal text-gray-700 focus:border-blue-500 focus:outline-none"
                      >
                        <option value="count">Count</option>
                        <option value="sum">Sum</option>
                        <option value="avg">Avg</option>
                      </select>
                    </div>
                  </div>
                </div>
              </template>
              <template #header>
                <div
                  v-if="zones.values.length === 0"
                  class="flex min-h-[38px] items-center justify-center rounded-lg border border-blue-100 bg-[#f0f6ff] px-3 py-2 text-center text-xs font-medium text-[#7b9ac0] select-none"
                >
                  Drag fields here
                </div>
              </template>
            </draggable>
          </div>

          <!-- Zone 4: Filters -->
          <div class="space-y-1">
            <div class="flex items-center justify-between text-xs font-semibold text-gray-700">
              <span class="flex items-center gap-1.5">
                <FilterIcon class="size-3.5 text-gray-500" />
                Filters
              </span>
              <button
                type="button"
                class="cursor-pointer text-[11px] font-medium text-blue-600 hover:underline"
                @click="openFieldSelector('filters')"
              >
                + Add
              </button>
            </div>

            <div v-if="showFieldSelector && fieldSelectorZone === 'filters'" class="mb-1.5">
              <select
                v-model="selectedField"
                class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
                @change="handleFieldSelection"
              >
                <option :value="null">Select a field...</option>
                <option v-for="f in availableFieldsForZone" :key="f.field" :value="f.field">
                  {{ f.label }}
                </option>
              </select>
            </div>

            <draggable
              v-model="zones.filters"
              group="pivotFields"
              item-key="field"
              class="min-h-[38px] space-y-1"
              aria-label="Filters drop zone"
              :animation="150"
              :empty-insert-threshold="30"
              :force-fallback="true"
              :fallback-on-body="true"
              ghost-class="pivot-drag-ghost"
              fallback-class="pivot-drag-fallback"
              @change="evt => handleZoneChange('filters', evt)"
            >
              <template #item="{ element, index }">
                <div class="relative select-none">
                  <div
                    :ref="el => (filterChipRefs[element.field] = el)"
                    class="flex cursor-pointer items-center justify-between rounded-lg border border-blue-100 bg-white px-2 py-1 text-xs shadow-2xs transition-colors hover:bg-gray-50"
                    @click="openFilterFor = element.field"
                  >
                    <span class="truncate font-medium text-gray-800">{{ element.label }}</span>
                    <div class="ml-1 flex shrink-0 items-center gap-1.5">
                      <span
                        class="rounded border border-blue-100 bg-blue-50 px-1.5 py-0.5 text-[11px] text-blue-600"
                        >{{ filterSummary(element) }}</span
                      >
                      <button
                        type="button"
                        class="cursor-pointer text-sm font-bold text-gray-400 hover:text-red-500"
                        @click.stop="removeFieldFromZone('filters', index)"
                      >
                        ×
                      </button>
                    </div>
                  </div>
                  <FilterValuePicker
                    v-if="openFilterFor === element.field"
                    :source="source"
                    :field="element.field"
                    :label="element.label"
                    :anchor-el="filterChipRefs[element.field]"
                    :model-value="element.filterConfig"
                    @update:model-value="element.filterConfig = $event"
                    @apply="runPivot"
                    @close="openFilterFor = null"
                  />
                </div>
              </template>
              <template #header>
                <div
                  v-if="zones.filters.length === 0"
                  class="flex min-h-[38px] items-center justify-center rounded-lg border border-blue-100 bg-[#f0f6ff] px-3 py-2 text-center text-xs font-medium text-[#7b9ac0] select-none"
                >
                  Drag fields here
                </div>
              </template>
            </draggable>
          </div>
        </div>
      </div>
    </div>

    <!-- ── CARD 2: RIGHT MOST WHITE CARD (Data Source & Pivot Fields) ── -->
    <div
      class="flex w-full shrink-0 flex-col justify-between space-y-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm lg:w-72"
    >
      <!-- Data source section -->
      <div>
        <div class="mb-2 flex items-center gap-2 text-xs font-bold text-gray-900">
          <DatabaseIcon class="size-3.5 text-gray-600" />
          <span>Data source</span>
        </div>
        <select
          v-model="source"
          class="w-full cursor-pointer rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-xs font-medium text-gray-700 shadow-2xs focus:border-blue-500 focus:outline-none"
        >
          <option v-for="item in sourceOptions" :key="item.key" :value="item.key">
            {{ item.label }}
          </option>
        </select>
      </div>

      <!-- Pivot Fields section -->
      <div class="flex min-h-0 flex-1 flex-col">
        <div class="mb-2 flex items-center gap-2 text-xs font-bold text-gray-900">
          <GridIcon class="size-3.5 text-gray-600" />
          <span>Pivot Fields</span>
        </div>

        <div class="relative mb-3">
          <input
            v-model="fieldSearch"
            type="text"
            placeholder="Search data fields..."
            class="w-full rounded-xl border border-gray-200 bg-white py-2 pr-3 pl-8 text-xs text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none"
          />
          <SearchIcon
            class="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-gray-400"
          />
        </div>

        <div class="max-h-[500px] flex-1 overflow-y-auto pr-1">
          <draggable
            :model-value="filteredFields"
            :group="{ name: 'pivotFields', pull: 'clone', put: false }"
            item-key="field"
            :sort="false"
            :animation="150"
            :force-fallback="true"
            :fallback-on-body="true"
            ghost-class="pivot-drag-ghost"
            fallback-class="pivot-drag-fallback"
            :clone="cloneField"
            class="space-y-0.5 text-xs font-medium text-gray-700"
          >
            <template #item="{ element }">
              <div
                role="button"
                :aria-label="`Field ${element.label}`"
                class="field-list-item cursor-grab truncate rounded-lg px-2.5 py-1.5 transition-colors select-none hover:bg-blue-50/70 hover:text-blue-700 active:cursor-grabbing"
              >
                {{ element.label }}
              </div>
            </template>
          </draggable>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.field-list-item,
.zone-item {
  user-select: none;
  -webkit-user-select: none;
  -webkit-touch-callout: none;
  -webkit-user-drag: none;
  touch-action: manipulation;
}

/* Rounded white card preview for item attached to mouse cursor */
:deep(.pivot-drag-fallback) {
  opacity: 1 !important;
  background-color: #ffffff !important;
  border: 1.5px solid #2563eb !important;
  border-radius: 0.75rem !important;
  box-shadow:
    0 10px 25px -5px rgba(37, 99, 235, 0.25),
    0 4px 6px -2px rgba(0, 0, 0, 0.1) !important;
  padding: 0.5rem 0.875rem !important;
  font-size: 0.75rem !important;
  font-weight: 600 !important;
  color: #1e293b !important;
  pointer-events: none !important;
  z-index: 99999 !important;
  display: flex !important;
  align-items: center !important;
  gap: 0.5rem !important;
  transform: scale(1.02) !important;
  white-space: nowrap !important;
  text-shadow: none !important;
  box-sizing: border-box !important;
  line-height: 1.25rem !important;
}

/* Dashed placeholder card left in list/zone during drag */
:deep(.pivot-drag-ghost) {
  opacity: 0.4 !important;
  background-color: #eff6ff !important;
  border: 1.5px dashed #60a5fa !important;
  border-radius: 0.75rem !important;
  color: transparent !important;
  text-shadow: none !important;
}
</style>
