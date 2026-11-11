<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { formatMetricValue as formatMetricValueHelper } from '@/lib/utils'
import { VisDonut, VisSingleContainer } from '@unovis/vue'
import TrendIndicator from '@/components/TrendIndicator.vue'

/**
 * PCC SUPREME CBED Dashboard Props
 * CBED: Carabao-based Enterprise Development
 * Following PCC's data warehousing requirements for business intelligence
 */
const props = defineProps({
  // CBED Core Metrics - Farmer Cooperative Support
  totalFcas: { type: Number, default: 0 },
  totalFarmers: { type: Number, default: 0 },
  totalAnimals: { type: Number, default: 0 },
  cowsInMilkLine: { type: Number, default: 0 },
  productionSupportServices: { type: Number, default: 0 },
  marketDevelopmentServices: { type: Number, default: 0 },
  iecClients: { type: Number, default: 0 },
  clientsTrained: { type: Number, default: 0 },

  // Production Metrics - IH (Institutions with Carabao Herds)
  totalMilkVolume: { type: Number, default: 0 }, // liters - primary PCC KPI
  totalMeatProduced: { type: Number, default: 0 }, // kgs
  totalManureProduced: { type: Number, default: 0 }, // kgs
  totalMilkValue: { type: Number, default: 0 }, // PHP
  totalMeatValue: { type: Number, default: 0 }, // PHP
  totalManureValue: { type: Number, default: 0 }, // PHP

  // Previous-period totals for year-over-year trend comparison,
  // keyed by the metric value keys (e.g. totalMilkVolume)
  previousTotals: { type: Object, default: () => ({}) },
  selectedProductionYear: { type: Number, default: null },

  // Milk Feeding Program Partnerships - Government Agencies
  depedTotalBeneficiaries: { type: Number, default: 0 },
  depedMilkPacksDistributed: { type: Number, default: 0 },
  depedNoOfSdosEngaged: { type: Number, default: 0 },
  depedNoOfCooperativesEngaged: { type: Number, default: 0 },
  depedGrossIncome: { type: Number, default: 0 },

  dswdTotalBeneficiaries: { type: Number, default: 0 },
  dswdMilkPacksDistributed: { type: Number, default: 0 },
  dswdNoOfLgusEngaged: { type: Number, default: 0 },
  dswdNoOfCooperativesEngaged: { type: Number, default: 0 },
  dswdGrossIncome: { type: Number, default: 0 },

  // Other Fund Source Programs - LGU and LDS
  lguTotalBeneficiaries: { type: Number, default: 0 },
  lguMilkPacksDistributed: { type: Number, default: 0 },
  lguNoOfLgusEngaged: { type: Number, default: 0 },
  lguNoOfCooperativesEngaged: { type: Number, default: 0 },
  lguGrossIncome: { type: Number, default: 0 },

  ldsTotalBeneficiaries: { type: Number, default: 0 },
  ldsMilkPacksDistributed: { type: Number, default: 0 },
  ldsNoOfLgusEngaged: { type: Number, default: 0 },
  ldsNoOfCooperativesEngaged: { type: Number, default: 0 },
  ldsGrossIncome: { type: Number, default: 0 },

  // Enterprise Development Types - CBED Business Intelligence
  processingEnterprises: { type: Number, default: 0 },
  marketEnterprises: { type: Number, default: 0 },
  dairyBoxEnterprises: { type: Number, default: 0 },
  knpEnterprises: { type: Number, default: 0 },

  // Compliance Metrics - Regulatory Requirements
  csoCompliance: { type: Number, default: 0 },
  fdaLtoCompliance: { type: Number, default: 0 },
  ndaLtoCompliance: { type: Number, default: 0 },
  rsbsaCompliance: { type: Number, default: 0 },
  ffedisCompliance: { type: Number, default: 0 },

  // Business & Compliance Documents
  businessDocumentsCompliance: { type: Number, default: 0 },
  fdaCertificationsCompliance: { type: Number, default: 0 },
  ffeisRegistrationCompliance: { type: Number, default: 0 },
  isoCompliance: { type: Number, default: 0 },
  ndaCompliance: { type: Number, default: 0 },
  doleCompliance: { type: Number, default: 0 },
  cdaCompliance: { type: Number, default: 0 },
  secCompliance: { type: Number, default: 0 },

  // Operational Documents
  processingCompliance: { type: Number, default: 0 },
  marketCompliance: { type: Number, default: 0 },
  dairyBoxCompliance: { type: Number, default: 0 },
  knpCompliance: { type: Number, default: 0 },

  // Production Goals (center-specific annual targets)
  productionGoalMilk: { type: Number, default: 0 },
  productionGoalMeat: { type: Number, default: 0 },
  productionGoalManure: { type: Number, default: 0 },

  // CBED-only milk total for Production Breakdown donut
  totalMilkVolumeCbed: { type: Number, default: 0 },

  // Flag to show breakdown only for center-admin
  isCenterAdmin: { type: Boolean, default: false },
})

