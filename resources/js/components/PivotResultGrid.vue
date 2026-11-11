<script setup>
import { computed } from 'vue'

const props = defineProps({
  result: {
    type: Object,
    default: null,
  },
  rowFields: {
    type: Array,
    default: () => [],
  },
  valueFields: {
    type: Array,
    default: () => [],
  },
  columnFields: {
    type: Array,
    default: () => [],
  },
  showTotals: {
    type: Boolean,
    default: false,
  },
})

const hasRows = computed(() => props.result?.rows && props.result.rows.length > 0)
const hasValues = computed(() => props.valueFields && props.valueFields.length > 0)

/**
 * The server always returns at least one column key, using '' as the bucket for
 * "no column field was grouped" (see PivotBuilderService::buildFigures). So the
 * result's column array is never empty and cannot by itself tell us whether a
 * real grouping exists. Decide from the configured fields instead, and drop
 * blank keys so the placeholder bucket never surfaces as an empty column.
 */
const columnKeys = computed(() =>
  (props.result?.columns || []).filter(key => key !== '' && key !== null && key !== undefined)
)

const hasColumns = computed(() => props.columnFields.length > 0 && columnKeys.value.length > 0)

const rowColumns = computed(() => props.rowFields || [])
const displayRows = computed(() => props.result?.rows || [])
const totalRows = computed(() => displayRows.value.length)

const getRowKey = row => {
  return Object.values(row).join('|')
}

/**
 * Aliases identifying each value field, in the order the server emitted them.
 * Each data cell is keyed by alias, so a pivot with several value fields keeps
 * one number per field instead of overwriting all but the last.
 */
const valueKeys = computed(() => {
  const fromServer = props.result?.value_fields
  if (Array.isArray(fromServer) && fromServer.length) return fromServer
  return props.valueFields.map(v => `${v.field}_${v.agg ?? 'count'}`)
})

const valueCount = computed(() => props.valueFields.length)

/** Value bag for one cell: { alias: number } for the given row and column key. */
const cellValues = (row, column) => {
  if (!props.result?.data) return {}
  return props.result.data[getRowKey(row)]?.[column] || {}
}

const getValueForCell = (row, column, valueIndex) => {
  if (!props.result?.data) return '-'
  return cellValues(row, column)[valueKeys.value[valueIndex]] ?? '-'
}

const valueLabels = computed(() => {
  return props.valueFields.map(v => {
    const aggLabel = v.agg === 'count' ? 'Count' : v.agg === 'sum' ? 'Sum' : 'Avg'
    return `${aggLabel} of ${v.label}`
  })
})

function getCellDisplay(row, rIndex, field) {
  const rawValue = row[field.field]
  let displayVal = rawValue
  if (
    displayVal === null ||
    displayVal === undefined ||
    displayVal === '' ||
    displayVal === 'null'
  ) {
    displayVal = 'NA'
  }

  if (field.repeatRowLabels === false && rIndex > 0) {
    const prevRow = displayRows.value[rIndex - 1]
    let prevVal = prevRow ? prevRow[field.field] : null
    if (prevVal === null || prevVal === undefined || prevVal === '' || prevVal === 'null') {
      prevVal = 'NA'
    }
    if (prevVal === displayVal) {
      return ''
    }
  }

  return displayVal
}

// Columns the result actually stores a value under: the pivot's own column
// keys, or the server's '' placeholder bucket when no field is in Columns.
const cellValue = (row, column) => props.result?.data?.[getRowKey(row)]?.[column]

/**
 * Totals are derived client-side from the result matrix, so the server never
 * needs to know about them. Each value field is totalled independently, keyed
 * by its alias, so a multi-value pivot does not collapse to a single figure.
 * avg aggregates average the visible cells instead of adding them — adding
 * averages is meaningless.
 */
const aggregate = (values, valueIndex) => {
  const valueKey = valueKeys.value[valueIndex]
  const numbers = values
    .map(cell => cell?.[valueKey])
    .filter(v => typeof v === 'number' && !Number.isNaN(v))
  if (numbers.length === 0) return '-'

  const total = numbers.reduce((sum, v) => sum + v, 0)
  return props.valueFields[valueIndex]?.agg === 'avg' ? total / numbers.length : total
}

const columnTotal = (column, valueIndex) =>
  aggregate(
    displayRows.value.map(row => cellValue(row, column)),
    valueIndex
  )
</script>

