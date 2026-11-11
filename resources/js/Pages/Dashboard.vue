<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import FiltersPanel from '@/components/FiltersPanel.vue'
import DashboardFilters from '@/components/DashboardFilters.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, defineAsyncComponent, watch, computed } from 'vue'
import { monthsArray } from '@/enums/MonthEnum'
// import { getProvincesByRegion } from '../utils/geoJsonLoader.js'
// Dynamic component imports
const DashboardCBED = defineAsyncComponent(() => import('@/Pages/Partials/DashboardCBED.vue'))
const DashboardCenter = defineAsyncComponent(() => import('@/Pages/Partials/DashboardCenter.vue'))
const CBEDMap = defineAsyncComponent(() => import('@/components/CBEDMap.vue'))

import { useAuth } from '@/composable/useAuth'
import debounce from 'lodash/debounce'

// Define props for PCC SUPREME dashboard data
const page = usePage()
const { isCenterAdmin, user } = useAuth()

const inertiaProps = computed(() => page.props || {})

const readFilterParams = () => {
  const searchParams = new URLSearchParams(window.location.search)
  const filterParams = {}
  for (const [key, value] of searchParams.entries()) {
    if (key.startsWith('filter[')) {
      const cleanKey = key.replace('filter[', '').replace(']', '')
      filterParams[cleanKey] = value
    }
  }
  return filterParams
}

const filters = ref(inertiaProps.value.filters || {})

// Keep the local filters in sync with the server-side props on Inertia
// navigations (e.g. switching the program dropdown, which updates
// `clusteredCenters` = GP/NIZ). The component instance is reused across
// navigations, so the ref above only holds its initial value; without this
// watch the sidebar would only reflect the change after a full page refresh.
watch(
  () => inertiaProps.value.filters,
  value => {
    if (value) filters.value = value
  },
  { deep: true }
)

const programFilter = ref(isCenterAdmin.value ? 'cbed' : readFilterParams().program || 'cbed')

const activeDashboard = computed(() => (programFilter.value === 'ih' ? 'center' : 'cbed'))

const totalFcas = computed(() => inertiaProps.value.total_fcas ?? 0)
const totalFarmers = computed(() => inertiaProps.value.total_farmers ?? 0)
const totalProductionStaff = computed(() => inertiaProps.value.total_production_staff ?? 0)
const totalAnimals = computed(() => inertiaProps.value.total_animals ?? 0)
const cowsInMilkLine = computed(() => inertiaProps.value.cows_in_milk_line ?? 0)
const productionSupportServices = computed(
  () => inertiaProps.value.production_support_services ?? 0
)
const marketDevelopmentServices = computed(
  () => inertiaProps.value.market_development_services ?? 0
)
const iecClients = computed(() => inertiaProps.value.iec_clients ?? 0)
const clientsTrained = computed(() => inertiaProps.value.clients_trained ?? 0)

const totalMilkVolume = computed(() => inertiaProps.value.total_milk_volume ?? 0)
const totalMeatProduced = computed(() => inertiaProps.value.total_meat_produced ?? 0)
const totalManureProduced = computed(() => inertiaProps.value.total_manure_produced ?? 0)
const totalMilkValue = computed(() => inertiaProps.value.total_milk_value ?? 0)
const totalMeatValue = computed(() => inertiaProps.value.total_meat_value ?? 0)
const totalManureValue = computed(() => inertiaProps.value.total_manure_value ?? 0)

const previousBuffaloTotals = computed(() => inertiaProps.value.previous_buffalo_totals ?? {})
const selectedProductionYear = computed(() => inertiaProps.value.selected_production_year ?? null)

const productionGoalMilk = computed(() => inertiaProps.value.production_goal_milk ?? 0)
const productionGoalMeat = computed(() => inertiaProps.value.production_goal_meat ?? 0)
const productionGoalManure = computed(() => inertiaProps.value.production_goal_manure ?? 0)
const totalMilkVolumeCbed = computed(() => inertiaProps.value.total_milk_volume_cbed ?? 0)