/**
 * Reactive metric values computed from props
 * Ensures real-time updates when parent Dashboard filters change
 * Supporting PCC's multi-tenancy and data warehousing architecture
 */
const metricValues = computed(() => ({
  // CBED Summary KPIs
  totalFcas: props.totalFcas || 0,
  totalFarmers: props.totalFarmers || 0,
  totalAnimals: props.totalAnimals || 0,
  cowsInMilkLine: props.cowsInMilkLine || 0,
  productionSupportServices: props.productionSupportServices || 0,
  marketDevelopmentServices: props.marketDevelopmentServices || 0,
  iecClients: props.iecClients || 0,
  clientsTrained: props.clientsTrained || 0,

  // Production Metrics - Buffalo-derived products
  totalMilkVolume: props.totalMilkVolume || 0,
  totalMeatProduced: props.totalMeatProduced || 0,
  totalManureProduced: props.totalManureProduced || 0,
  totalMilkValue: props.totalMilkValue || 0,
  totalMeatValue: props.totalMeatValue || 0,
  totalManureValue: props.totalManureValue || 0,

  // Government Partnership Programs
  depedTotalBeneficiaries: props.depedTotalBeneficiaries || 0,
  depedMilkPacksDistributed: props.depedMilkPacksDistributed || 0,
  depedNoOfSdosEngaged: props.depedNoOfSdosEngaged || 0,
  depedNoOfCooperativesEngaged: props.depedNoOfCooperativesEngaged || 0,
  depedGrossIncome: props.depedGrossIncome || 0,

  dswdTotalBeneficiaries: props.dswdTotalBeneficiaries || 0,
  dswdMilkPacksDistributed: props.dswdMilkPacksDistributed || 0,
  dswdNoOfLgusEngaged: props.dswdNoOfLgusEngaged || 0,
  dswdNoOfCooperativesEngaged: props.dswdNoOfCooperativesEngaged || 0,
  dswdGrossIncome: props.dswdGrossIncome || 0,

  // Other Fund Source Programs - LGU and LDS
  lguTotalBeneficiaries: props.lguTotalBeneficiaries || 0,
  lguMilkPacksDistributed: props.lguMilkPacksDistributed || 0,
  lguNoOfLgusEngaged: props.lguNoOfLgusEngaged || 0,
  lguNoOfCooperativesEngaged: props.lguNoOfCooperativesEngaged || 0,
  lguGrossIncome: props.lguGrossIncome || 0,

  ldsTotalBeneficiaries: props.ldsTotalBeneficiaries || 0,
  ldsMilkPacksDistributed: props.ldsMilkPacksDistributed || 0,
  ldsNoOfLgusEngaged: props.ldsNoOfLgusEngaged || 0,
  ldsNoOfCooperativesEngaged: props.ldsNoOfCooperativesEngaged || 0,
  ldsGrossIncome: props.ldsGrossIncome || 0,

  // Combined Other Fund Source Programs (LGU + LDS)
  otherTotalBeneficiaries: (props.lguTotalBeneficiaries || 0) + (props.ldsTotalBeneficiaries || 0),
  otherMilkPacksDistributed:
    (props.lguMilkPacksDistributed || 0) + (props.ldsMilkPacksDistributed || 0),
  otherNoOfLgusEngaged: (props.lguNoOfLgusEngaged || 0) + (props.ldsNoOfLgusEngaged || 0),
  otherNoOfCooperativesEngaged:
    (props.lguNoOfCooperativesEngaged || 0) + (props.ldsNoOfCooperativesEngaged || 0),
  otherGrossIncome: (props.lguGrossIncome || 0) + (props.ldsGrossIncome || 0),

  // Enterprise Development Categories
  processingEnterprises: props.processingEnterprises || 0,
  marketEnterprises: props.marketEnterprises || 0,
  dairyBoxEnterprises: props.dairyBoxEnterprises || 0,
  knpEnterprises: props.knpEnterprises || 0,

  // Regulatory Compliance Status
  csoCompliance: props.csoCompliance || 0,
  fdaLtoCompliance: props.fdaLtoCompliance || 0,
  ndaLtoCompliance: props.ndaLtoCompliance || 0,
  rsbsaCompliance: props.rsbsaCompliance || 0,
  ffedisCompliance: props.ffedisCompliance || 0,

  // Business & Compliance Documents
  businessDocumentsCompliance: props.businessDocumentsCompliance || 0,
  fdaCertificationsCompliance: props.fdaCertificationsCompliance || 0,
  ffeisRegistrationCompliance: props.ffeisRegistrationCompliance || 0,
  isoCompliance: props.isoCompliance || 0,
  ndaCompliance: props.ndaCompliance || 0,
  doleCompliance: props.doleCompliance || 0,
  cdaCompliance: props.cdaCompliance || 0,
  secCompliance: props.secCompliance || 0,

  // Operational Documents
  processingCompliance: props.processingCompliance || 0,
  marketCompliance: props.marketCompliance || 0,
  dairyBoxCompliance: props.dairyBoxCompliance || 0,
  knpCompliance: props.knpCompliance || 0,
}))

