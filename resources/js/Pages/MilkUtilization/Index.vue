<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import { toast } from 'vue-sonner'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth } from '@/composable/useAuth'
import MilkUtilizationToolbar from './Partials/MilkUtilizationToolbar.vue'
import MilkUtilizationTable from './Partials/MilkUtilizationTable.vue'
import MilkUtilizationEmptyState from './Partials/MilkUtilizationEmptyState.vue'

const props = defineProps({
  options: { type: Object, default: () => ({}) },
})

const { hasPermission } = useAuth()

const SECTIONS = [
  { key: 'institutional', label: 'Institutional Herd and Gene Pool' },
  { key: 'cbed', label: 'CBED Assisted and Impact Zone' },
]

const ALL_CENTERS = 'all'
const REPORT_MONTH = 12
const route = window.route

const ENDPOINT = '/reports/milk-utilization/data'

const now = new Date()

const selection = reactive({
  year: null,
  month: REPORT_MONTH,
  center: ALL_CENTERS,
})

const activeSectionKey = ref('institutional')
const payloads = reactive({ institutional: null, cbed: null })
const errors = reactive({ institutional: null, cbed: null })
const loading = ref(false)
const expandedKeys = ref([])
const queueing = ref(false)
const lastOptions = ref(null)

const canExport = computed(() => hasPermission('export-data'))

const activeSection = computed(() => payloads[activeSectionKey.value])
const activeError = computed(() => errors[activeSectionKey.value])

const options = computed(
  () => activeSection.value?.options ?? lastOptions.value ?? props.options ?? {}
)

const columns = computed(() => activeSection.value?.columns ?? [])
const groupHeaders = computed(() => activeSection.value?.group_headers ?? [])
const coverageLabel = computed(() => activeSection.value?.filters?.coverage_label ?? '')
const hasRows = computed(() => (activeSection.value?.sections?.[0]?.rows ?? []).length > 0)

const years = computed(() => options.value.years ?? [])
const centers = computed(() => options.value.centers ?? [])

const periodLabel = computed(() => String(selection.year ?? ''))

let bootstrapDone = false

const requestParams = sectionKey => ({
  year: selection.year,
  month: REPORT_MONTH,
  section: sectionKey,

  center_id: selection.center === ALL_CENTERS ? undefined : Number(selection.center),
})

const fetchSection = async sectionKey => {
  loading.value = true
  errors[sectionKey] = null

  try {
    const { data } = await axios.get(ENDPOINT, { params: requestParams(sectionKey) })

    payloads[sectionKey] = data
    lastOptions.value = data.options ?? lastOptions.value

    if (await applyServerDefaults(data, sectionKey)) {
      return fetchSection(sectionKey)
    }
  } catch (error) {
    errors[sectionKey] = error

    toast.error('Could not load milk utilization.', {
      description: error.response?.status === 422 ? 'The selected period is not valid.' : undefined,
    })
  } finally {
    loading.value = false
  }
}

const applyServerDefaults = async (data, sectionKey) => {
  const period = data?.options?.default_period
  const availableYears = data?.options?.years ?? []

  if (!period || sectionKey !== 'institutional') {
    bootstrapDone = true

    return false
  }

  let refetch = false

  if (!bootstrapDone) {
    bootstrapDone = true

    const yearHasData = availableYears.some(
      option => Number(option.value) === Number(selection.year)
    )

    if (
      !yearHasData &&
      availableYears.length &&
      Number(availableYears[0].value) !== Number(selection.year)
    ) {
      selection.year = Number(availableYears[0].value)
      refetch = true
    }
  }

  if (refetch) {
    syncUrl()
  }

  return refetch
}

const syncUrl = () => {
  const url = new window.URL(window.location.href)

  url.searchParams.set('filter[year]', String(selection.year))
  url.searchParams.delete('filter[month]')

  if (selection.center === ALL_CENTERS) {
    url.searchParams.delete('filter[center_id]')
  } else {
    url.searchParams.set('filter[center_id]', String(selection.center))
  }

  window.history.replaceState({}, '', url)
}

const handleSelection = async patch => {
  Object.assign(selection, patch, { month: REPORT_MONTH })

  syncUrl()

  SECTIONS.forEach(item => {
    if (item.key !== activeSectionKey.value) {
      payloads[item.key] = null
    }
  })

  await fetchSection(activeSectionKey.value)
}

const toggleGroup = rowKey => {
  expandedKeys.value = expandedKeys.value.includes(rowKey)
    ? expandedKeys.value.filter(key => key !== rowKey)
    : [...expandedKeys.value, rowKey]
}

