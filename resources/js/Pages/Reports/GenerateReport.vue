<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import FiltersPanel from '@/components/FiltersPanel.vue'
import CustomPivotBuilder from '@/components/CustomPivotBuilder.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { cn } from '@/lib/utils'
import { ref, watch, computed } from 'vue'

import { Item, ItemContent, ItemDescription, ItemTitle } from '@/components/ui/item'
import {
  Download as DownloadIcon,
  ChevronRightIcon,
  BadgeCheckIcon,
  Upload as UploadIcon,
  Tag as TagIcon,
  FileText as FileTextIcon,
  Eye as EyeIcon,
  ArrowRight as ArrowRightIcon,
} from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { ButtonGroup } from '@/components/ui/button-group'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import axios from 'axios'
import { useAuth } from '@/composable/useAuth'

const { isCenterScoped } = useAuth()

const props = defineProps({
  filters: { type: Object, required: false },
})

const reportTypes = computed(() => {
  const fromProps = props.filters?.report_types
  const base =
    fromProps && fromProps.length
      ? fromProps.filter(type => type && type.value && type.label)
      : [
          { label: 'Random Generate Report', value: 'custom-pivot' },
          { label: 'Milk Feeding Programs Report', value: 'milk-feeding-programs' },
          { label: 'Supreme Template', value: 'export-template' },
        ]
  if (isCenterScoped.value) {
    return base.filter(t => t.value !== 'custom-pivot')
  }
  return base
})

const reportFilters = computed(() => {
  const fromProps = props.filters?.report_filter_elements
  return fromProps && Object.keys(fromProps).length
    ? fromProps
    : {
        'custom-pivot': [],
        'milk-feeding-programs': [
          'years',
          'fundedBy',
          'selectCenters',
          'modeOfProcurement',
          'suppliers',
          'psgcAddress',
        ],
        'export-template': ['templateFile'],
      }
})

const urlFilters = ref({})
const queueingExport = ref(false)
const downloadingTemplate = ref(false)

const handleFilterUpdate = filter => {
  urlFilters.value = { ...urlFilters.value, ...filter }
}

const selectedReport = ref(
  new window.URL(window.location.href).searchParams.get('filter[report_type]') || 'bdp'
)

// Hide Random Generate Report for center-admin users (center CLSU etc.)
if (isCenterScoped.value && selectedReport.value === 'custom-pivot') {
  selectedReport.value = reportTypes.value.find(t => t.value !== 'custom-pivot')?.value || 'bdp'
}

watch(selectedReport, newReport => {
  // Clear stale PSGC/location params immediately so the next report doesn't flash "All Regions"
  const url = new URL(window.location.href)
  ;['region', 'province', 'city', 'district'].forEach(k => url.searchParams.delete(`filter[${k}]`))
  window.history.replaceState(
    null,
    '',
    url.pathname + (url.search ? `?${url.searchParams.toString()}` : '')
  )

  router.get(
    window.location.pathname,
    { 'filter[report_type]': newReport },
    { preserveState: false, replace: true }
  )
})

// Read the selected template key directly from the URL (FiltersPanel keeps it there)
const selectedTemplateKey = computed(
  () => new window.URL(window.location.href).searchParams.get('filter[template_file]') || null
)

const handleDownloadTemplate = () => {
  const key = new window.URL(window.location.href).searchParams.get('filter[template_file]')
  if (!key || downloadingTemplate.value) return

  downloadingTemplate.value = true
  const downloadUrl = `/reports/template-download?key=${encodeURIComponent(key)}`
  const template = (props.filters?.templates || []).find(t => t.value === key)

  window.dispatchEvent(
    new window.CustomEvent('report-export-queued', {
      detail: {
        documentId: `local-template-${Date.now()}`,
        local: true,
        reportType: 'export-template',
        status: 'processed',
        fileName: template?.label || 'template',
        fileSize: 0,
        downloadUrl,
      },
    })
  )

  setTimeout(() => {
    downloadingTemplate.value = false
  }, 1500)
}

const handleDownloadAs = (format, exportType = null) => {
  queueReportExport(format, exportType)
}