// Donut chart data for Production Breakdown (center-admin only)
const milkDonutData = computed(() => [
  { label: 'Accomplished', value: Math.max(props.totalMilkVolumeCbed || 0, 0) },
  {
    label: 'Remaining',
    value: Math.max(0, (props.productionGoalMilk || 0) - (props.totalMilkVolumeCbed || 0)),
  },
])

const meatDonutData = computed(() => [
  { label: 'Accomplished', value: Math.max(props.totalMeatProduced || 0, 0) },
  {
    label: 'Remaining',
    value: Math.max(0, (props.productionGoalMeat || 0) - (props.totalMeatProduced || 0)),
  },
])

const manureDonutData = computed(() => [
  { label: 'Accomplished', value: Math.max(props.totalManureProduced || 0, 0) },
  {
    label: 'Remaining',
    value: Math.max(0, (props.productionGoalManure || 0) - (props.totalManureProduced || 0)),
  },
])

const formatKg = value =>
  `${Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })} kg`

const milkPercent = computed(() => {
  if (!props.productionGoalMilk) return 0
  return Math.min((props.totalMilkVolumeCbed / props.productionGoalMilk) * 100, 100)
})
const meatPercent = computed(() => {
  if (!props.productionGoalMeat) return 0
  return Math.min((props.totalMeatProduced / props.productionGoalMeat) * 100, 100)
})
const manurePercent = computed(() => {
  if (!props.productionGoalManure) return 0
  return Math.min((props.totalManureProduced / props.productionGoalManure) * 100, 100)
})