const depedTotalBeneficiaries = computed(() => inertiaProps.value.deped_total_beneficiaries ?? 0)
const depedMilkPacksDistributed = computed(
  () => inertiaProps.value.deped_milk_packs_distributed ?? 0
)
const depedNoOfSdosEngaged = computed(() => inertiaProps.value.deped_no_of_sdos_engaged ?? 0)
const depedNoOfCooperativesEngaged = computed(
  () => inertiaProps.value.deped_no_of_cooperatives_engaged ?? 0
)
const depedGrossIncome = computed(() => inertiaProps.value.deped_gross_income ?? 0)

const dswdTotalBeneficiaries = computed(() => inertiaProps.value.dswd_total_beneficiaries ?? 0)
const dswdMilkPacksDistributed = computed(() => inertiaProps.value.dswd_milk_packs_distributed ?? 0)
const dswdNoOfLgusEngaged = computed(() => inertiaProps.value.dswd_no_of_lgus_engaged ?? 0)
const dswdNoOfCooperativesEngaged = computed(
  () => inertiaProps.value.dswd_no_of_cooperatives_engaged ?? 0
)
const dswdGrossIncome = computed(() => inertiaProps.value.dswd_gross_income ?? 0)

const lguTotalBeneficiaries = computed(() => inertiaProps.value.lgu_total_beneficiaries ?? 0)
const lguMilkPacksDistributed = computed(() => inertiaProps.value.lgu_milk_packs_distributed ?? 0)
const lguNoOfLgusEngaged = computed(() => inertiaProps.value.lgu_no_of_lgus_engaged ?? 0)
const lguNoOfCooperativesEngaged = computed(
  () => inertiaProps.value.lgu_no_of_cooperatives_engaged ?? 0
)
const lguGrossIncome = computed(() => inertiaProps.value.lgu_gross_income ?? 0)

const ldsTotalBeneficiaries = computed(() => inertiaProps.value.lds_total_beneficiaries ?? 0)
const ldsMilkPacksDistributed = computed(() => inertiaProps.value.lds_milk_packs_distributed ?? 0)
const ldsNoOfLgusEngaged = computed(() => inertiaProps.value.lds_no_of_lgus_engaged ?? 0)
const ldsNoOfCooperativesEngaged = computed(
  () => inertiaProps.value.lds_no_of_cooperatives_engaged ?? 0
)
const ldsGrossIncome = computed(() => inertiaProps.value.lds_gross_income ?? 0)

const processingEnterprises = computed(() => inertiaProps.value.processing_enterprises ?? 0)
const marketEnterprises = computed(() => inertiaProps.value.market_enterprises ?? 0)
const dairyBoxEnterprises = computed(() => inertiaProps.value.dairy_box_enterprises ?? 0)
const knpEnterprises = computed(() => inertiaProps.value.knp_enterprises ?? 0)

const years = computed(() => inertiaProps.value.production_years ?? [])
const months = monthsArray

const _rawMonth = readFilterParams().productionMonth
let _initialMonth
if (_rawMonth === '' || (typeof _rawMonth === 'string' && _rawMonth.toLowerCase() === 'all')) {
  _initialMonth = 'all'
} else if (_rawMonth !== undefined && _rawMonth !== null && _rawMonth !== '') {
  const _parsed = Number(_rawMonth)
  _initialMonth = Number.isNaN(_parsed) ? new Date().getMonth() + 1 : _parsed
} else {
  _initialMonth = new Date().getMonth() + 1
}
const productionYear = ref(Number(readFilterParams().productionYear) || new Date().getFullYear())
const productionMonth = ref(_initialMonth)

const _toMonthParam = month =>
  month === 'all' || month === '' || month === null || month === undefined ? 'all' : String(month)

