<script setup>
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { computed, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useAuth } from '@/composable/useAuth'
import { cn } from '@/lib/utils'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { ButtonGroup } from '@/components/ui/button-group'
import { Check, Download as DownloadIcon, Pencil, X } from 'lucide-vue-next'
import axios from 'axios'
import { toast } from 'vue-sonner'

const props = defineProps({
  provinces: { type: Array, required: true },
  years: { type: Array, required: true },
  inputData: { type: Object, required: true },
  computedData: { type: Object, required: true },
})

const { hasPermission, canExportData, canExportOwnCenter } = useAuth()
const canManage = computed(() => hasPermission('manage-pl480'))
const canExport = computed(() => canExportData.value || canExportOwnCenter.value)

const route = window.route
const queueingExport = ref(false)

const selectedWithoutYears = ref(new Set())
const selectedWithYears = ref(new Set())

watch(
  () => props.years,
  years => {
    if (selectedWithoutYears.value.size === 0 || selectedWithYears.value.size === 0) {
      const sortedYears = [...years].map(Number).sort((a, b) => a - b)
      // Default selection should focus on the most recent 6 years:
      // - first 3 => "Without PL480"
      // - last 3  => "With PL480"
      const lastSixYears = sortedYears.slice(-6)
      const withoutDefault = lastSixYears.slice(0, 3)
      const withDefault = lastSixYears.slice(3)

      if (selectedWithoutYears.value.size === 0)
        selectedWithoutYears.value = new Set(withoutDefault)
      if (selectedWithYears.value.size === 0) selectedWithYears.value = new Set(withDefault)
      return
    }
  },
  { immediate: true }
)

const visibleWithoutPl480Years = computed(() =>
  props.years.filter(y => selectedWithoutYears.value.has(y))
)
const visibleWithPl480Years = computed(() =>
  props.years.filter(y => selectedWithYears.value.has(y))
)

const toggleWithoutYear = year => {
  const next = new Set(selectedWithoutYears.value)
  if (next.has(year)) next.delete(year)
  else next.add(year)
  selectedWithoutYears.value = next
}

const toggleWithYear = year => {
  const next = new Set(selectedWithYears.value)
  if (next.has(year)) next.delete(year)
  else next.add(year)
  selectedWithYears.value = next
}

const resetWithoutYearFilters = () => {
  selectedWithoutYears.value = new Set(props.years)
}

const resetWithYearFilters = () => {
  selectedWithYears.value = new Set(props.years)
}

const draftInputData = reactive({})
const editingCell = ref(null)
const isSaving = ref(false)

watch(
  () => props.inputData,
  newValue => {
    for (const province of props.provinces) {
      draftInputData[province] = draftInputData[province] || {}
      for (const year of props.years) {
        draftInputData[province][year] = newValue?.[province]?.[year] ?? 0
      }
    }
  },
  { immediate: true }
)

const startEdit = (province, year) => {
  if (!canManage.value || isSaving.value) return
  editingCell.value = { province, year, value: draftInputData[province][year] }
}

const cancelEdit = () => {
  if (!isSaving.value) editingCell.value = null
}

const isEditing = (province, year) =>
  editingCell.value?.province === province && editingCell.value?.year === year

const formatNumber = value => {
  const numberValue = Number(value ?? 0)
  if (!Number.isFinite(numberValue)) return '0'
  const rounded = Math.round(numberValue * 100) / 100
  return rounded.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

const withoutTotals = computed(() => {
  const totals = {}
  for (const year of visibleWithoutPl480Years.value) {
    totals[year] = props.provinces.reduce((sum, province) => {
      return sum + Number(props.computedData?.[province]?.[year] ?? 0)
    }, 0)
  }
  return totals
})

const inputTotals = computed(() => {
  const totals = {}
  for (const year of visibleWithPl480Years.value) {
    totals[year] = props.provinces.reduce((sum, province) => {
      const raw =
        draftInputData?.[province]?.[year] === '' ? 0 : (draftInputData?.[province]?.[year] ?? 0)
      return sum + Number(raw ?? 0)
    }, 0)
  }
  return totals
})

const computedTotals = computed(() => {
  const totals = {}
  for (const year of visibleWithPl480Years.value) {
    totals[year] = props.provinces.reduce((sum, province) => {
      return sum + Number(props.computedData?.[province]?.[year] ?? 0)
    }, 0)
  }
  return totals
})

const saveEdit = () => {
  if (!editingCell.value || isSaving.value || !canManage.value) return

  const { province, year, value } = editingCell.value
  const numericValue = value === '' ? 0 : Number(value)
  if (!Number.isFinite(numericValue) || numericValue < 0) {
    toast.error('Enter a valid production value of zero or more.')
    return
  }

  isSaving.value = true
  router.post(
    route('pl480.inputs.upsert'),
    { province, year: Number(year), value: numericValue },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        draftInputData[province][year] = numericValue
        editingCell.value = null
        toast.success('Value updated successfully')
      },
      onError: errors => {
        toast.error('Failed to update value', {
          description: Object.values(errors)[0],
        })
      },
      onFinish: () => {
        isSaving.value = false
      },
    }
  )
}