// CBED Dashboard Configuration - Business Intelligence Metrics
const metricCardsCBEDSummary = [
  {
    title: 'FCAs',
    description: 'Farmer Cooperatives enrolled in CBED program',
    valueKey: 'totalFcas',
    metricUnit: '',
  },
  {
    title: 'Farmers',
    description: 'Individual farmers participating in CBED',
    valueKey: 'totalFarmers',
    metricUnit: '',
  },
  {
    title: 'Animal Inventory',
    description: 'Total carabao population in CBED program',
    valueKey: 'totalAnimals',
    metricUnit: '',
  },
  {
    title: 'Cows on the Milk Line',
    description: 'Active milking carabao in production',
    valueKey: 'cowsInMilkLine',
    metricUnit: '',
  },
  {
    title: 'Production Support Services',
    description: 'Technical assistance services provided',
    valueKey: 'productionSupportServices',
    metricUnit: '',
  },
  {
    title: 'Market Development Services',
    description: 'Market linkage and development support',
    valueKey: 'marketDevelopmentServices',
    metricUnit: '',
  },
  {
    title: 'IEC Clients',
    description: 'Information, Education & Communication recipients',
    valueKey: 'iecClients',
    metricUnit: '',
  },
  {
    title: 'Clients Trained',
    description: 'Farmers trained in carabao management',
    valueKey: 'clientsTrained',
    metricUnit: '',
  },
]

// Production Metrics Configuration - Sustainability Indicators
const metricSummaryIHinKG = [
  {
    title: 'Total Milk',
    description: 'Milk production across all PCC centers',
    valueKey: 'totalMilkVolume',
    metricUnit: 'kg',
  },
  {
    title: 'Total Meat',
    description: 'Meat production from carabao livestock',
    valueKey: 'totalMeatProduced',
    metricUnit: 'kg',
  },
  {
    title: 'Total Manure',
    description: 'Organic fertilizer from carabao waste',
    valueKey: 'totalManureProduced',
    metricUnit: 'kg',
  },
]

const metricSummaryIHinPHP = [
  {
    title: 'Total Milk Value',
    description: 'Economic value of milk production',
    valueKey: 'totalMilkValue',
    metricUnit: 'PHP',
  },
  {
    title: 'Total Meat Value',
    description: 'Economic value of meat production',
    valueKey: 'totalMeatValue',
    metricUnit: 'PHP',
  },
  {
    title: 'Total Manure Value',
    description: 'Economic value of organic fertilizer',
    valueKey: 'totalManureValue',
    metricUnit: 'PHP',
  },
]

const previousPeriodLabel = computed(() =>
  props.selectedProductionYear ? String(props.selectedProductionYear - 1) : 'previous year'
)

// Government Partnership Programs - Multi-agency Collaboration
const metricMilkFeedingProgramDeped = [
  {
    title: 'Total Beneficiaries',
    description: 'Students receiving milk through DepEd program',
    valueKey: 'depedTotalBeneficiaries',
    metricUnit: '',
  },
  {
    title: 'Milk Packs Distributed',
    description: 'UHT milk packs distributed to schools',
    valueKey: 'depedMilkPacksDistributed',
    metricUnit: '',
  },
  {
    title: 'Schools Division Offices (SDOs)',
    description: 'DepEd regional offices participating',
    valueKey: 'depedNoOfSdosEngaged',
    metricUnit: '',
  },
  {
    title: 'Cooperatives Engaged',
    description: 'Farmer cooperatives supplying to DepEd',
    valueKey: 'depedNoOfCooperativesEngaged',
    metricUnit: '',
  },
  {
    title: 'Gross Income',
    description: 'Revenue generated from DepEd partnership',
    valueKey: 'depedGrossIncome',
    metricUnit: 'PHP',
  },
]