// Centralized list of Inertia props that must be reloaded when program or dates change.
// Historically the program watcher used a truncated `only` list (missing milk/meat/manure,
// compliance, goals, etc.) so only `filters/hidden_centers/centers` appeared to update and
// a full page refresh was required to see the numbers. Keep this list in sync with
// `DashboardController::__invoke` returned props.
const DASHBOARD_ONLY = [
  'filters',
  'hidden_centers',
  '_program',
  'center_regions',
  'centers',
  'total_fcas',
  'total_farmers',
  'total_production_staff',
  'total_animals',
  'cows_in_milk_line',
  'production_support_services',
  'market_development_services',
  'iec_clients',
  'clients_trained',
  'total_milk_volume',
  'total_meat_produced',
  'total_manure_produced',
  'total_milk_value',
  'total_meat_value',
  'total_manure_value',
  'previous_buffalo_totals',
  'selected_production_year',
  'production_goal_milk',
  'production_goal_meat',
  'production_goal_manure',
  'total_milk_volume_cbed',
  'deped_total_beneficiaries',
  'deped_milk_packs_distributed',
  'deped_no_of_sdos_engaged',
  'deped_no_of_cooperatives_engaged',
  'deped_gross_income',
  'dswd_total_beneficiaries',
  'dswd_milk_packs_distributed',
  'dswd_no_of_lgus_engaged',
  'dswd_no_of_cooperatives_engaged',
  'dswd_gross_income',
  'lgu_total_beneficiaries',
  'lgu_milk_packs_distributed',
  'lgu_no_of_lgus_engaged',
  'lgu_no_of_cooperatives_engaged',
  'lgu_gross_income',
  'lds_total_beneficiaries',
  'lds_milk_packs_distributed',
  'lds_no_of_lgus_engaged',
  'lds_no_of_cooperatives_engaged',
  'lds_gross_income',
  'processing_enterprises',
  'market_enterprises',
  'dairy_box_enterprises',
  'knp_enterprises',
  'production_years',
  'milk_feeding_years',
  'certification_labels',
  'certification_property_mapping',
  // Document / compliance counts depend on `program` (IH vs CBED) via DocumentCountQuery
  'cso_compliance',
  'fda_lto_compliance',
  'nda_lto_compliance',
  'rsbsa_compliance',
  'ffedis_compliance',
  'business_documents_compliance',
  'fda_certifications_compliance',
  'ffeis_registration_compliance',
  'iso_compliance',
  'nda_compliance',
  'dole_compliance',
  'cda_compliance',
  'sec_compliance',
  'processing_compliance',
  'market_compliance',
  'dairy_box_compliance',
  'knp_compliance',
  'bir_compliance',
  'philgeps_compliance',
  'product_registration_compliance',
]

