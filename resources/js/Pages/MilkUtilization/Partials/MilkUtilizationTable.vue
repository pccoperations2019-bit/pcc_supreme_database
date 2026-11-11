<script setup>
import { computed, ref } from 'vue'
import { Icon } from '@iconify/vue'
import axios from 'axios'
import { toast } from 'vue-sonner'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'

const formatMilkValue = (value, column) => {
  const numeric = Number(value)
  const missing = value === null || value === undefined || value === '' || Number.isNaN(numeric)
  const decimals = column.key === 'lactating_animals' ? 0 : (column.decimals ?? 2)
  const formatted = (missing ? 0 : numeric).toLocaleString('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })

  if (column.unit === 'PHP') return `₱${formatted}`
  if (column.unit === '%') return `${formatted}%`
  if (column.unit === 'kg') return `${formatted} ${column.unit}`
  return formatted
}

const props = defineProps({
  section: { type: Object, required: true },
  columns: { type: Array, default: () => [] },
  groupHeaders: { type: Array, default: () => [] },
  warnings: { type: Array, default: () => [] },
  expandedKeys: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  coverageLabel: { type: String, default: '' },
  year: { type: Number, required: true },
  month: { type: Number, required: true },
  program: { type: String, required: true },
})

const emit = defineEmits(['toggle-group', 'refresh'])

const editableColumns = computed(() =>
  props.columns.filter(
    col => col.key !== 'production' && (col.kind === 'key_input' || col.key === 'lactating_animals')
  )
)

const editingCell = ref(null)
const saving = ref(false)

const startEdit = (rowKey, columnKey, currentValue) => {
  editingCell.value = {
    rowKey,
    columnKey,
    value: currentValue === null ? '' : currentValue,
  }
}

const cancelEdit = () => {
  editingCell.value = null
}

const saveEdit = async () => {
  if (!editingCell.value || saving.value) return

  const { rowKey, columnKey, value } = editingCell.value
  if (
    columnKey === 'lactating_animals' &&
    value !== '' &&
    (!Number.isInteger(Number(value)) || Number(value) < 0)
  ) {
    toast.error('Enter a whole number of lactating animals, zero or more.')
    return
  }

  saving.value = true

  try {
    const parts = rowKey.split('-')
    const centerCode = parts[0]
    const partnerId = parts.length > 1 ? parseInt(parts[1]) : null

    const payload = {
      year: props.year,
      month: props.month,
      program: props.program,
      center_code: centerCode,
      partner_id: partnerId,
      notes: null,
    }

    if (columnKey === 'lactating_animals') {
      payload.lactating_animals = value === '' ? null : Number(value)
    } else if (columnKey === 'production') {
      payload.production = value === '' ? null : parseFloat(value)
    } else if (columnKey === 'received') {
      payload.received = value === '' ? null : parseFloat(value)
    } else if (columnKey === 'fed_to_calves') {
      payload.fed_to_calves = value === '' ? null : parseFloat(value)
    } else if (columnKey === 'value_added_products') {
      payload.value_added_products = value === '' ? null : parseFloat(value)
    } else if (columnKey === 'milk_feeding') {
      payload.milk_feeding = value === '' ? null : parseFloat(value)
    }

    await axios.post('/reports/milk-utilization/monthly-summary', payload)

    toast.success('Value updated successfully')
    editingCell.value = null
    emit('refresh')
  } catch (error) {
    toast.error('Failed to update value', {
      description: error.response?.data?.message || error.message,
    })
  } finally {
    saving.value = false
  }
}

const valueColumns = computed(() => props.columns.filter(column => column.in_values !== false))
const labelColumn = computed(
  () => props.columns.find(column => column.in_values === false) ?? props.columns[0] ?? null
)

const bandsAreValid = computed(
  () =>
    props.groupHeaders.length > 0 &&
    props.groupHeaders.reduce((total, band) => total + (Number(band.colspan) || 0), 0) ===
      props.columns.length
)

const groups = computed(() =>
  (props.section.rows ?? []).map(row => ({
    row,
    children: props.expandedKeys.includes(row.key) ? (row.children ?? []) : [],
  }))
)

const isEmphasis = column => column.kind === 'headline' || column.color_key === 'headline'

const valueCellClass = column =>
  [
    'px-2 py-1.5 text-right text-sm whitespace-nowrap tabular-nums',
    isEmphasis(column) ? 'font-bold text-slate-900' : 'text-slate-700',
  ].join(' ')

const isExpanded = rowKey => props.expandedKeys.includes(rowKey)
const groupId = row => `milk-utilization-${props.section.key}-${row.key}`
const isEditable = column => editableColumns.value.some(col => col.key === column.key)
const isEditing = (rowKey, columnKey) =>
  editingCell.value &&
  editingCell.value.rowKey === rowKey &&
  editingCell.value.columnKey === columnKey
</script>

<template>
  <div>
    <div class="rounded-t-lg bg-slate-900 px-4 py-3 text-white">
      <p class="text-xs font-semibold tracking-wide uppercase">
        {{ section.marker }}
      </p>
      <h2 class="text-sm font-bold sm:text-base">{{ section.title }}</h2>
      <p v-if="section.note" class="text-xs text-white/80">{{ section.note }}</p>
    </div>

    <p class="bg-slate-50 px-4 py-2 text-xs text-slate-700 sm:text-sm">{{ coverageLabel }}</p>
    <p class="px-1 pt-2 text-xs text-slate-500 md:hidden">Scroll sideways to see all columns.</p>

    <div
      class="border-x border-t border-slate-200"
      :class="loading ? 'pointer-events-none opacity-60' : ''"
    >
      <Table class="min-w-max">
        <TableCaption class="sr-only">{{ section.title }} — {{ coverageLabel }}</TableCaption>

        <TableHeader>
          <template v-if="bandsAreValid">
            <TableRow class="hover:bg-transparent">
              <TableHead
                v-for="band in groupHeaders"
                :key="band.start"
                :colspan="band.colspan > 1 ? band.colspan : undefined"
                :rowspan="band.start === 0 && band.colspan === 1 ? 2 : undefined"
                scope="col"
                class="sticky top-0 h-10 border border-slate-300 bg-slate-100 px-2 text-center text-xs font-semibold tracking-wide whitespace-nowrap text-slate-800 uppercase"
                :class="band.start === 0 ? 'left-0 z-40' : 'z-30'"
              >
                {{ band.label }}
              </TableHead>
            </TableRow>

            <TableRow class="hover:bg-transparent">
              <TableHead
                class="sticky top-0 left-0 z-40 h-10 border border-slate-300 bg-slate-50 px-3 text-left text-xs font-semibold whitespace-nowrap text-slate-700 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.15)]"
              >
                {{ labelColumn?.label }}
              </TableHead>

              <TableHead
                v-for="column in valueColumns"
                :key="column.key"
                :title="column.formula"
                scope="col"
                class="sticky top-10 z-30 h-10 border border-slate-300 bg-slate-50 px-2 text-xs font-semibold whitespace-nowrap text-slate-700"
                :class="isEmphasis(column) ? 'bg-slate-100 text-slate-900' : ''"
              >
                {{ column.label }}
              </TableHead>
            </TableRow>
          </template>

          <TableRow v-else class="hover:bg-transparent">
            <TableHead
              v-for="column in columns"
              :key="column.key ?? column.letter"
              :title="column.formula"
              scope="col"
              class="h-10 border border-slate-300 bg-slate-50 px-2 text-xs font-semibold whitespace-nowrap text-slate-700"
              :class="[
                column.in_values === false ? 'sticky top-0 left-0 z-40' : 'sticky top-0 z-30',
                isEmphasis(column) ? 'bg-slate-100 text-slate-900' : '',
              ]"
            >
              {{ column.label }}
            </TableHead>
          </TableRow>
        </TableHeader>

        <TableBody v-for="group in groups" :id="groupId(group.row)" :key="group.row.key">
          <TableRow>
            <TableCell
              scope="row"
              class="sticky left-0 z-20 min-w-[10rem] border-r border-slate-200 bg-white px-3 py-1.5 text-left text-sm font-medium whitespace-nowrap text-slate-900 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.15)]"
            >
              <button
                v-if="group.row.has_children"
                type="button"
                class="hover:text-deep-navy-blue focus-visible:ring-ring inline-flex cursor-pointer items-center gap-1 rounded text-left focus-visible:ring-2 focus-visible:outline-none"
                :aria-expanded="isExpanded(group.row.key)"
                :aria-controls="groupId(group.row)"
                @click="emit('toggle-group', group.row.key)"
              >
                <Icon
                  :icon="isExpanded(group.row.key) ? 'lucide:chevron-down' : 'lucide:chevron-right'"
                  class="h-4 w-4 shrink-0"
                  aria-hidden="true"
                />
                <span>{{ group.row.label }}</span>
                <span class="sr-only">
                  {{ isExpanded(group.row.key) ? 'Collapse enterprises' : 'Expand enterprises' }}
                </span>
              </button>
              <template v-else>
                <span>{{ group.row.label }}</span>
                <span v-if="group.row.is_genepool" class="ml-2 text-xs text-slate-500 uppercase">
                  Gene pool
                </span>
              </template>
            </TableCell>

            <TableCell
              v-for="column in valueColumns"
              :key="column.key"
              :class="valueCellClass(column)"
            >
              <template v-if="isEditable(column) && group.row.kind !== 'total'">
                <div
                  v-if="isEditing(group.row.key, column.key)"
                  class="flex items-center justify-end gap-1"
                >
                  <Input
                    :model-value="editingCell.value"
                    type="number"
                    :step="column.key === 'lactating_animals' ? 1 : 0.01"
                    :min="column.key === 'lactating_animals' ? 0 : undefined"
                    :inputmode="column.key === 'lactating_animals' ? 'numeric' : 'decimal'"
                    class="h-7 w-24 text-right text-sm"
                    @update:model-value="editingCell.value = $event"
                    @keydown.enter="saveEdit(group.row)"
                    @keydown.esc="cancelEdit"
                  />
                  <Button
                    size="sm"
                    variant="ghost"
                    class="h-7 w-7 p-0"
                    :disabled="saving"
                    @click="saveEdit(group.row)"
                  >
                    <Icon icon="lucide:check" class="h-4 w-4" />
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    class="h-7 w-7 p-0"
                    :disabled="saving"
                    @click="cancelEdit"
                  >
                    <Icon icon="lucide:x" class="h-4 w-4" />
                  </Button>
                </div>
                <div
                  v-else
                  class="cursor-pointer rounded px-1 hover:bg-slate-100"
                  :title="column.kind === 'key_input' ? 'Click to edit' : 'Click to edit'"
                  @click="startEdit(group.row.key, column.key, group.row.values[column.key])"
                >
                  {{ formatMilkValue(group.row.values[column.key], column) }}
                  <Icon icon="lucide:pencil" class="ml-1 inline h-3 w-3 text-slate-400" />
                </div>
              </template>

              <template v-else>
                {{ formatMilkValue(group.row.values[column.key], column) }}
              </template>
            </TableCell>
          </TableRow>

          <TableRow v-for="child in group.children" :key="child.key">
            <TableCell
              scope="row"
              class="sticky left-0 z-20 min-w-[10rem] border-r border-slate-200 bg-white py-1.5 pr-3 pl-8 text-left text-sm whitespace-nowrap text-slate-600 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.15)]"
            >
              {{ child.label }}
            </TableCell>

            <TableCell
              v-for="column in valueColumns"
              :key="column.key"
              :class="valueCellClass(column)"
            >
              <template v-if="isEditable(column)">
                <div
                  v-if="isEditing(child.key, column.key)"
                  class="flex items-center justify-end gap-1"
                >
                  <Input
                    :model-value="editingCell.value"
                    type="number"
                    :step="column.key === 'lactating_animals' ? 1 : 0.01"
                    :min="column.key === 'lactating_animals' ? 0 : undefined"
                    :inputmode="column.key === 'lactating_animals' ? 'numeric' : 'decimal'"
                    class="h-7 w-24 text-right text-sm"
                    @update:model-value="editingCell.value = $event"
                    @keydown.enter="saveEdit(child)"
                    @keydown.esc="cancelEdit"
                  />
                  <Button
                    size="sm"
                    variant="ghost"
                    class="h-7 w-7 p-0"
                    :disabled="saving"
                    @click="saveEdit(child)"
                  >
                    <Icon icon="lucide:check" class="h-4 w-4" />
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    class="h-7 w-7 p-0"
                    :disabled="saving"
                    @click="cancelEdit"
                  >
                    <Icon icon="lucide:x" class="h-4 w-4" />
                  </Button>
                </div>
                <div
                  v-else
                  class="cursor-pointer rounded px-1 hover:bg-slate-100"
                  title="Click to edit"
                  @click="startEdit(child.key, column.key, child.values[column.key])"
                >
                  {{ formatMilkValue(child.values[column.key], column) }}
                  <Icon icon="lucide:pencil" class="ml-1 inline h-3 w-3 text-slate-400" />
                </div>
              </template>

              <template v-else>
                {{ formatMilkValue(child.values[column.key], column) }}
              </template>
            </TableCell>
          </TableRow>
        </TableBody>

        <TableBody v-if="section.total">
          <TableRow class="border-t-2 border-slate-400">
            <TableCell
              scope="row"
              class="sticky left-0 z-20 min-w-[10rem] border-r border-slate-200 bg-slate-100 px-3 py-1.5 text-left text-sm font-bold whitespace-nowrap text-slate-900 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.15)]"
            >
              {{ section.total.label }}
            </TableCell>

            <TableCell
              v-for="column in valueColumns"
              :key="column.key"
              :class="[
                'bg-slate-100 px-2 py-1.5 text-right text-sm font-bold whitespace-nowrap text-slate-900 tabular-nums',
              ]"
            >
              {{ formatMilkValue(section.total.values[column.key], column) }}
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  </div>
</template>