const queueReportExport = async (format, exportType = null) => {
  if (queueingExport.value) return

  const currentUrl = new window.URL(window.location.href)
  const searchParams = new window.URLSearchParams(currentUrl.search)
  const filterParams = {}

  for (const [key, value] of searchParams.entries()) {
    if (key.startsWith('filter[')) {
      const cleanKey = key.replace('filter[', '').replace(']', '')
      filterParams[cleanKey] = value
    }
  }

  // Validate required fields — only for filters the report actually renders
  const elements = reportFilters.value[selectedReport.value] || []
  if (elements.includes('reportTemplate') && !filterParams.template) {
    alert('Template is required. Please select a template before exporting.')
    return
  }

  // Center is required when report exposes Center filter
  const needsCenter = (reportFilters.value[selectedReport.value] || []).includes('selectCenters')
  if (needsCenter && !filterParams.center_id) {
    alert('Center is required. Please select a center or All Centers before exporting.')
    return
  }

  // List and Figures is hidden for BDP (kept for future use) – skip validation for bdp
  if (
    selectedReport.value !== 'bdp' &&
    elements.includes('listAndFigures') &&
    !filterParams.list_and_figures
  ) {
    alert('List and Figures is required. Please select List or Figures before exporting.')
    return
  }

  queueingExport.value = true

  const params = Object.fromEntries(
    Object.entries({
      report_type: selectedReport.value,
      download_as: format,
      export_type: exportType,
      queue_export: true,
      ...filterParams,
    }).filter(([, v]) => v != null && v !== '')
  )

  try {
    const response = await axios.post('/reports/generate', params)
    window.dispatchEvent(
      new window.CustomEvent('report-export-queued', {
        detail: {
          documentId: response.data.document_id,
          reportType: selectedReport.value,
          format,
          exportType,
          status: 'processing',
          fileName: null,
          fileSize: 0,
          downloadUrl: null,
        },
      })
    )
  } catch (error) {
    console.error('Error queueing report:', error)
  } finally {
    queueingExport.value = false
  }
}
</script>