// Single debounced request – avoids double Inertia visit when year+month change together (tip #6, #7)
// and keeps program/date sync in one place so `only` cannot drift again.
const pushDashboardFilters = debounce((year, month, prog) => {
  const nextFilters = readFilterParams()
  nextFilters.productionYear = String(year)
  nextFilters.productionMonth = _toMonthParam(month)
  nextFilters.milkFeedingYear = String(year)
  nextFilters.milkFeedingMonth = _toMonthParam(month)
  nextFilters.servicesYear = String(year)
  nextFilters.servicesMonth = _toMonthParam(month)
  nextFilters.program = prog === 'all' ? 'all' : prog
  router.get(
    route('dashboard'),
    {
      program: prog === 'ih' ? 'ih' : 'cbed',
      filter: nextFilters,
    },
    {
      only: DASHBOARD_ONLY,
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}, 350)

watch(
  () => [productionYear.value, productionMonth.value],
  ([year, month]) => {
    pushDashboardFilters(year, month, programFilter.value)
  }
)

const programOptions = computed(() =>
  isCenterAdmin.value
    ? [{ label: 'CBED', value: 'cbed' }]
    : [
        { label: 'IH', value: 'ih' },
        { label: 'CBED', value: 'cbed' },
        { label: 'IH + CBED', value: 'all' },
      ]
)

watch(
  () => programFilter.value,
  value => {
    if (isCenterAdmin.value) {
      programFilter.value = 'cbed'
      return
    }
    pushDashboardFilters(productionYear.value, productionMonth.value, value)
  }
)

// Keep programFilter in sync with the URL after Inertia navigations
// (back/forward, external links). `readFilterParams()` is source of truth for `filter[program]`.
watch(
  () => page.url,
  () => {
    if (isCenterAdmin.value) return
    const urlProg = readFilterParams().program
    if (urlProg && urlProg !== programFilter.value) {
      programFilter.value = urlProg
    }
  }
)

const csoCompliance = computed(() => inertiaProps.value.cso_compliance ?? 0)
const fdaLtoCompliance = computed(() => inertiaProps.value.fda_lto_compliance ?? 0)
const ndaLtoCompliance = computed(() => inertiaProps.value.nda_lto_compliance ?? 0)
const rsbsaCompliance = computed(() => inertiaProps.value.rsbsa_compliance ?? 0)
const ffedisCompliance = computed(() => inertiaProps.value.ffedis_compliance ?? 0)

const businessDocumentsCompliance = computed(
  () => inertiaProps.value.business_documents_compliance ?? 0
)
const fdaCertificationsCompliance = computed(
  () => inertiaProps.value.fda_certifications_compliance ?? 0
)
const ffeisRegistrationCompliance = computed(
  () => inertiaProps.value.ffeis_registration_compliance ?? 0
)
const isoCompliance = computed(() => inertiaProps.value.iso_compliance ?? 0)
const ndaCompliance = computed(() => inertiaProps.value.nda_compliance ?? 0)
const doleCompliance = computed(() => inertiaProps.value.dole_compliance ?? 0)
const cdaCompliance = computed(() => inertiaProps.value.cda_compliance ?? 0)
const secCompliance = computed(() => inertiaProps.value.sec_compliance ?? 0)

const processingCompliance = computed(() => inertiaProps.value.processing_compliance ?? 0)
const marketCompliance = computed(() => inertiaProps.value.market_compliance ?? 0)
const dairyBoxCompliance = computed(() => inertiaProps.value.dairy_box_compliance ?? 0)
const knpCompliance = computed(() => inertiaProps.value.knp_compliance ?? 0)

const centers = computed(() => inertiaProps.value.centers || [])
const hiddenCenters = computed(() => inertiaProps.value.hidden_centers || [])
// const centerRegions = computed(() => inertiaProps.value.center_regions || {})

// Center codes to hide per dashboard/program (mirrors Support\Centers::hiddenCenters)
// GP removed from all profiles: "all" behaves like CBED (hide GP+NHQ, show NIZ).
const hiddenCenterCodes = computed(() => {
  if (programFilter.value === 'all') return new Set(['NHQ', 'GP'])
  const hidden = activeDashboard.value === 'center' ? ['NHQ', 'NIZ'] : ['NHQ', 'GP']
  return new Set(hidden)
})

// Filter centers for center-admin users, and hide program-excluded centers otherwise
const filteredCenters = computed(() => {
  if (isCenterAdmin.value) {
    if (user.value?.center_id) {
      return centers.value.filter(center => center.id === user.value.center_id)
    }
  }

  return centers.value.filter(
    center => !hiddenCenterCodes.value.has(String(center.code ?? '').toUpperCase())
  )
})

// Filter to only active centers for map display, excluding hidden centers for the current program
const activeCentersForMap = computed(() => {
  return filteredCenters.value.filter(
    center =>
      center.status === 'active' && !hiddenCenters.value.includes(center.code?.toLowerCase())
  )
})

// Adjust map settings for center-admin users
const mapMidpoint = computed(() => {
  if (isCenterAdmin.value && filteredCenters.value.length > 0) {
    const center = filteredCenters.value[0]
    if (center.latitude && center.longitude) {
      return [center.latitude, center.longitude]
    }
  }
  return [12.8797, 121.774]
})

const mapZoom = computed(() => (isCenterAdmin.value ? 10 : 6))

const updateDashboardFilters = newFilters => {
  console.log('Updated Filters from Dashboard:', newFilters)
  router.get(route('dashboard'), { filter: newFilters }, { preserveState: true, replace: true })
}

const onNavClick = () => {}

const handleCenterSelected = centerId => {
  const nextFilters = { ...(filters.value || {}) }
  if (centerId == null) delete nextFilters.center_id
  else nextFilters.center_id = centerId
  filters.value = nextFilters
  // Don't reload centers/center_regions/addresses on marker click — that
  // remounts CBEDMap and hides the modal. Only totals need to re-filter.
  // Reuse DASHBOARD_ONLY minus the map props, but include compliance which is center-sensitive for IH.
  const centerOnly = DASHBOARD_ONLY.filter(
    p =>
      ![
        'centers',
        'center_regions',
        'production_years',
        'milk_feeding_years',
        'certification_labels',
        'certification_property_mapping',
      ].includes(p)
  )
  router.get(
    route('dashboard'),
    {
      program: programFilter.value === 'ih' ? 'ih' : 'cbed',
      filter: nextFilters,
    },
    {
      only: centerOnly,
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}

// URL is source of truth — Inertia handles reload via preserveState, no extra watcher needed

// const provinces = computed(() => {
//   return props.center_regions.flatMap(region => getProvincesByRegion(region))
// })

/**
 * {
    "clsu": {
        "regions": [
            "Region VI (Western Visayas)",
            "Region VIII (Eastern Visayas)"
        ],
        "color": "#9AE66A"
    },
    "cmu": {
        "regions": [
            "Region I (Ilocos Region)",
            "Region VI (Western Visayas)",
            "Region VIII (Eastern Visayas)"
        ],
        "color": "#F3B33D"
    },
    "csu": {
        "regions": [
            "CAR",
            "II",
            "Region I (Ilocos Region)",
            "Region II (Cagayan Valley)",
            "Region IV-A (CALABARZON)",
            "Region VI (Western Visayas)",
            "Region VIII (Eastern Visayas)"
        ],
        "color": "#F7ECA6"
    },
    "lcsf": {
        "regions": [
            "Region IV-A (CALABARZON)",
            "Region VI (Western Visayas)",
            "Region VII (Central Visayas)"
        ],
        "color": "#8CB8E4"
    },
    "mlpc": {
        "regions": [
            "Region I (Ilocos Region)",
            "Region II (Cagayan Valley)",
            "Region IV-A (CALABARZON)",
            "Region VIII (Eastern Visayas)"
        ],
        "color": "#9F9E37"
    },
    "mmsu": {
        "regions": [
            "Region I (Ilocos Region)",
            "Region II (Cagayan Valley)",
            "Region IV-A (CALABARZON)",
            "Region VI (Western Visayas)",
            "Region VIII (Eastern Visayas)"
        ],
        "color": "#F4AAA9"
    },
    "niz": {
        "regions": [
            "Region I (Ilocos Region)",
            "Region II (Cagayan Valley)",
            "Region III (Central Luzon)",
            "Region VI (Western Visayas)"
        ],
        "color": "#76D7FA"
    },
    "uplb": {
        "regions": [
            "Region I (Ilocos Region)",
            "Region II (Cagayan Valley)",
            "Region VI (Western Visayas)",
            "Region VIII (Eastern Visayas)"
        ],
        "color": "#C1583F"
    },
    "usf": {
        "regions": [
            "Region I (Ilocos Region)",
            "Region II (Cagayan Valley)",
            "Region VI (Western Visayas)",
            "Region VIII (Eastern Visayas)",
            "VII"
        ],
        "color": "#F9A6A4"
    },
    "usm": {
        "regions": [
            "Autonomous Region in Muslim Mindanao (BARMM)",
            "Region IX (Zamboanga Peninzula)",
            "Region XI (Davao Region)",
            "Region XII (SOCCSKSARGEN)",
            "Region XIII (Caraga)"
        ],
        "color": "#E99163"
    },
    "vsu": {
        "regions": [
            "Region I (Ilocos Region)",
            "Region II (Cagayan Valley)",
            "Region IV-A (CALABARZON)",
            "Region VI (Western Visayas)",
            "Region VIII (Eastern Visayas)"
        ],
        "color": "#C96B36"
    },
    "wvsu": {
        "regions": [
            "Region I (Ilocos Region)",
            "Region II (Cagayan Valley)",
            "Region IV-A (CALABARZON)",
            "Region VI (Western Visayas)"
        ],
        "color": "#D5B15B"
    }
}
 */
// const colors = ref({})

// const provinces = computed(() => {
//   const provs = []
//   const colorsMap = {}
//   for (const centerCode in centerRegions.value) {
//     const center = centerRegions.value[centerCode]
//     console.log('Processing center:', centerCode, center)
//     for (const region of center.regions) {
//       const regionProvinces = getProvincesByRegion(region)
//       for (const province of regionProvinces) {
//         if (!provs.includes(province)) {
//           provs.push(province)
//           colorsMap[province] = center.color
//         }
//       }
//     }
//   }
//   colors.value = colorsMap
//   return provs
// })

// console.log('Computed provinces based on center_regions:', provinces.value)
// console.log('centerRegions:', centerRegions.value)
// console.log('colors mapping:', colors.value)
</script>

<template>
  <Head title="Dashboard" />
  <DashboardLayout :filters="filters" @nav-click="onNavClick">
    <template #filters>
      <div class="flex flex-col gap-4">
        <DashboardFilters
          :years="years"
          :months="months"
          :program-options="programOptions"
          :production-year="productionYear"
          :production-month="productionMonth"
          :program-filter="programFilter"
          @update:production-year="val => (productionYear = val)"
          @update:production-month="val => (productionMonth = val)"
          @update:program-filter="val => (programFilter = val)"
        />
        <FiltersPanel v-if="!isCenterAdmin" :filters="filters" :elements="['clusteredCenters']" />
      </div>
    </template>
    <div class="flex flex-col lg:flex-row">
      <div class="w-full lg:w-2/3">
        <DashboardCBED
          v-if="isCenterAdmin || activeDashboard === 'cbed'"
          :total-fcas="totalFcas"
          :total-farmers="totalFarmers"
          :total-animals="totalAnimals"
          :cows-in-milk-line="cowsInMilkLine"
          :production-support-services="productionSupportServices"
          :market-development-services="marketDevelopmentServices"
          :iec-clients="iecClients"
          :clients-trained="clientsTrained"
          :total-milk-volume="totalMilkVolume"
          :total-meat-produced="totalMeatProduced"
          :total-manure-produced="totalManureProduced"
          :total-milk-value="totalMilkValue"
          :total-meat-value="totalMeatValue"
          :total-manure-value="totalManureValue"
          :previous-totals="previousBuffaloTotals"
          :selected-production-year="selectedProductionYear"
          :deped-total-beneficiaries="depedTotalBeneficiaries"
          :deped-milk-packs-distributed="depedMilkPacksDistributed"
          :deped-no-of-sdos-engaged="depedNoOfSdosEngaged"
          :deped-no-of-cooperatives-engaged="depedNoOfCooperativesEngaged"
          :deped-gross-income="depedGrossIncome"
          :dswd-total-beneficiaries="dswdTotalBeneficiaries"
          :dswd-milk-packs-distributed="dswdMilkPacksDistributed"
          :dswd-no-of-lgus-engaged="dswdNoOfLgusEngaged"
          :dswd-no-of-cooperatives-engaged="dswdNoOfCooperativesEngaged"
          :dswd-gross-income="dswdGrossIncome"
          :lgu-total-beneficiaries="lguTotalBeneficiaries"
          :lgu-milk-packs-distributed="lguMilkPacksDistributed"
          :lgu-no-of-lgus-engaged="lguNoOfLgusEngaged"
          :lgu-no-of-cooperatives-engaged="lguNoOfCooperativesEngaged"
          :lgu-gross-income="lguGrossIncome"
          :lds-total-beneficiaries="ldsTotalBeneficiaries"
          :lds-milk-packs-distributed="ldsMilkPacksDistributed"
          :lds-no-of-lgus-engaged="ldsNoOfLgusEngaged"
          :lds-no-of-cooperatives-engaged="ldsNoOfCooperativesEngaged"
          :lds-gross-income="ldsGrossIncome"
          :processing-enterprises="processingEnterprises"
          :market-enterprises="marketEnterprises"
          :dairy-box-enterprises="dairyBoxEnterprises"
          :knp-enterprises="knpEnterprises"
          :cso-compliance="csoCompliance"
          :fda-lto-compliance="fdaLtoCompliance"
          :nda-lto-compliance="ndaLtoCompliance"
          :rsbsa-compliance="rsbsaCompliance"
          :ffedis-compliance="ffedisCompliance"
          :business-documents-compliance="businessDocumentsCompliance"
          :fda-certifications-compliance="fdaCertificationsCompliance"
          :ffeis-registration-compliance="ffeisRegistrationCompliance"
          :iso-compliance="isoCompliance"
          :nda-compliance="ndaCompliance"
          :dole-compliance="doleCompliance"
          :cda-compliance="cdaCompliance"
          :sec-compliance="secCompliance"
          :processing-compliance="processingCompliance"
          :market-compliance="marketCompliance"
          :dairy-box-compliance="dairyBoxCompliance"
          :knp-compliance="knpCompliance"
          :production-goal-milk="productionGoalMilk"
          :production-goal-meat="productionGoalMeat"
          :production-goal-manure="productionGoalManure"
          :total-milk-volume-cbed="totalMilkVolumeCbed"
          :is-center-admin="isCenterAdmin"
        />
        <DashboardCenter
          v-else
          :total-fcas="totalFcas"
          :total-farmers="totalProductionStaff"
          :total-animals="totalAnimals"
          :cows-in-milk-line="cowsInMilkLine"
          :total-milk-volume="totalMilkVolume"
          :total-meat-produced="totalMeatProduced"
          :total-manure-produced="totalManureProduced"
          :total-milk-value="totalMilkValue"
          :total-meat-value="totalMeatValue"
          :total-manure-value="totalManureValue"
          :previous-totals="previousBuffaloTotals"
          :selected-production-year="selectedProductionYear"
          :processing-compliance="processingCompliance"
          :market-compliance="marketCompliance"
          :dairy-box-compliance="dairyBoxCompliance"
          :knp-compliance="knpCompliance"
          :processing-enterprises="processingEnterprises"
          :market-enterprises="marketEnterprises"
          :dairy-box-enterprises="dairyBoxEnterprises"
          :cso-compliance="csoCompliance"
          :fda-lto-compliance="fdaLtoCompliance"
          :nda-lto-compliance="ndaLtoCompliance"
          :ffedis-compliance="ffedisCompliance"
          :rsbsa-compliance="rsbsaCompliance"
          :deped-total-beneficiaries="depedTotalBeneficiaries"
          :deped-milk-packs-distributed="depedMilkPacksDistributed"
          :deped-no-of-sdos-engaged="depedNoOfSdosEngaged"
          :deped-no-of-cooperatives-engaged="depedNoOfCooperativesEngaged"
          :deped-gross-income="depedGrossIncome"
          :dswd-total-beneficiaries="dswdTotalBeneficiaries"
          :dswd-milk-packs-distributed="dswdMilkPacksDistributed"
          :dswd-no-of-lgus-engaged="dswdNoOfLgusEngaged"
          :dswd-no-of-cooperatives-engaged="dswdNoOfCooperativesEngaged"
          :dswd-gross-income="dswdGrossIncome"
          :lgu-total-beneficiaries="lguTotalBeneficiaries"
          :lgu-milk-packs-distributed="lguMilkPacksDistributed"
          :lgu-no-of-lgus-engaged="lguNoOfLgusEngaged"
          :lgu-no-of-cooperatives-engaged="lguNoOfCooperativesEngaged"
          :lgu-gross-income="lguGrossIncome"
          :lds-total-beneficiaries="ldsTotalBeneficiaries"
          :lds-milk-packs-distributed="ldsMilkPacksDistributed"
          :lds-no-of-lgus-engaged="ldsNoOfLgusEngaged"
          :lds-no-of-cooperatives-engaged="ldsNoOfCooperativesEngaged"
          :lds-gross-income="ldsGrossIncome"
        />
      </div>
      <div class="mt-4 ml-0 hidden w-full lg:mt-0 lg:ml-4 lg:block lg:w-1/3">
        <CBEDMap
          :addresses="activeCentersForMap"
          :midpoint="mapMidpoint"
          :zoom="mapZoom"
          :show-provinces="true"
          :selected-provinces="[]"
          :province-colors="{}"
          :selected-center-id="filters?.center_id ? parseInt(filters.center_id) : null"
          @center-selected="handleCenterSelected"
        />
      </div>
    </div>
  </DashboardLayout>
</template>