<template>
  <div class="flex h-full w-full flex-1 flex-col space-y-2">
    <!-- Empty state when no rows exist -->
    <div
      v-if="!hasRows"
      class="flex min-h-[400px] items-center justify-center rounded-xl border border-dashed border-gray-200 bg-white p-12 text-center text-xs text-gray-400"
    >
      Drag fields into Rows, Columns, or Values to build your custom pivot report.
    </div>

    <!-- Scrollable Table Container with Sticky Header -->
    <div
      v-else
      class="max-h-[640px] min-h-[480px] w-full flex-1 overflow-auto rounded-xl border border-gray-200 shadow-2xs"
    >
      <table class="w-full border-collapse text-left text-xs">
        <thead class="sticky top-0 z-20 bg-[#f8fafc]">
          <tr class="border-b border-gray-200 text-gray-700">
            <!-- Row Columns -->
            <th
              v-for="(field, fIdx) in rowColumns"
              :key="field.field"
              rowspan="2"
              class="border-r border-gray-200/60 bg-[#f8fafc] px-3.5 py-2.5 font-semibold whitespace-nowrap text-gray-700 last:border-r-0"
              :class="{ 'rounded-tl-xl': fIdx === 0 }"
            >
              {{ field.label }}
            </th>

            <!-- Column groups, each spanning one sub-column per value field -->
            <th
              v-for="(column, cIdx) in columnKeys"
              v-if="hasColumns"
              :key="`group-${column}`"
              :colspan="Math.max(valueCount, 1)"
              class="border-r border-gray-200/60 bg-[#f8fafc] px-3.5 py-2.5 text-right font-semibold whitespace-nowrap text-gray-700 last:border-r-0"
              :class="{ 'rounded-tr-xl': cIdx === columnKeys.length - 1 && !showTotals }"
            >
              {{ column }}
            </th>

            <!-- Without a column grouping the value labels are the top level -->
            <th
              v-for="(label, index) in valueLabels"
              v-if="!hasColumns && hasValues"
              :key="`value-${index}`"
              rowspan="2"
              class="border-r border-gray-200/60 bg-[#f8fafc] px-3.5 py-2.5 text-right font-semibold whitespace-nowrap text-gray-700 last:border-r-0"
              :class="{ 'rounded-tr-xl': index === valueLabels.length - 1 }"
            >
              {{ label }}
            </th>
          </tr>

          <!-- Sub-header: one cell per (column group x value field) -->
          <tr v-if="hasColumns" class="border-b border-gray-200 text-gray-700">
            <template v-for="column in columnKeys" :key="`sub-${column}`">
              <th
                v-for="(label, index) in valueLabels"
                :key="`sub-${column}-${index}`"
                class="rounded-tr-xl border-r border-gray-200/60 bg-[#f8fafc] px-3.5 py-2 text-right text-[11px] font-medium whitespace-nowrap text-gray-500 last:border-r-0"
              >
                {{ label }}
              </th>
            </template>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
          <tr
            v-for="(row, rIndex) in displayRows"
            :key="rIndex"
            class="transition-colors hover:bg-gray-50/70"
          >
            <!-- Row Columns -->
            <td
              v-for="field in rowColumns"
              :key="field.field"
              class="border-r border-gray-100 px-3.5 py-2.5 font-medium whitespace-nowrap text-gray-800 last:border-r-0"
            >
              {{ getCellDisplay(row, rIndex, field) }}
            </td>

            <!-- One cell per (column group x value field) -->
            <template v-if="hasColumns">
              <template v-for="column in columnKeys" :key="`cell-${column}`">
                <td
                  v-for="(label, index) in valueLabels"
                  :key="`cell-${column}-${index}`"
                  class="border-r border-gray-100 px-3.5 py-2.5 text-right whitespace-nowrap text-gray-700 last:border-r-0"
                >
                  {{ getValueForCell(row, column, index) }}
                </td>
              </template>
            </template>

            <td
              v-for="(label, index) in valueLabels"
              v-if="!hasColumns && hasValues"
              :key="`cell-value-${index}`"
              class="border-r border-gray-100 px-3.5 py-2.5 text-right whitespace-nowrap text-gray-700 last:border-r-0"
            >
              {{ getValueForCell(row, '', index) }}
            </td>
          </tr>
        </tbody>
        <tfoot v-if="showTotals" class="border-t-2 border-gray-200 bg-[#f8fafc]">
          <tr>
            <!-- Only label the row when the body has a leading label column to
                 sit in; otherwise the label would add a phantom column. -->
            <td
              v-if="rowColumns.length"
              :colspan="rowColumns.length"
              class="px-3.5 py-2.5 font-semibold whitespace-nowrap text-gray-800"
            >
              Total
            </td>
            <template v-if="hasColumns">
              <template v-for="column in columnKeys" :key="`total-${column}`">
                <td
                  v-for="(label, index) in valueLabels"
                  :key="`total-${column}-${index}`"
                  class="border-r border-gray-100 px-3.5 py-2.5 text-right font-semibold whitespace-nowrap text-gray-800 last:border-r-0"
                >
                  {{ columnTotal(column, index) }}
                </td>
              </template>
            </template>
            <td
              v-for="(label, index) in valueLabels"
              v-if="!hasColumns && hasValues"
              :key="`total-value-${index}`"
              class="rounded-br-xl border-r border-gray-100 px-3.5 py-2.5 text-right font-semibold whitespace-nowrap text-gray-800 last:border-r-0"
            >
              {{ columnTotal('', index) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Truncated Warning -->
    <div
      v-if="props.result?.meta?.truncated"
      class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"
    >
      Showing first {{ props.result.meta.returned_groups }} of
      {{ props.result.meta.total_groups }} groups (limit {{ props.result.meta.max_groups }}). Add
      filters or remove high-cardinality fields
      <template v-if="rowColumns.length">
        ({{ rowColumns.map(f => f.label).join(', ') }})
      </template>
      from Rows/Columns to see fewer groups. Results are truncated to prevent browser freeze.
    </div>

    <!-- Record Count Footer -->
    <div v-if="hasRows" class="flex items-center justify-between px-1 pt-1 text-xs text-gray-400">
      <div>
        Showing {{ totalRows
        }}{{ props.result?.meta?.total_groups ? ` of ${props.result.meta.total_groups}` : '' }}
        {{ totalRows === 1 ? 'record group' : 'record groups' }}
        <span v-if="props.result?.meta?.truncated" class="text-amber-600"> (truncated)</span>
      </div>
    </div>
  </div>
</template>