<template>
  <DashboardLayout>
    <Head title="Generate Report" />
    <div class="p-3 sm:p-4 md:p-6">
      <h1 class="mb-4 text-xl font-semibold sm:text-2xl">Generate Report</h1>

      <!-- Stack vertically on mobile, side-by-side grid on md+ -->
      <div class="flex flex-col gap-4 md:grid md:grid-cols-12">
        <!-- ── Report type selector ── -->
        <div class="flex flex-col gap-2 md:col-span-3 lg:col-span-2">
          <Item variant="outline">
            <ItemContent>
              <ItemTitle class="text-sm sm:text-base">Select Report Type</ItemTitle>
              <ItemDescription class="text-xs sm:text-sm">
                Choose the type of report you want to generate.
              </ItemDescription>
            </ItemContent>
          </Item>

          <div class="flex gap-2 overflow-x-auto pb-1 md:flex-col md:overflow-visible md:pb-0">
            <Item
              v-for="(type, index) in reportTypes"
              :key="type?.value || index"
              v-model="selectedReport"
              variant="outline"
              size="sm"
              as-child
              :class="
                cn(
                  'shrink-0 md:shrink',
                  selectedReport === type?.value
                    ? 'bg-deep-navy-blue text-white'
                    : 'hover:bg-gray-100'
                )
              "
              @click="type?.value && (selectedReport = type.value)"
            >
              <a href="#">
                <ItemMedia>
                  <BadgeCheckIcon class="size-4 sm:size-5" />
                </ItemMedia>
                <ItemContent>
                  <ItemTitle class="text-xs sm:text-sm">
                    {{ type?.label || 'Unknown' }}
                  </ItemTitle>
                </ItemContent>
                <ItemActions>
                  <ChevronRightIcon class="size-3 sm:size-4" />
                </ItemActions>
              </a>
            </Item>
          </div>
        </div>

        <!-- ── Random Generate or standard report filters ── -->
        <template v-if="selectedReport === 'custom-pivot' && !isCenterScoped">
          <div class="md:col-span-9 lg:col-span-10">
            <CustomPivotBuilder :sources="props.filters?.pivot_sources || []" />
          </div>
        </template>

        <Card v-else class="md:col-span-9 lg:col-span-10">
          <CardHeader class="pb-2 sm:pb-4">
            <CardTitle class="text-sm sm:text-base">Report Filters</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="selectedReport">
              <!-- ── Supreme Template: single unified section — dropdown first, instructions below ── -->
              <template v-if="selectedReport === 'export-template'">
                <div class="space-y-6">
                  <!-- Supreme Template dropdown at top -->
                  <div>
                    <h5 class="text-foreground mb-2 text-xs font-semibold tracking-wide sm:text-sm">
                      Supreme Template
                    </h5>
                    <FiltersPanel
                      :filters="filters"
                      :elements="reportFilters[selectedReport]"
                      @update:filter="handleFilterUpdate"
                    />
                    <div class="mt-3 flex flex-wrap gap-2">
                      <ButtonGroup :class="cn('text-black')">
                        <Button
                          variant="secondary"
                          prevent-default
                          class="text-xs sm:text-sm"
                          @click="handleDownloadTemplate"
                        >
                          {{ downloadingTemplate ? 'Queued…' : 'Export' }}
                        </Button>
                        <Button variant="secondary" class="px-2" @click="handleDownloadTemplate">
                          <DownloadIcon class="size-3.5 sm:size-4" />
                        </Button>
                      </ButtonGroup>
                    </div>
                  </div>

                  <!-- Divider before instructions -->
                  <div class="border-t border-gray-200" />

                  <!-- Template Instructions below export -->
                  <div>
                    <h4 class="text-deep-navy-blue text-sm font-semibold sm:text-[15px]">
                      Template Instructions
                    </h4>
                    <p class="text-muted-foreground mt-1 text-xs sm:text-sm">
                      Follow these steps to add and select a report template.
                    </p>
                  </div>

                  <!-- 4-step instructions -->
                  <ol class="relative space-y-0">
                    <div
                      class="absolute top-[14px] bottom-[14px] left-[15px] w-px bg-gray-200"
                      aria-hidden="true"
                    />
                    <li class="relative flex gap-3 pb-5">
                      <span
                        class="bg-deep-navy-blue relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full text-[11px] leading-none font-bold text-white ring-4 ring-white"
                      >
                        01
                      </span>
                      <div class="min-w-0 flex-1 pt-0.5">
                        <p class="flex items-center gap-1.5 text-xs font-semibold sm:text-sm">
                          <UploadIcon class="text-deep-navy-blue size-3.5" />
                          Upload File
                        </p>
                        <p class="text-muted-foreground mt-0.5 text-xs leading-relaxed">
                          Upload the Excel report template.
                        </p>
                      </div>
                    </li>
                    <li class="relative flex gap-3 pb-5">
                      <span
                        class="bg-deep-navy-blue relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full text-[11px] leading-none font-bold text-white ring-4 ring-white"
                      >
                        02
                      </span>
                      <div class="min-w-0 flex-1 pt-0.5">
                        <p class="flex items-center gap-1.5 text-xs font-semibold sm:text-sm">
                          <TagIcon class="text-deep-navy-blue size-3.5" />
                          Select Tag
                        </p>
                        <p class="text-muted-foreground mt-0.5 text-xs leading-relaxed">
                          Select <span class="text-foreground font-semibold">Template</span> as the
                          document tag.
                        </p>
                      </div>
                    </li>
                    <li class="relative flex gap-3 pb-5">
                      <span
                        class="bg-deep-navy-blue relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full text-[11px] leading-none font-bold text-white ring-4 ring-white"
                      >
                        03
                      </span>
                      <div class="min-w-0 flex-1 pt-0.5">
                        <p class="flex items-center gap-1.5 text-xs font-semibold sm:text-sm">
                          <FileTextIcon class="text-deep-navy-blue size-3.5" />
                          Add Metadata
                        </p>
                        <p class="text-muted-foreground mt-0.5 text-xs leading-relaxed">
                          Provide the required document information.
                        </p>
                      </div>
                    </li>
                    <li class="relative flex gap-3">
                      <span
                        class="bg-deep-navy-blue relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full text-[11px] leading-none font-bold text-white ring-4 ring-white"
                      >
                        04
                      </span>
                      <div class="min-w-0 flex-1 pt-0.5">
                        <p class="flex items-center gap-1.5 text-xs font-semibold sm:text-sm">
                          <EyeIcon class="text-deep-navy-blue size-3.5" />
                          Review &amp; Submit
                        </p>
                        <p class="text-muted-foreground mt-0.5 text-xs leading-relaxed">
                          Review the details and submit the document.
                        </p>
                      </div>
                    </li>
                  </ol>

                  <!-- CTA -->
                  <div class="flex justify-end">
                    <Link
                      href="/documents"
                      class="bg-deep-navy-blue focus-visible:ring-deep-navy-blue inline-flex w-full items-center justify-center gap-1.5 rounded-md px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-[#0a1e4a] focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none sm:w-auto"
                    >
                      Go to Documents
                      <ArrowRightIcon class="size-4" />
                    </Link>
                  </div>
                </div>
              </template>

              <!-- ── All other report types: single column ── -->
              <template v-else>
                <div class="mb-4 max-w-4xl">
                  <h3 class="mb-3 text-sm font-medium sm:text-base">
                    {{
                      reportTypes.find(r => r.value === selectedReport)?.label ||
                      'Please select a report type'
                    }}
                  </h3>
                  <FiltersPanel
                    class="mb-4"
                    :filters="filters"
                    :elements="reportFilters[selectedReport]"
                    @update:filter="handleFilterUpdate"
                  />
                </div>

                <div class="flex flex-wrap gap-2">
                  <template v-if="selectedReport === 'milk-feeding-programs'">
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <ButtonGroup :class="cn('text-black')" prevent-default>
                          <Button variant="secondary" prevent-default class="text-xs sm:text-sm">
                            Export Raw
                          </Button>
                          <Button variant="secondary" class="px-2">
                            <DownloadIcon class="size-3.5 sm:size-4" />
                          </Button>
                        </ButtonGroup>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent>
                        <DropdownMenuGroup>
                          <DropdownMenuItem @click="handleDownloadAs('xlsx', 'raw')">
                            Download as xlsx
                          </DropdownMenuItem>
                          <DropdownMenuItem @click="handleDownloadAs('csv', 'raw')">
                            Download as csv
                          </DropdownMenuItem>
                          <DropdownMenuItem @click="handleDownloadAs('pdf', 'raw')">
                            Download as pdf
                          </DropdownMenuItem>
                        </DropdownMenuGroup>
                      </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <ButtonGroup :class="cn('text-black')" prevent-default>
                          <Button variant="secondary" prevent-default class="text-xs sm:text-sm">
                            Export Summary
                          </Button>
                          <Button variant="secondary" class="px-2">
                            <DownloadIcon class="size-3.5 sm:size-4" />
                          </Button>
                        </ButtonGroup>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent>
                        <DropdownMenuGroup>
                          <DropdownMenuItem @click="handleDownloadAs('xlsx', 'summary')">
                            Download as xlsx
                          </DropdownMenuItem>
                          <DropdownMenuItem @click="handleDownloadAs('csv', 'summary')">
                            Download as csv
                          </DropdownMenuItem>
                          <DropdownMenuItem @click="handleDownloadAs('pdf', 'summary')">
                            Download as pdf
                          </DropdownMenuItem>
                        </DropdownMenuGroup>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </template>

                  <template v-else>
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <ButtonGroup :class="cn('text-black')" prevent-default>
                          <Button variant="secondary" prevent-default class="text-xs sm:text-sm">
                            Export
                          </Button>
                          <Button variant="secondary" class="px-2">
                            <DownloadIcon class="size-3.5 sm:size-4" />
                          </Button>
                        </ButtonGroup>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent>
                        <DropdownMenuGroup>
                          <DropdownMenuItem @click="handleDownloadAs('xlsx')">
                            Download as xlsx
                          </DropdownMenuItem>
                          <DropdownMenuItem @click="handleDownloadAs('csv')">
                            Download as csv
                          </DropdownMenuItem>
                          <DropdownMenuItem @click="handleDownloadAs('pdf')">
                            Download as pdf
                          </DropdownMenuItem>
                        </DropdownMenuGroup>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </template>
                </div>
              </template>
            </div>

            <div v-else class="text-sm text-gray-500">
              Please select a report type to see available filters.
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </DashboardLayout>
</template>
