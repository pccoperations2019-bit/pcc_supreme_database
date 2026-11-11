<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { formatMetricValue as formatMetricValueHelper } from '@/lib/utils'
import TrendIndicator from '@/components/TrendIndicator.vue'

const props = defineProps({
  totalFarmers: { type: Number, default: 0 },
  totalAnimals: { type: Number, default: 0 },
  cowsInMilkLine: { type: Number, default: 0 },
  totalMilkVolume: { type: Number, default: 0 },
  totalMeatProduced: { type: Number, default: 0 },
  totalManureProduced: { type: Number, default: 0 },
  totalMilkValue: { type: Number, default: 0 },
  totalMeatValue: { type: Number, default: 0 },
  totalManureValue: { type: Number, default: 0 },

  // Previous-period totals for year-over-year trend comparison,
  // keyed by the metric value keys (e.g. totalMilkVolume)
  previousTotals: { type: Object, default: () => ({}) },
  selectedProductionYear: { type: Number, default: null },

  processingCompliance: { type: Number, default: 0 },
  marketCompliance: { type: Number, default: 0 },
  dairyBoxCompliance: { type: Number, default: 0 },
  knpCompliance: { type: Number, default: 0 },

  csoCompliance: { type: Number, default: 0 },
  fdaLtoCompliance: { type: Number, default: 0 },
  ndaLtoCompliance: { type: Number, default: 0 },
  ffedisCompliance: { type: Number, default: 0 },
  rsbsaCompliance: { type: Number, default: 0 },

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

  processingEnterprises: { type: Number, default: 0 },
  marketEnterprises: { type: Number, default: 0 },
  dairyBoxEnterprises: { type: Number, default: 0 },
})

const metricCardsSummary = [
  {
    title: 'Farm Workers',
    description: 'Individual farmers participating in CBED',
    valueKey: 'totalFarmers',
  },
  {
    title: 'Animal Inventory',
    description: 'Total carabao population in program',
    valueKey: 'totalAnimals',
  },
  {
    title: 'Cows on the Milk Line',
    description: 'Active milking carabao in production',
    valueKey: 'cowsInMilkLine',
  },
]

const metricBuffaloDerivedKg = [
  { title: 'Total Milk', valueKey: 'totalMilkVolume', metricUnit: 'kg' },
  { title: 'Total Meat', valueKey: 'totalMeatProduced', metricUnit: 'kg' },
  { title: 'Total Manure', valueKey: 'totalManureProduced', metricUnit: 'kg' },
]
const metricBuffaloDerivedPhp = [
  { title: 'Total Milk Value', valueKey: 'totalMilkValue', metricUnit: 'PHP' },
  { title: 'Total Meat Value', valueKey: 'totalMeatValue', metricUnit: 'PHP' },
  { title: 'Total Manure Value', valueKey: 'totalManureValue', metricUnit: 'PHP' },
]

const metricMilkFeedingDepEd = [
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

const metricMilkFeedingDSWD = [
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
const metricMilkFeedingOther = [
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
const metricEnterpriseDocs = [
  { title: 'Processing', valueKey: 'processingEnterprises' },
  { title: 'Marketing', valueKey: 'marketEnterprises' },
  { title: 'Dairy Box', valueKey: 'dairyBoxEnterprises' },
]
const metricCompliance = [
  { title: 'FDA-LTO', valueKey: 'fdaLtoCompliance' },
  { title: 'NDA-LTO', valueKey: 'ndaLtoCompliance' },
]
const metricDocsAndCompliance = [...metricEnterpriseDocs, ...metricCompliance]

const metricValues = computed(() => ({
  totalFarmers: props.totalFarmers || 0,
  totalAnimals: props.totalAnimals || 0,
  cowsInMilkLine: props.cowsInMilkLine || 0,
  totalMilkVolume: props.totalMilkVolume || 0,
  totalMeatProduced: props.totalMeatProduced || 0,
  totalManureProduced: props.totalManureProduced || 0,
  totalMilkValue: props.totalMilkValue || 0,
  totalMeatValue: props.totalMeatValue || 0,
  totalManureValue: props.totalManureValue || 0,

  processingCompliance: props.processingCompliance || 0,
  marketCompliance: props.marketCompliance || 0,
  dairyBoxCompliance: props.dairyBoxCompliance || 0,
  knpCompliance: props.knpCompliance || 0,

  processingEnterprises: props.processingEnterprises || 0,
  marketEnterprises: props.marketEnterprises || 0,
  dairyBoxEnterprises: props.dairyBoxEnterprises || 0,

  csoCompliance: props.csoCompliance || 0,
  fdaLtoCompliance: props.fdaLtoCompliance || 0,
  ndaLtoCompliance: props.ndaLtoCompliance || 0,
  ffedisCompliance: props.ffedisCompliance || 0,
  rsbsaCompliance: props.rsbsaCompliance || 0,

  // Milk feeding program values
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
}))

const page = usePage()
const currencySymbols = computed(() => page.props.currencySymbols || {})

const formatMetricValue = (valueKey, unit = '') =>
  formatMetricValueHelper(metricValues.value[valueKey] ?? 0, unit, currencySymbols.value)

const previousPeriodLabel = computed(() =>
  props.selectedProductionYear ? String(props.selectedProductionYear - 1) : 'previous year'
)
</script>

<template>
  <div class="px-2 sm:px-0">
    <div class="grid grid-cols-3 gap-2">
      <Card v-for="card in metricCardsSummary" :key="card.title">
        <CardContent
          class="flex flex-col items-center justify-center space-y-1 px-1 pt-3 pb-2 text-center"
        >
          <div class="w-full truncate text-sm font-bold sm:text-lg lg:text-2xl">
            {{ formatMetricValue(card.valueKey) }}
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
              v-for="item in metricBuffaloDerivedKg"
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
              v-for="item in metricBuffaloDerivedPhp"
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
              v-for="item in metricMilkFeedingDepEd"
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
              v-for="item in metricMilkFeedingDSWD"
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
              v-for="item in metricMilkFeedingOther"
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
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 lg:gap-4">
        <Card v-for="card in metricDocsAndCompliance" :key="card.title">
          <CardContent
            class="flex flex-col items-center justify-center space-y-1 px-1 pt-3 pb-2 text-center"
          >
            <div class="w-full truncate text-sm font-bold sm:text-lg lg:text-2xl">
              {{ formatMetricValue(card.valueKey) }}
            </div>
            <div class="text-[8px] font-semibold tracking-tight text-gray-500 uppercase sm:text-xs">
              {{ card.title }}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>