const metricMilkFeedingProgramDSWD = [
  {
    title: 'Total Beneficiaries',
    description: 'Children receiving milk through DSWD program',
    valueKey: 'dswdTotalBeneficiaries',
    metricUnit: '',
  },
  {
    title: 'Milk Packs Distributed',
    description: 'UHT milk packs for supplementary feeding',
    valueKey: 'dswdMilkPacksDistributed',
    metricUnit: '',
  },
  {
    title: 'Local Government Units (LGUs)',
    description: 'Municipal/City LGUs implementing program',
    valueKey: 'dswdNoOfLgusEngaged',
    metricUnit: '',
  },
  {
    title: 'Cooperatives Engaged',
    description: 'Farmer cooperatives supplying to DSWD',
    valueKey: 'dswdNoOfCooperativesEngaged',
    metricUnit: '',
  },
  {
    title: 'Gross Income',
    description: 'Revenue generated from DSWD partnership',
    valueKey: 'dswdGrossIncome',
    metricUnit: 'PHP',
  },
]

// Other Fund Source Programs - LGU and LDS combined
const metricMilkFeedingProgramOther = [
  {
    title: 'Total Beneficiaries',
    description: 'Beneficiaries from LGU and LDS programs',
    valueKey: 'otherTotalBeneficiaries',
    metricUnit: '',
  },
  {
    title: 'Milk Packs Distributed',
    description: 'Milk packs from LGU and LDS programs',
    valueKey: 'otherMilkPacksDistributed',
    metricUnit: '',
  },
  {
    title: 'Local Government Units (LGUs)',
    description: 'LGUs engaged in other fund source programs',
    valueKey: 'otherNoOfLgusEngaged',
    metricUnit: '',
  },
  {
    title: 'Cooperatives Engaged',
    description: 'Cooperatives in LGU and LDS programs',
    valueKey: 'otherNoOfCooperativesEngaged',
    metricUnit: '',
  },
  {
    title: 'Gross Income',
    description: 'Revenue from LGU and LDS partnerships',
    valueKey: 'otherGrossIncome',
    metricUnit: 'PHP',
  },
]

// Enterprise Development Categories - CBED Business Types
const CBEBenterpricesMetric = [
  {
    title: 'Processing',
    description: 'Operational processing enterprises',
    valueKey: 'processingEnterprises',
    metricUnit: '',
  },
  {
    title: 'Marketing',
    description: 'Operational marketing enterprises',
    valueKey: 'marketEnterprises',
    metricUnit: '',
  },
  {
    title: 'Dairy Box',
    description: 'Operational dairy box enterprises',
    valueKey: 'dairyBoxEnterprises',
    metricUnit: '',
  },
  {
    title: 'KNP',
    description: 'Dairy boxes integrated with Kadiwa ni Pangulo',
    valueKey: 'knpEnterprises',
    metricUnit: '',
  },
]

// Regulatory Compliance Metrics - Government Requirements
const CBEDcomplianceMetric = [
  {
    title: 'CSO',
    description: 'Cooperative Service Office compliance',
    valueKey: 'csoCompliance',
    metricUnit: '',
  },
  {
    title: 'FDA-LTO',
    description: 'Food and Drug Administration License to Operate',
    valueKey: 'fdaLtoCompliance',
    metricUnit: '',
  },
  {
    title: 'NDA-LTO',
    description: 'National Dairy Authority License to Operate',
    valueKey: 'ndaLtoCompliance',
    metricUnit: '',
  },
  {
    title: 'FFEDIS',
    description: 'Food and Feed Establishment and Distribution System',
    valueKey: 'ffedisCompliance',
    metricUnit: '',
  },
  {
    title: 'RSBSA',
    description: 'Registry System for Basic Sectors in Agriculture',
    valueKey: 'rsbsaCompliance',
    metricUnit: '',
  },
]

const page = usePage()
const currencySymbols = computed(() => page.props.currencySymbols || {})

const formatMetricValue = (valueKey, unit = '') =>
  formatMetricValueHelper(metricValues.value[valueKey], unit, currencySymbols.value)