const handleTabChange = sectionKey => {
  activeSectionKey.value = sectionKey

  const url = new window.URL(window.location.href)
  url.searchParams.set('section', sectionKey)
  window.history.replaceState({}, '', url)

  if (!payloads[sectionKey]) {
    fetchSection(sectionKey)
  }
}

const queueExport = async format => {
  if (queueing.value) return

  if (!selection.year) {
    toast.error('Select a year before exporting.')

    return
  }

  queueing.value = true

  try {
    const response = await axios.post(route('reports.milk-utilization.export'), {
      format: format,
      queue_export: true,
      year: selection.year,
      month: REPORT_MONTH,
      section: activeSectionKey.value,
      center_id: selection.center === ALL_CENTERS ? undefined : Number(selection.center),
    })

    window.dispatchEvent(
      new window.CustomEvent('report-export-queued', {
        detail: {
          documentId: response.data.document_id,
          reportType: 'milk-utilization',
          format: format,
          status: 'processing',
          fileName: null,
          fileSize: 0,
          downloadUrl: null,
        },
      })
    )
  } catch {
    toast.error('Could not queue the export. Please try again.')
  } finally {
    queueing.value = false
  }
}

onMounted(async () => {
  const params = new window.URLSearchParams(window.location.search)
  const urlYear = Number(params.get('filter[year]'))
  const urlCenter = params.get('filter[center_id]')
  const urlSection = params.get('section')

  selection.year = urlYear || props.options?.default_period?.year || now.getFullYear()
  selection.month = REPORT_MONTH
  selection.center = urlCenter || ALL_CENTERS

  if (urlSection && (urlSection === 'institutional' || urlSection === 'cbed')) {
    activeSectionKey.value = urlSection
  }

  bootstrapDone = Boolean(urlYear)
  syncUrl()

  await fetchSection(activeSectionKey.value)
})
</script>

<template>
  <Head title="Milk Utilization" />
  <DashboardLayout>
    <div class="flex flex-col gap-6 rounded-lg bg-white px-6 py-12 text-black shadow-sm">
      <div>
        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Milk Utilization</h1>
        <p class="text-sm text-slate-600 sm:text-base">
          Where the milk went: produced, fed to calves, processed, sold raw, and spoiled. Volumes
          cover January through December of the selected year.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Filters</CardTitle>
        </CardHeader>

        <CardContent>
          <MilkUtilizationToolbar
            :years="years"
            :centers="centers"
            :model-value="selection"
            :disabled="loading"
            :can-export="canExport"
            :queueing="queueing"
            @update:model-value="handleSelection"
            @export="queueExport"
          />
        </CardContent>
      </Card>

      <div class="flex flex-col gap-4">
        <Tabs
          :model-value="activeSectionKey"
          class="flex flex-col gap-4"
          @update:model-value="handleTabChange"
        >
          <TabsList>
            <TabsTrigger
              v-for="item in SECTIONS"
              :key="item.key"
              :value="item.key"
              class="px-3 text-xs sm:text-sm"
            >
              {{ item.label }}
            </TabsTrigger>
          </TabsList>

          <TabsContent :value="activeSectionKey" class="mt-0">
            <div class="flex flex-col gap-4">
              <p v-if="periodLabel" class="text-sm font-medium text-slate-700">
                {{ periodLabel }}
              </p>

              <template v-if="activeError">
                <MilkUtilizationEmptyState
                  variant="error"
                  :message="
                    activeError.response?.data?.message ||
                    'Something went wrong while loading this report.'
                  "
                  retryable
                  @retry="fetchSection(activeSectionKey)"
                />
              </template>

              <template v-else-if="loading && !activeSection">
                <div class="rounded-lg border border-slate-200 p-4">
                  <span class="sr-only" role="status">Loading milk utilization…</span>
                  <div class="flex flex-col gap-2">
                    <Skeleton v-for="row in 8" :key="row" class="h-6 w-full" />
                  </div>
                </div>
              </template>

              <template v-else-if="!hasRows">
                <MilkUtilizationEmptyState
                  variant="empty"
                  :message="`No milk utilization records for ${periodLabel}. Select another year or center to view available data.`"
                />
              </template>

              <template v-else>
                <MilkUtilizationTable
                  :section="activeSection.sections[0]"
                  :columns="columns"
                  :group-headers="groupHeaders"
                  :warnings="activeSection.warnings ?? []"
                  :expanded-keys="expandedKeys"
                  :loading="loading"
                  :coverage-label="coverageLabel"
                  :year="selection.year"
                  :month="selection.month"
                  :program="activeSectionKey === 'institutional' ? 'ih' : 'cbed'"
                  @toggle-group="toggleGroup"
                  @refresh="fetchSection(activeSectionKey)"
                />
              </template>
            </div>
          </TabsContent>
        </Tabs>
      </div>
    </div>
  </DashboardLayout>
</template>