const handleExportPL480 = async format => {
  if (queueingExport.value) return
  queueingExport.value = true

  try {
    const withoutYears = Array.from(selectedWithoutYears.value)
    const withYears = Array.from(selectedWithYears.value)

    const response = await axios.post(route('pl480.export'), {
      format: format,
      queue_export: true,
      without_years: withoutYears,
      with_years: withYears,
    })

    window.dispatchEvent(
      new window.CustomEvent('report-export-queued', {
        detail: {
          documentId: response.data.document_id,
          reportType: 'pl480',
          format,
          status: 'processing',
          fileName: null,
          fileSize: 0,
          downloadUrl: null,
        },
      })
    )
  } catch (error) {
    console.error('Export failed:', error)
    toast.error('Unable to queue export right now.', {
      description: error.response?.data?.message,
    })
  } finally {
    queueingExport.value = false
  }
}
</script>

<template>
  <div
    class="space-y-4 rounded-lg bg-white px-4 py-6 text-black shadow-sm sm:space-y-6 sm:px-6 sm:py-12"
  >
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">PL480 Milk Production</h1>
        <p class="text-sm text-slate-600 sm:text-base">
          View and manage milk production data with and without PL480 intervention.
        </p>
      </div>
    </div>

    <div class="space-y-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="outline">
                Without PL480 Years
                <span class="ml-2 text-xs text-slate-600">({{ selectedWithoutYears.size }})</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-64 bg-white">
              <DropdownMenuLabel>ACTUAL MILK PRODUCTION (W/OUT PL480)</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <div class="max-h-64 overflow-y-auto pr-1">
                <DropdownMenuCheckboxItem
                  v-for="year in props.years"
                  :key="`without-${year}`"
                  :model-value="selectedWithoutYears.has(year)"
                  @update:model-value="() => toggleWithoutYear(year)"
                  @select.prevent
                >
                  {{ year }}
                </DropdownMenuCheckboxItem>
              </div>
              <DropdownMenuSeparator />
              <div class="flex items-center justify-between gap-2 px-2 py-2">
                <Button size="sm" variant="outline" @click="resetWithoutYearFilters">Reset</Button>
                <div class="text-xs text-slate-600">{{ selectedWithoutYears.size }} selected</div>
              </div>
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="outline">
                With PL480 Years
                <span class="ml-2 text-xs text-slate-600">({{ selectedWithYears.size }})</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-64 bg-white">
              <DropdownMenuLabel>WITH PL480 PROJECT (INPUT DATA)</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <div class="max-h-64 overflow-y-auto pr-1">
                <DropdownMenuCheckboxItem
                  v-for="year in props.years"
                  :key="`with-${year}`"
                  :model-value="selectedWithYears.has(year)"
                  @update:model-value="() => toggleWithYear(year)"
                  @select.prevent
                >
                  {{ year }}
                </DropdownMenuCheckboxItem>
              </div>
              <DropdownMenuSeparator />
              <div class="flex items-center justify-between gap-2 px-2 py-2">
                <Button size="sm" variant="outline" @click="resetWithYearFilters">Reset</Button>
                <div class="text-xs text-slate-600">{{ selectedWithYears.size }} selected</div>
              </div>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        <div v-if="canExport" class="flex gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <ButtonGroup :class="cn('text-black')" prevent-default>
                <Button
                  variant="secondary"
                  prevent-default
                  class="text-xs sm:text-sm"
                  :disabled="queueingExport"
                >
                  Export
                </Button>
                <Button variant="secondary" class="px-2" :disabled="queueingExport">
                  <DownloadIcon class="size-3.5 sm:size-4" />
                </Button>
              </ButtonGroup>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
              <DropdownMenuGroup>
                <DropdownMenuItem @click="handleExportPL480('xlsx')">
                  Download as xlsx
                </DropdownMenuItem>
                <DropdownMenuItem @click="handleExportPL480('csv')">
                  Download as csv
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <div class="w-full overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <div class="min-w-[800px] sm:min-w-full">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="bg-slate-50 font-semibold text-slate-700" rowspan="2"
                  >Province</TableHead
                >
                <TableHead
                  class="bg-slate-50 text-center font-semibold text-slate-700"
                  :colspan="visibleWithoutPl480Years.length || 1"
                >
                  ACTUAL MILK PRODUCTION W/OUT PL480 INTERVENTION
                </TableHead>
                <TableHead
                  class="bg-slate-50 text-center font-semibold text-slate-700"
                  :colspan="visibleWithPl480Years.length || 1"
                >
                  WITH PL480 PROJECT (INPUT DATA)
                </TableHead>
              </TableRow>
              <TableRow>
                <TableHead
                  v-for="year in visibleWithoutPl480Years"
                  :key="year"
                  class="bg-slate-50 text-center font-semibold text-slate-700"
                >
                  {{ year }}
                </TableHead>
                <TableHead
                  v-if="visibleWithoutPl480Years.length === 0"
                  class="bg-slate-50 text-center font-semibold text-slate-400"
                >
                  —
                </TableHead>
                <TableHead
                  v-for="year in visibleWithPl480Years"
                  :key="year"
                  class="bg-slate-50 text-center font-semibold text-slate-700"
                >
                  {{ year }}
                </TableHead>
                <TableHead
                  v-if="visibleWithPl480Years.length === 0"
                  class="bg-slate-50 text-center font-semibold text-slate-400"
                >
                  —
                </TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              <TableRow
                v-for="province in props.provinces"
                :key="province"
                class="border-none transition-colors hover:bg-slate-50"
              >
                <TableCell class="font-medium text-slate-900">{{ province }}</TableCell>

                <TableCell
                  v-for="year in visibleWithoutPl480Years"
                  :key="year"
                  class="text-center text-slate-700"
                >
                  {{ formatNumber(props.computedData?.[province]?.[year] ?? 0) }}
                </TableCell>
                <TableCell
                  v-if="visibleWithoutPl480Years.length === 0"
                  class="text-center text-slate-400"
                  >—</TableCell
                >

                <TableCell
                  v-for="year in visibleWithPl480Years"
                  :key="year"
                  class="text-center text-slate-700"
                >
                  <div
                    v-if="isEditing(province, year)"
                    class="flex items-center justify-center gap-1"
                  >
                    <Input
                      v-model="editingCell.value"
                      class="h-7 w-24 text-right text-sm"
                      inputmode="decimal"
                      type="number"
                      min="0"
                      step="0.01"
                      :aria-label="`Milk production for ${province}, ${year}`"
                      :disabled="isSaving"
                      @keydown.enter="saveEdit"
                      @keydown.esc="cancelEdit"
                    />
                    <Button
                      size="sm"
                      variant="ghost"
                      class="h-7 w-7 p-0"
                      :disabled="isSaving"
                      aria-label="Save value"
                      @click="saveEdit"
                    >
                      <Check class="h-4 w-4" />
                    </Button>
                    <Button
                      size="sm"
                      variant="ghost"
                      class="h-7 w-7 p-0"
                      :disabled="isSaving"
                      aria-label="Cancel edit"
                      @click="cancelEdit"
                    >
                      <X class="h-4 w-4" />
                    </Button>
                  </div>
                  <button
                    v-else-if="canManage"
                    type="button"
                    class="focus-visible:ring-ring inline-flex cursor-pointer items-center gap-1 rounded px-1 hover:bg-slate-100 focus-visible:ring-2 focus-visible:outline-none"
                    :disabled="isSaving"
                    :aria-label="`Edit milk production for ${province}, ${year}`"
                    title="Click to edit"
                    @click="startEdit(province, year)"
                  >
                    {{ formatNumber(draftInputData?.[province]?.[year] ?? 0) }}
                    <Pencil class="h-3 w-3 text-slate-400" aria-hidden="true" />
                  </button>
                  <span v-else class="text-sm text-slate-700">{{
                    formatNumber(draftInputData?.[province]?.[year] ?? 0)
                  }}</span>
                </TableCell>
                <TableCell
                  v-if="visibleWithPl480Years.length === 0"
                  class="text-center text-slate-400"
                  >—</TableCell
                >
              </TableRow>

              <TableRow class="font-semibold">
                <TableCell class="text-slate-900">TOTAL</TableCell>
                <TableCell
                  v-for="year in visibleWithoutPl480Years"
                  :key="`input-total-${year}`"
                  class="text-center text-slate-900"
                >
                  {{ formatNumber(withoutTotals[year]) }}
                </TableCell>
                <TableCell
                  v-if="visibleWithoutPl480Years.length === 0"
                  class="text-center text-slate-400"
                  >—</TableCell
                >
                <TableCell
                  v-for="year in visibleWithPl480Years"
                  :key="`input-total-${year}`"
                  class="text-center text-slate-900"
                >
                  {{ formatNumber(inputTotals[year]) }}
                </TableCell>
                <TableCell
                  v-if="visibleWithPl480Years.length === 0"
                  class="text-center text-slate-400"
                  >—</TableCell
                >
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </div>
    </div>

    <div class="space-y-4">
      <div class="w-full overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <div class="min-w-[800px] sm:min-w-full">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="bg-slate-50 font-semibold text-slate-700" rowspan="2"
                  >Province</TableHead
                >
                <TableHead
                  class="bg-slate-50 text-center font-semibold text-slate-700"
                  :colspan="visibleWithoutPl480Years.length || 1"
                >
                  ACTUAL MILK PRODUCTION W/OUT PL480 INTERVENTION
                </TableHead>
                <TableHead
                  class="bg-slate-50 text-center font-semibold text-slate-700"
                  :colspan="visibleWithPl480Years.length || 1"
                >
                  WITH PL480 PROJECT (COMPUTE MILK PRODUCTION DATA BASED FROM MILK 4DX REPORT)
                </TableHead>
              </TableRow>
              <TableRow>
                <TableHead
                  v-for="year in visibleWithoutPl480Years"
                  :key="year"
                  class="bg-slate-50 text-center font-semibold text-slate-700"
                >
                  {{ year }}
                </TableHead>
                <TableHead
                  v-if="visibleWithoutPl480Years.length === 0"
                  class="bg-slate-50 text-center font-semibold text-slate-400"
                >
                  —
                </TableHead>
                <TableHead
                  v-for="year in visibleWithPl480Years"
                  :key="year"
                  class="bg-slate-50 text-center font-semibold text-slate-700"
                >
                  {{ year }}
                </TableHead>
                <TableHead
                  v-if="visibleWithPl480Years.length === 0"
                  class="bg-slate-50 text-center font-semibold text-slate-400"
                >
                  —
                </TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              <TableRow
                v-for="province in props.provinces"
                :key="province"
                class="border-none transition-colors hover:bg-slate-50"
              >
                <TableCell class="font-medium text-slate-900">{{ province }}</TableCell>
                <TableCell
                  v-for="year in visibleWithoutPl480Years"
                  :key="year"
                  class="text-center text-slate-700"
                >
                  {{ formatNumber(props.computedData?.[province]?.[year] ?? 0) }}
                </TableCell>
                <TableCell
                  v-if="visibleWithoutPl480Years.length === 0"
                  class="text-center text-slate-400"
                  >—</TableCell
                >
                <TableCell
                  v-for="year in visibleWithPl480Years"
                  :key="year"
                  class="text-center text-slate-700"
                >
                  {{ formatNumber(props.computedData?.[province]?.[year] ?? 0) }}
                </TableCell>
                <TableCell
                  v-if="visibleWithPl480Years.length === 0"
                  class="text-center text-slate-400"
                  >—</TableCell
                >
              </TableRow>

              <TableRow class="font-semibold">
                <TableCell class="text-slate-900">TOTAL</TableCell>
                <TableCell
                  v-for="year in visibleWithoutPl480Years"
                  :key="`computed-total-${year}`"
                  class="text-center text-slate-900"
                >
                  {{ formatNumber(withoutTotals[year]) }}
                </TableCell>
                <TableCell
                  v-if="visibleWithoutPl480Years.length === 0"
                  class="text-center text-slate-400"
                  >—</TableCell
                >
                <TableCell
                  v-for="year in visibleWithPl480Years"
                  :key="`computed-total-${year}`"
                  class="text-center text-slate-900"
                >
                  {{ formatNumber(computedTotals[year]) }}
                </TableCell>
                <TableCell
                  v-if="visibleWithPl480Years.length === 0"
                  class="text-center text-slate-400"
                  >—</TableCell
                >
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </div>
    </div>
  </div>
</template>