</script>
<template>
  <div class="px-2 sm:px-0">
    <slot name="body">
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <Card v-for="card in metricCardsCBEDSummary" :key="card.title">
          <CardContent
            class="flex flex-col items-center justify-center space-y-1 px-1 pt-3 pb-2 text-center"
          >
            <div class="w-full truncate text-sm font-bold sm:text-lg lg:text-2xl">
              {{ formatMetricValue(card.valueKey, card.metricUnit) }}
            </div>
            <div class="text-[8px] font-semibold tracking-tight text-gray-500 uppercase sm:text-xs">
              {{ card.title }}
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="mt-6">
        <Card class="mb-4">
          <CardHeader class="px-4 text-center">
            <CardTitle class="text-lg sm:text-xl">Buffalo-Derived Products</CardTitle>
            <CardDescription class="text-xs sm:text-sm">
              Production metrics from PCC institutions with carabao herds
            </CardDescription>
          </CardHeader>
          <CardContent class="px-3 pb-6 sm:px-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div
                v-for="item in metricSummaryIHinKG"
                :key="item.title"
                class="flex flex-col items-center rounded-lg bg-gray-50 p-2 sm:p-4"
              >
                <div class="w-full truncate text-center text-sm font-bold sm:text-lg lg:text-2xl">
                  {{ formatMetricValue(item.valueKey, item.metricUnit) }}
                </div>
                <div
                  class="text-[8px] font-semibold tracking-tight text-gray-500 uppercase sm:text-xs"
                >
                  {{ item.title }}
                </div>
                <TrendIndicator
                  :current="metricValues[item.valueKey]"
                  :previous="previousTotals[item.valueKey] ?? 0"
                  :unit="item.metricUnit"
                  :label="item.title"
                  :period-label="previousPeriodLabel"
                />
              </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div
                v-for="item in metricSummaryIHinPHP"
                :key="item.title"
                class="flex flex-col items-center rounded-lg bg-gray-50 p-2 sm:p-4"
              >
                <div class="w-full truncate text-center text-sm font-bold sm:text-lg lg:text-2xl">
                  {{ formatMetricValue(item.valueKey, item.metricUnit) }}
                </div>
                <div
                  class="text-[8px] font-semibold tracking-tight text-gray-500 uppercase sm:text-xs"
                >
                  {{ item.title }}
                </div>
                <TrendIndicator
                  :current="metricValues[item.valueKey]"
                  :previous="previousTotals[item.valueKey] ?? 0"
                  :unit="item.metricUnit"
                  :label="item.title"
                  :period-label="previousPeriodLabel"
                />
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Production Breakdown: Accomplishment vs Target (center-admin only) -->
        <Card v-if="isCenterAdmin" class="mb-4">
          <CardHeader class="px-4 text-center">
            <CardTitle class="text-lg sm:text-xl">Production Breakdown</CardTitle>
            <CardDescription class="text-xs sm:text-sm">
              Accomplishment vs. annual target for buffalo-derived products
            </CardDescription>
          </CardHeader>
          <CardContent class="px-3 pb-6 sm:px-6">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
              <!-- Milk Donut -->
              <div class="flex flex-col items-center">
                <p
                  class="mb-1 text-xs font-semibold tracking-tight text-gray-500 uppercase sm:text-sm"
                >
                  Total Milk (kg)
                </p>
                <div class="relative mx-auto w-full max-w-[200px]">
                  <VisSingleContainer
                    :data="milkDonutData"
                    :margin="{ top: 20, bottom: 20 }"
                    class="h-44 w-full"
                  >
                    <VisDonut
                      :value="d => d.value"
                      :color="d => (d.label === 'Accomplished' ? '#3b82f6' : '#ef4444')"
                      :arc-width="28"
                    />
                  </VisSingleContainer>
                  <div
                    class="pointer-events-none absolute inset-0 flex items-center justify-center"
                  >
                    <div class="flex flex-col items-center justify-center">
                      <span class="text-base font-bold text-blue-600"
                        >{{ milkPercent.toFixed(1) }}%</span
                      >
                    </div>
                  </div>
                </div>
                <div class="mt-2 flex justify-center gap-3 text-xs font-medium">
                  <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-4 rounded-sm bg-blue-500" />Accomplished
                  </span>
                  <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-4 rounded-sm bg-red-500" />Remaining
                  </span>
                </div>
                <div class="mt-2 space-y-0.5 text-center text-xs text-gray-500">
                  <p>
                    Target:
                    <span class="font-semibold text-gray-700">{{
                      formatKg(productionGoalMilk)
                    }}</span>
                  </p>
                  <p>
                    Accomplished:
                    <span class="font-semibold text-blue-600">{{
                      formatKg(totalMilkVolumeCbed)
                    }}</span>
                  </p>
                </div>
              </div>

              <!-- Meat Donut -->
              <div class="flex flex-col items-center">
                <p
                  class="mb-1 text-xs font-semibold tracking-tight text-gray-500 uppercase sm:text-sm"
                >
                  Total Meat (kg)
                </p>
                <div class="relative mx-auto w-full max-w-[200px]">
                  <VisSingleContainer
                    :data="meatDonutData"
                    :margin="{ top: 20, bottom: 20 }"
                    class="h-44 w-full"
                  >
                    <VisDonut
                      :value="d => d.value"
                      :color="d => (d.label === 'Accomplished' ? '#f59e0b' : '#ef4444')"
                      :arc-width="28"
                    />
                  </VisSingleContainer>
                  <div
                    class="pointer-events-none absolute inset-0 flex items-center justify-center"
                  >
                    <div class="flex flex-col items-center justify-center">
                      <span class="text-base font-bold text-amber-500"
                        >{{ meatPercent.toFixed(1) }}%</span
                      >
                    </div>
                  </div>
                </div>
                <div class="mt-2 flex justify-center gap-3 text-xs font-medium">
                  <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-4 rounded-sm bg-amber-400" />Accomplished
                  </span>
                  <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-4 rounded-sm bg-red-500" />Remaining
                  </span>
                </div>
                <div class="mt-2 space-y-0.5 text-center text-xs text-gray-500">
                  <p>
                    Target:
                    <span class="font-semibold text-gray-700">{{
                      formatKg(productionGoalMeat)
                    }}</span>
                  </p>
                  <p>
                    Accomplished:
                    <span class="font-semibold text-amber-500">{{
                      formatKg(totalMeatProduced)
                    }}</span>
                  </p>
                </div>
              </div>

              <!-- Manure Donut -->
              <div class="flex flex-col items-center">
                <p
                  class="mb-1 text-xs font-semibold tracking-tight text-gray-500 uppercase sm:text-sm"
                >
                  Total Manure (kg)
                </p>
                <div class="relative mx-auto w-full max-w-[200px]">
                  <VisSingleContainer
                    :data="manureDonutData"
                    :margin="{ top: 20, bottom: 20 }"
                    class="h-44 w-full"
                  >
                    <VisDonut
                      :value="d => d.value"
                      :color="d => (d.label === 'Accomplished' ? '#22c55e' : '#ef4444')"
                      :arc-width="28"
                    />
                  </VisSingleContainer>
                  <div
                    class="pointer-events-none absolute inset-0 flex items-center justify-center"
                  >
                    <div class="flex flex-col items-center justify-center">
                      <span class="text-base font-bold text-green-600"
                        >{{ manurePercent.toFixed(1) }}%</span
                      >
                    </div>
                  </div>
                </div>
                <div class="mt-2 flex justify-center gap-3 text-xs font-medium">
                  <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-4 rounded-sm bg-green-500" />Accomplished
                  </span>
                  <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-4 rounded-sm bg-red-500" />Remaining
                  </span>
                </div>
                <div class="mt-2 space-y-0.5 text-center text-xs text-gray-500">
                  <p>
                    Target:
                    <span class="font-semibold text-gray-700">{{
                      formatKg(productionGoalManure)
                    }}</span>
                  </p>
                  <p>
                    Accomplished:
                    <span class="font-semibold text-green-600">{{
                      formatKg(totalManureProduced)
                    }}</span>
                  </p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="px-4 text-center">
            <CardTitle class="text-lg sm:text-xl">Milk Feeding Program</CardTitle>
            <CardDescription class="text-xs sm:text-sm">
              Government partnerships for nutrition and food security
            </CardDescription>
          </CardHeader>
          <CardContent class="px-2 pb-6 sm:px-6">
            <div class="mb-4 text-center sm:text-left">
              <h3 class="text-sm font-semibold text-gray-700 sm:text-base">
                DepEd School-based Feeding Program
              </h3>
            </div>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5">
              <div
                v-for="item in metricMilkFeedingProgramDeped"
                :key="item.title"
                class="flex flex-col items-center rounded-lg bg-gray-50 p-2 text-center sm:p-3"
              >
                <div class="w-full truncate text-sm font-bold sm:text-lg lg:text-2xl">
                  {{ formatMetricValue(item.valueKey, item.metricUnit) }}
                </div>
                <div
                  class="mt-1 text-[8px] font-semibold tracking-tight text-gray-500 uppercase sm:text-xs"
                >
                  {{ item.title }}
                </div>
              </div>
            </div>

            <div class="mt-8 mb-4 text-center sm:text-left">
              <h3 class="text-sm font-semibold text-gray-700 sm:text-base">
                DSWD Supplementary Feeding Program
              </h3>
            </div>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5">
              <div
                v-for="item in metricMilkFeedingProgramDSWD"
                :key="item.title"
                class="flex flex-col items-center rounded-lg bg-gray-50 p-2 text-center sm:p-3"
              >
                <div class="w-full truncate text-sm font-bold sm:text-lg lg:text-2xl">
                  {{ formatMetricValue(item.valueKey, item.metricUnit) }}
                </div>
                <div
                  class="mt-1 text-[8px] font-semibold tracking-tight text-gray-500 uppercase sm:text-xs"
                >
                  {{ item.title }}
                </div>
              </div>
            </div>

            <div class="mt-8 mb-4 text-center sm:text-left">
              <h3 class="text-sm font-semibold text-gray-700 sm:text-base">
                Other Fund Source Program
              </h3>
            </div>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5">
              <div
                v-for="item in metricMilkFeedingProgramOther"
                :key="item.title"
                class="flex flex-col items-center rounded-lg bg-gray-50 p-2 text-center sm:p-3"
              >
                <div class="w-full truncate text-sm font-bold sm:text-lg lg:text-2xl">
                  {{ formatMetricValue(item.valueKey, item.metricUnit) }}
                </div>
                <div
                  class="mt-1 text-[8px] font-semibold tracking-tight text-gray-500 uppercase sm:text-xs"
                >
                  {{ item.title }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="mt-6">
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-2 lg:grid-cols-4">
          <Card v-for="card in CBEBenterpricesMetric" :key="card.title">
            <CardContent
              class="flex flex-col items-center justify-center space-y-1 px-1 pt-3 pb-2 text-center"
            >
              <div class="w-full truncate text-sm font-bold sm:text-lg lg:text-2xl">
                {{ formatMetricValue(card.valueKey, card.metricUnit) }}
              </div>
              <div
                class="text-[8px] font-semibold tracking-tight text-gray-500 uppercase sm:text-xs"
              >
                {{ card.title }}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <div class="mt-6">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 lg:gap-4">
          <Card v-for="card in CBEDcomplianceMetric" :key="card.title">
            <CardContent
              class="flex flex-col items-center justify-center space-y-1 px-1 pt-3 pb-2 text-center"
            >
              <div class="w-full truncate text-sm font-bold sm:text-lg lg:text-2xl">
                {{ formatMetricValue(card.valueKey, card.metricUnit) }}
              </div>
              <div
                class="text-[8px] font-semibold tracking-tight text-gray-500 uppercase sm:text-xs"
              >
                {{ card.title }}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </slot>
  </div>
</template>
