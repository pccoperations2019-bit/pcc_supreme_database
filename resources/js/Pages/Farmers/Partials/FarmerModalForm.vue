<script setup>
import { computed, watch, ref, inject, nextTick } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { toastFlash } from '@/lib/flashToasts'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { EDIT_TYPES, EDIT_TYPE_BY_MODAL_TYPE } from '@/lib/contants'

import ImageUploadField from '@/components/ImageUploadField.vue'
import { usePsgcAddress } from '@/composable/usePsgcAddress'
import { useFarmModalForm } from '@/composable/useFarmModalForm'
import {
  normalizeAnimalInventory,
  animalInventoryPayload,
  defaultAnimalOwnership,
  normalizeAnimalOwnership,
  animalOwnershipPayload,
} from '@/composable/useFarmerForm'
import FarmInformationTab from '@/components/farmer/modal-tabs/FarmInformationTab.vue'
import AnimalInventoryTab from '@/components/farmer/modal-tabs/AnimalInventoryTab.vue'
import LivestockTab from '@/components/farmer/modal-tabs/LivestockTab.vue'
import FeedManagementTab from '@/components/farmer/modal-tabs/FeedManagementTab.vue'
import WasteManagementTab from '@/components/farmer/modal-tabs/WasteManagementTab.vue'
import SocioEconomicTab from '@/components/farmer/modal-tabs/SocioEconomicTab.vue'
import SourcesOfIncomeTab from '@/components/farmer/modal-tabs/SourcesOfIncomeTab.vue'
import PovertyIndexTab from '@/components/farmer/modal-tabs/PovertyIndexTab.vue'

const props = defineProps({
  farmer: {
    type: Object,
    required: true,
  },
  enums: {
    type: Object,
    default: () => ({}),
  },
})

const modalState = inject('modalState', ref({ open: false, type: null, id: null }))
const createDrafts = inject('createDrafts', null)
const isCreateMode = computed(() => !!createDrafts)
const geotagFileInput = ref(null)
const geotagImageFile = ref(null)
const geotagImagePreview = ref(null)
const geotagBlobUrl = ref(null)

const entrepreneurFileInput = ref(null)
const entrepreneurImageFile = ref(null)
const entrepreneurImagePreview = ref(null)
const entrepreneurBlobUrl = ref(null)

const enterpriseFileInput = ref(null)
const enterpriseImageFile = ref(null)
const enterpriseImagePreview = ref(null)
const enterpriseBlobUrl = ref(null)

const editType = computed(() => {
  const type = modalState.value?.type
  return type ? (EDIT_TYPE_BY_MODAL_TYPE[type] ?? null) : null
})

const isOpen = computed(() => {
  return modalState.value?.open === true && editType.value !== null
})

const draftSourceFarmer = computed(() => {
  if (!isCreateMode.value) return null
  const type = modalState.value?.type
  const draft = type ? createDrafts?.[type] : null
  if (!draft) return null
  return {
    ...draft,
    metadata: draft.additional_details || {},
    address: {
      street: draft.street || '',
      district: draft.district || '',
      region: draft.region || '',
      province: draft.province || '',
      city: draft.municipality || '',
      barangay: draft.barangay || '',
      latitude: draft.gps_latitude || '',
      longitude: draft.gps_longitude || '',
    },
  }
})

const addressPsgc = usePsgcAddress()
const farmPsgc = usePsgcAddress()

const {
  regions: regionsData,
  provinces: addressProvinces,
  cities: addressCities,
  barangays: addressBarangays,
  load: loadAddressPsgc,
  fetchProvinces: fetchAddressProvinces,
  fetchCities: fetchAddressCities,
  fetchBarangays: fetchAddressBarangays,
  getRegionCodeByName: getAddressRegionCodeByName,
  getProvinceCodeByName: getAddressProvinceCodeByName,
  getCityCodeByName: getAddressCityCodeByName,
  getBarangayCodeByName: getAddressBarangayCodeByName,
  getRegionNameByCode: getAddressRegionNameByCode,
  getProvinceNameByCode: getAddressProvinceNameByCode,
  getCityNameByCode: getAddressCityNameByCode,
  getBarangayNameByCode: getAddressBarangayNameByCode,
  hasProvincesInRegion: addressHasProvincesInRegion,
} = addressPsgc

const {
  provinces: farmProvinces,
  cities: farmCities,
  barangays: farmBarangays,
  load: loadFarmPsgc,
  fetchProvinces: fetchFarmProvinces,
  fetchCities: fetchFarmCities,
  fetchBarangays: fetchFarmBarangays,
  getRegionCodeByName: getFarmRegionCodeByName,
  getProvinceCodeByName: getFarmProvinceCodeByName,
  getCityCodeByName: getFarmCityCodeByName,
  getBarangayCodeByName: getFarmBarangayCodeByName,
  getRegionNameByCode: getFarmRegionNameByCode,
  getProvinceNameByCode: getFarmProvinceNameByCode,
  getCityNameByCode: getFarmCityNameByCode,
  getBarangayNameByCode: getFarmBarangayNameByCode,
  hasProvincesInRegion: farmHasProvincesInRegion,
} = farmPsgc

const {
  setGroupDetail: rawSetGroupDetail,
  toggleGroupValue: rawToggleGroupValue,
  hasGroupValue: rawHasGroupValue,
  toBooleanLike,
} = useFarmModalForm()

const form = useForm({
  center_id: null,
  partner_id: null,
  // Personal Information
  first_name: '',
  middle_name: '',
  last_name: '',
  extension_name: '',
  nickname: '',
  gender: '',
  age: '',
  date_of_interview: '',
  birthday: '',
  civil_status: '',
  spouse_name: '',
  household_size: '',
  contact_number: '',
  has_personal_cellphone: false,
  purok_zone: '',

  // Address Information
  street: '',
  barangay: '',
  municipality: '',
  province: '',
  region: '',
  district: '',

  // Farming Information
  management_system: '',
  feeding_management: '',
  animal_ownership: defaultAnimalOwnership(),
  is_drylot: false,
  duration_of_stocking: '',
  is_applied_in_the_field: false,
  is_dry_rice_field: false,
  is_dry_vegetable: false,

  // Livestock Information
  dairy_cattle: 0,
  beef_cattle: 0,
  chicken: 0,
  duck: 0,
  geese: 0,
  goat: 0,
  swine: 0,
  horse: 0,
  other_livestock_details: [],
  animal_inventory: [],

  // Income Information
  main_source_of_income: '',
  main_annual_net_income: 0,
  secondary_source_of_income: '',
  secondary_annual_net_income: 0,
  tertiary_source_of_income: '',
  tertiary_annual_net_income: 0,
  quaternary_source_of_income: '',
  quaternary_annual_net_income: 0,
  avg_net_income: 0,
  ave_monthly_salary: 0,
  source_of_income_details: [],

  // Farm Information
  farm_name: '',
  farm_region: '',
  farm_province: '',
  farm_municipality_city: '',
  farm_barangay: '',
  farm_purok_zone: '',
  farm_district: '',
  farm_street: '',
  proposed_farm_name: '',
  type_of_farm_business_registration: [],
  status_of_registration: '',
  farm_registration_number: '',
  year_farm_established: '',
  pcc_projects_assets: '',
  is_solid_storage_covered: false,
  is_solid_storage_additives: false,
  is_uncovered_anaerobic_lagoon: false,

  // Milk Production & Marketing
  is_twice_a_day_milking: false,
  daily_milk_production_min: '',
  daily_milk_production_max: '',
  milk_price_per_liter_min: '',
  milk_price_per_liter_max: '',
  milk_selling_locations: [],
  milk_selling_restaurant_specify: '',
  milk_selling_processor_specify: '',
  milk_selling_collector_name: '',
  milk_selling_others_specify: '',
  assistance_needed: [],

  // Management Information
  is_vermicomposting: false,
  is_daily_spread: false,
  is_storage_bulking: false,
  is_liquid_slurry: false,
  is_anaerobic_digester: false,
  staff_count_male: 0,
  staff_count_female: 0,
  gps_latitude: '',
  gps_longitude: '',
  pcc_assets_latitude: '',
  pcc_assets_longitude: '',
  status_google_map: '',
  google_map_link: '',
  geotag_image: '',
  geotag_image_file: null,
  entrepreneur_image: '',
  entrepreneur_image_file: null,
  enterprise_image: '',
  enterprise_image_file: null,

  // Socio-Economic Information
  position: '',
  year_involvement_in_business: '',
  family_member_attending_school_count: '',
  female_count: '',
  male_count: '',
  is_pwd: false,
  is_4ps: false,
  is_ips: false,
  is_indigent: false,
  indigenous_group: '',
  membership_in_organization: '',
  name_of_organization: '',
  tv_type: '',
  educational_attainment: '',
  type_of_entrepreneur: '',

  // RSBSA & Production Information
  has_rsbsa: false,
  rsbsa_cert_status: '',
  rsbsa_certificate_of_registration: '',
  if_producer_please_specify: '',
  involved_in_buffalo_farming: false,
  how_many_in_family_are_involved_in_buffalo_farming: '',
  are_you_the_head_of_the_family: false,
  if_no_highest_educational_attainment_of_head_of_family: '',
  challenges_or_problems_encountered_in_dairy_farming: '',

  additional_details: {},
})

const setGroupDetail = (key, field, value) => rawSetGroupDetail(form, key, field, value)
const toggleGroupValue = (key, field, value, checked) =>
  rawToggleGroupValue(form, key, field, value, checked)
const hasGroupValue = (key, field, value) => rawHasGroupValue(form, key, field, value)

const feedItems = [
  'acacia',
  'atovi',
  'banana',
  'bran',
  'commercial',
  'cornstover',
  'dicalcium',
  'forage',
  'grain',
  'guine',
  'ipil',
  'kakawate',
  'kamote',
  'legumes',
  'molasses',
  'mombasa',
  'napier',
  'paragrass',
  'ricebran',
  'ricestraw',
  'sakate',
  'salt',
  'silage',
  'sorghum',
  'ummb',
  'utrs',
]
const getRegionNameByCode = code =>
  getAddressRegionNameByCode(code) || getFarmRegionNameByCode(code)

const getProvinceNameByCode = code =>
  getAddressProvinceNameByCode(code) || getFarmProvinceNameByCode(code)

const getCityNameByCode = code => getAddressCityNameByCode(code) || getFarmCityNameByCode(code)

const getBarangayNameByCode = code =>
  getAddressBarangayNameByCode(code) || getFarmBarangayNameByCode(code)

const hydratingAddress = ref(false)
const hydratingFarmAddress = ref(false)
const addressRegionHasProvinces = computed(() => addressHasProvincesInRegion(form.region))
const farmRegionHasProvinces = computed(() => farmHasProvincesInRegion(form.farm_region))

const hydratePrimaryAddressFields = async farmer => {
  const addressRegion = farmer.address?.region || ''
  const addressProvince = farmer.address?.province || ''
  const addressCity = farmer.address?.city || farmer.address?.municipality || ''
  const addressBarangay = farmer.address?.barangay || ''

  hydratingAddress.value = true
  try {
    form.region = getAddressRegionCodeByName(addressRegion) || addressRegion

    await fetchAddressProvinces(form.region)
    const regionHasProvinces = addressHasProvincesInRegion(form.region)
    form.province = getAddressProvinceCodeByName(addressProvince, form.region) || addressProvince

    await fetchAddressCities({
      provinceCode: form.province,
      regionCode: !regionHasProvinces ? form.region : '',
    })
    form.municipality =
      getAddressCityCodeByName(
        addressCity,
        form.province,
        !regionHasProvinces ? form.region : ''
      ) || addressCity

    await fetchAddressBarangays(form.municipality)
    form.barangay =
      getAddressBarangayCodeByName(addressBarangay, form.municipality) || addressBarangay
  } finally {
    hydratingAddress.value = false
  }
}

const hydrateFarmAddressFields = async farmer => {
  const farmRegion = farmer.farm_region || ''
  const farmProvince = farmer.farm_province || ''
  const farmCity = farmer.farm_municipality_city || ''
  const farmBarangay = farmer.farm_barangay || ''

  hydratingFarmAddress.value = true
  try {
    form.farm_region = getFarmRegionCodeByName(farmRegion) || farmRegion

    await fetchFarmProvinces(form.farm_region)
    const regionHasProvinces = farmHasProvincesInRegion(form.farm_region)
    form.farm_province = getFarmProvinceCodeByName(farmProvince, form.farm_region) || farmProvince

    await fetchFarmCities({
      provinceCode: form.farm_province,
      regionCode: !regionHasProvinces ? form.farm_region : '',
    })
    form.farm_municipality_city =
      getFarmCityCodeByName(
        farmCity,
        form.farm_province,
        !regionHasProvinces ? form.farm_region : ''
      ) || farmCity

    await fetchFarmBarangays(form.farm_municipality_city)
    form.farm_barangay =
      getFarmBarangayCodeByName(farmBarangay, form.farm_municipality_city) || farmBarangay
  } finally {
    hydratingFarmAddress.value = false
  }
}

// Watchers to reset dependent fields
watch(
  () => form.region,
  async () => {
    if (hydratingAddress.value) return
    form.province = ''
    form.municipality = ''
    form.barangay = ''
    await fetchAddressProvinces(form.region)

    if (!addressHasProvincesInRegion(form.region)) {
      await fetchAddressCities({ regionCode: form.region })
    }
  }
)

watch(
  () => form.province,
  async () => {
    if (hydratingAddress.value) return
    form.municipality = ''
    form.barangay = ''

    if (form.province) {
      await fetchAddressCities({ provinceCode: form.province })
      return
    }

    if (form.region && !addressHasProvincesInRegion(form.region)) {
      await fetchAddressCities({ regionCode: form.region })
    }
  }
)

watch(
  () => form.municipality,
  async () => {
    if (hydratingAddress.value) return
    form.barangay = ''
    await fetchAddressBarangays(form.municipality)
  }
)

watch(
  () => form.farm_region,
  async () => {
    if (hydratingFarmAddress.value) return
    form.farm_province = ''
    form.farm_municipality_city = ''
    form.farm_barangay = ''
    await fetchFarmProvinces(form.farm_region)

    if (!farmHasProvincesInRegion(form.farm_region)) {
      await fetchFarmCities({ regionCode: form.farm_region })
    }
  }
)

watch(
  () => form.farm_province,
  async () => {
    if (hydratingFarmAddress.value) return
    form.farm_municipality_city = ''
    form.farm_barangay = ''

    if (form.farm_province) {
      await fetchFarmCities({ provinceCode: form.farm_province })
      return
    }

    if (form.farm_region && !farmHasProvincesInRegion(form.farm_region)) {
      await fetchFarmCities({ regionCode: form.farm_region })
    }
  }
)

watch(
  () => form.farm_municipality_city,
  async () => {
    if (hydratingFarmAddress.value) return
    form.farm_barangay = ''
    await fetchFarmBarangays(form.farm_municipality_city)
  }
)

const setAdditionalDetail = (key, value) => {
  const k = String(key || '').toLowerCase()
  form.additional_details = { ...(form.additional_details || {}), [k]: value }
}

const setTvType = value => {
  form.tv_type = value
}

const toYesNoValue = value => {
  if (value === null || value === undefined || value === '') return ''
  return toBooleanLike(value) ? '1' : '0'
}

const normalizeRegistrationStatus = value => {
  if (!value) return ''
  const labels = props.enums.farmRegistrationStatus || {}
  if (Object.prototype.hasOwnProperty.call(labels, value)) return value
  const found = Object.entries(labels).find(
    ([, label]) => String(label).trim().toLowerCase() === String(value).trim().toLowerCase()
  )
  return found ? found[0] : value
}

const handleGeotagFileChange = event => {
  const file = event?.target?.files?.[0] || null
  geotagImageFile.value = file
  form.geotag_image_file = file
  if (file) {
    if (geotagBlobUrl.value) {
      window.URL.revokeObjectURL(geotagBlobUrl.value)
    }
    geotagBlobUrl.value = window.URL.createObjectURL(file)
    geotagImagePreview.value = geotagBlobUrl.value
  }
}

const handleGeotagRemove = () => {
  if (geotagBlobUrl.value) {
    window.URL.revokeObjectURL(geotagBlobUrl.value)
    geotagBlobUrl.value = null
  }
  geotagImageFile.value = null
  form.geotag_image_file = null
  geotagImagePreview.value = null
  form.geotag_image = ''
  if (geotagFileInput.value) {
    geotagFileInput.value.value = ''
  }
}

const handleEntrepreneurFileChange = event => {
  const file = event?.target?.files?.[0] || null
  entrepreneurImageFile.value = file
  form.entrepreneur_image_file = file
  if (file) {
    if (entrepreneurBlobUrl.value) {
      window.URL.revokeObjectURL(entrepreneurBlobUrl.value)
    }
    entrepreneurBlobUrl.value = window.URL.createObjectURL(file)
    entrepreneurImagePreview.value = entrepreneurBlobUrl.value
  }
}

const handleEntrepreneurRemove = () => {
  if (entrepreneurBlobUrl.value) {
    window.URL.revokeObjectURL(entrepreneurBlobUrl.value)
    entrepreneurBlobUrl.value = null
  }
  entrepreneurImageFile.value = null
  form.entrepreneur_image_file = null
  entrepreneurImagePreview.value = null
  form.entrepreneur_image = ''
  if (entrepreneurFileInput.value) {
    entrepreneurFileInput.value.value = ''
  }
}

const handleEnterpriseFileChange = event => {
  const file = event?.target?.files?.[0] || null
  enterpriseImageFile.value = file
  form.enterprise_image_file = file
  if (file) {
    if (enterpriseBlobUrl.value) {
      window.URL.revokeObjectURL(enterpriseBlobUrl.value)
    }
    enterpriseBlobUrl.value = window.URL.createObjectURL(file)
    enterpriseImagePreview.value = enterpriseBlobUrl.value
  }
}

const handleEnterpriseRemove = () => {
  if (enterpriseBlobUrl.value) {
    window.URL.revokeObjectURL(enterpriseBlobUrl.value)
    enterpriseBlobUrl.value = null
  }
  enterpriseImageFile.value = null
  form.enterprise_image_file = null
  enterpriseImagePreview.value = null
  form.enterprise_image = ''
  if (enterpriseFileInput.value) {
    enterpriseFileInput.value.value = ''
  }
}

const isBrowserFile = value =>
  typeof globalThis.File !== 'undefined' && value instanceof globalThis.File

const pruneDraftPayload = value => {
  if (value === null || value === undefined || value === '' || value === false || value === 0) {
    return undefined
  }
  if (isBrowserFile(value)) return value
  if (Array.isArray(value)) {
    const cleaned = value.map(pruneDraftPayload).filter(item => item !== undefined)
    return cleaned.length ? cleaned : undefined
  }
  if (typeof value === 'object') {
    const cleaned = {}
    Object.entries(value).forEach(([key, item]) => {
      const pruned = pruneDraftPayload(item)
      if (pruned !== undefined) cleaned[key] = pruned
    })
    return Object.keys(cleaned).length ? cleaned : undefined
  }
  return value
}

watch(
  [() => isOpen.value, () => props.farmer, () => draftSourceFarmer.value],
  async ([open]) => {
    const farmer = isCreateMode.value ? draftSourceFarmer.value || props.farmer : props.farmer
    if (open && farmer) {
      await Promise.all([loadAddressPsgc(), loadFarmPsgc()])

      nextTick(async () => {
        // Set center_id and partner_id from farmer
        form.center_id = farmer.center_id || null
        form.partner_id = farmer.partner_id || null

        // Personal Information
        form.first_name = farmer.first_name || ''
        form.middle_name = farmer.middle_name || ''
        form.last_name = farmer.last_name || ''
        form.extension_name = farmer.extension_name || ''
        form.nickname = farmer.nickname || ''
        form.gender = farmer.gender || ''
        form.age = farmer.age || ''
        form.date_of_interview = farmer.date_of_interview
          ? farmer.date_of_interview.split('T')[0]
          : ''
        form.birthday = farmer.birthday ? String(farmer.birthday).split('T')[0] : ''
        form.civil_status = farmer.civil_status || ''
        form.spouse_name = farmer.spouse_name || ''
        form.household_size = farmer.household_size ?? ''
        form.contact_number = farmer.contact_number || ''
        form.has_personal_cellphone =
          farmer.has_personal_cellphone === true || farmer.has_personal_cellphone === 1
        form.purok_zone = farmer.purok_zone || ''

        // Address Information
        form.street = farmer.address?.street || ''
        await hydratePrimaryAddressFields(farmer)
        form.district = farmer.address?.district || ''

        // Farming Information
        form.management_system = farmer.management_system || ''
        form.feeding_management = farmer.feeding_management || ''
        form.animal_ownership = normalizeAnimalOwnership(farmer.animal_ownership)
        form.is_drylot = farmer.is_drylot || false
        form.duration_of_stocking = farmer.duration_of_stocking || ''
        form.is_applied_in_the_field = farmer.is_applied_in_the_field || false
        form.is_dry_rice_field = farmer.is_dry_rice_field || false
        form.is_dry_vegetable = farmer.is_dry_vegetable || false

        // Livestock Information
        form.dairy_cattle = farmer.dairy_cattle || 0
        form.beef_cattle = farmer.beef_cattle || 0
        form.chicken = farmer.chicken || 0
        form.duck = farmer.duck || 0
        form.geese = farmer.geese || 0
        form.goat = farmer.goat || 0
        form.swine = farmer.swine || 0
        form.horse = farmer.horse || 0
        form.other_livestock_details = farmer.other_livestock_details?.length
          ? farmer.other_livestock_details.map(d => ({ ...d }))
          : farmer.other_livestock_label
            ? [
                {
                  label: farmer.other_livestock_label,
                  count: farmer.other_livestock_count || farmer.other_livestock || 0,
                },
              ]
            : []

        // SECTION 12 - Animal Inventory
        form.animal_inventory = normalizeAnimalInventory(farmer.animal_inventory)

        // Income Information
        form.main_source_of_income = farmer.main_source_of_income || ''
        form.main_annual_net_income = farmer.main_annual_net_income || 0
        form.secondary_source_of_income = farmer.secondary_source_of_income || ''
        form.secondary_annual_net_income = farmer.secondary_annual_net_income || 0
        form.tertiary_source_of_income = farmer.tertiary_source_of_income || ''
        form.tertiary_annual_net_income = farmer.tertiary_annual_net_income || 0
        form.quaternary_source_of_income = farmer.quaternary_source_of_income || ''
        form.quaternary_annual_net_income = farmer.quaternary_annual_net_income || 0
        form.avg_net_income = farmer.avg_net_income || 0
        form.source_of_income_details = farmer.source_of_income_details?.length
          ? farmer.source_of_income_details.map(d => ({ ...d }))
          : []

        // Farm Information
        form.farm_name = farmer.farm_name || ''
        form.farm_district = farmer.farm_district || ''
        form.farm_street = farmer.farm_street || ''
        form.proposed_farm_name = farmer.proposed_farm_name || ''
        form.type_of_farm_business_registration = farmer.type_of_farm_business_registration || []
        form.status_of_registration = normalizeRegistrationStatus(farmer.status_of_registration)
        form.farm_registration_number = farmer.farm_registration_number || ''
        form.has_rsbsa = toBooleanLike(farmer.has_rsbsa)
        form.year_farm_established = farmer.year_farm_established ?? ''
        await hydrateFarmAddressFields(farmer)
        form.farm_purok_zone = farmer.farm_purok_zone || ''
        form.pcc_projects_assets = farmer.pcc_projects_assets || ''
        form.is_solid_storage_covered = farmer.is_solid_storage_covered || false
        form.is_solid_storage_additives = farmer.is_solid_storage_additives || false
        form.is_uncovered_anaerobic_lagoon = farmer.is_uncovered_anaerobic_lagoon || false
        form.is_twice_a_day_milking = farmer.is_twice_a_day_milking || false
        form.daily_milk_production_min = farmer.daily_milk_production_min ?? ''
        form.daily_milk_production_max = farmer.daily_milk_production_max ?? ''
        form.milk_price_per_liter_min = farmer.milk_price_per_liter_min ?? ''
        form.milk_price_per_liter_max = farmer.milk_price_per_liter_max ?? ''
        form.milk_selling_locations = farmer.milk_selling_locations || []
        form.milk_selling_restaurant_specify = farmer.milk_selling_restaurant_specify || ''
        form.milk_selling_processor_specify = farmer.milk_selling_processor_specify || ''
        form.milk_selling_collector_name = farmer.milk_selling_collector_name || ''
        form.milk_selling_others_specify = farmer.milk_selling_others_specify || ''
        form.assistance_needed = farmer.assistance_needed || []
        form.ave_monthly_salary = farmer.ave_monthly_salary || 0

        // Management Information
        form.is_vermicomposting = farmer.is_vermicomposting || false
        form.is_daily_spread = farmer.is_daily_spread || false
        form.is_storage_bulking = farmer.is_storage_bulking || false
        form.is_liquid_slurry = farmer.is_liquid_slurry || false
        form.is_anaerobic_digester = farmer.is_anaerobic_digester || false
        form.staff_count_male = farmer.staff_count_male ?? farmer.staff_count ?? 0
        form.staff_count_female = farmer.staff_count_female || 0
        form.gps_latitude = farmer.address?.latitude ?? farmer.gps_latitude ?? ''
        form.gps_longitude = farmer.address?.longitude ?? farmer.gps_longitude ?? ''
        form.pcc_assets_latitude = farmer.pcc_assets_latitude ?? ''
        form.pcc_assets_longitude = farmer.pcc_assets_longitude ?? ''
        form.status_google_map = farmer.status_google_map || ''
        form.google_map_link = farmer.google_map_link || ''
        form.geotag_image = farmer.geotag_image || ''
        form.geotag_image_file = null
        form.entrepreneur_image = farmer.entrepreneur_image || ''
        form.entrepreneur_image_file = null
        form.enterprise_image = farmer.enterprise_image || ''
        form.enterprise_image_file = null

        form.position = farmer.position || ''
        form.year_involvement_in_business = farmer.year_involvement_in_business ?? ''
        form.family_member_attending_school_count =
          farmer.family_member_attending_school_count ?? ''
        form.female_count = farmer.female_count ?? ''
        form.male_count = farmer.male_count ?? ''
        form.is_pwd = farmer.is_pwd === true || farmer.is_pwd === 1
        form.is_4ps = farmer.is_4ps === true || farmer.is_4ps === 1
        form.is_ips = farmer.is_ips === true || farmer.is_ips === 1
        form.is_indigent = farmer.is_indigent === true || farmer.is_indigent === 1
        form.indigenous_group = farmer.indigenous_group || ''
        form.educational_attainment = farmer.educational_attainment || ''
        form.type_of_entrepreneur = farmer.type_of_entrepreneur || ''

        form.rsbsa_cert_status = farmer.rsbsa_cert_status || ''
        form.has_rsbsa = toBooleanLike(farmer.has_rsbsa)
        form.rsbsa_certificate_of_registration = farmer.rsbsa_certificate_of_registration || ''
        form.if_producer_please_specify = farmer.if_producer_please_specify || ''
        form.involved_in_buffalo_farming = farmer.involved_in_buffalo_farming || false
        form.how_many_in_family_are_involved_in_buffalo_farming =
          farmer.how_many_in_family_are_involved_in_buffalo_farming ?? ''
        form.are_you_the_head_of_the_family = farmer.are_you_the_head_of_the_family || false
        form.if_no_highest_educational_attainment_of_head_of_family =
          farmer.if_no_highest_educational_attainment_of_head_of_family || ''
        form.challenges_or_problems_encountered_in_dairy_farming =
          farmer.challenges_or_problems_encountered_in_dairy_farming || ''
        form.membership_in_organization = farmer.membership_in_organization || ''
        form.name_of_organization = farmer.name_of_organization || ''
        form.tv_type = farmer.tv_type || ''

        form.additional_details = farmer.metadata || {}

        // Normalize boolean-like metadata values for checkbox sections.
        form.additional_details.isdugwell = toBooleanLike(form.additional_details.isdugwell)
        form.additional_details.isownwaterline = toBooleanLike(
          form.additional_details.isownwaterline
        )
        form.additional_details.issharedwaterline = toBooleanLike(
          form.additional_details.issharedwaterline
        )
        form.additional_details.isowndeepwell = toBooleanLike(form.additional_details.isowndeepwell)
        form.additional_details.isshareddeepwell = toBooleanLike(
          form.additional_details.isshareddeepwell
        )
        form.additional_details.isothers = toBooleanLike(form.additional_details.isothers)
        form.additional_details.isaccesstoelectricity = toYesNoValue(
          form.additional_details.isaccesstoelectricity
        )
        form.additional_details.isownrefrigerator = toYesNoValue(
          form.additional_details.isownrefrigerator
        )
        form.additional_details.isownwashingmachine = toYesNoValue(
          form.additional_details.isownwashingmachine
        )
        form.additional_details.isowntelevision = toYesNoValue(
          form.additional_details.isowntelevision
        )
        form.additional_details.isownradio = toYesNoValue(form.additional_details.isownradio)

        if (form.additional_details.animal_shed) {
          const rawAvailable = form.additional_details.animal_shed.available
          if (typeof rawAvailable === 'string' && rawAvailable !== '') {
            form.additional_details.animal_shed.available = [rawAvailable]
          } else if (typeof rawAvailable === 'boolean') {
            form.additional_details.animal_shed.available = rawAvailable
              ? ['Available']
              : ['Not available']
          }
        }

        // Normalize feed item boolean values
        feedItems.forEach(item => {
          form.additional_details[`israiny${item}`] = toBooleanLike(
            form.additional_details[`israiny${item}`]
          )
          form.additional_details[`isdry${item}`] = toBooleanLike(
            form.additional_details[`isdry${item}`]
          )
        })

        if (geotagBlobUrl.value) {
          window.URL.revokeObjectURL(geotagBlobUrl.value)
          geotagBlobUrl.value = null
        }
        geotagImageFile.value = null
        form.geotag_image_file = null
        geotagImagePreview.value = farmer.geotag_image_url || null
        if (entrepreneurBlobUrl.value) {
          window.URL.revokeObjectURL(entrepreneurBlobUrl.value)
          entrepreneurBlobUrl.value = null
        }
        entrepreneurImageFile.value = null
        form.entrepreneur_image_file = null
        entrepreneurImagePreview.value = farmer.entrepreneur_image_url || null
        if (enterpriseBlobUrl.value) {
          window.URL.revokeObjectURL(enterpriseBlobUrl.value)
          enterpriseBlobUrl.value = null
        }
        enterpriseImageFile.value = null
        form.enterprise_image_file = null
        enterpriseImagePreview.value = farmer.enterprise_image_url || null

        form.clearErrors()
      })
    } else if (!open) {
      form.reset()
      if (geotagBlobUrl.value) {
        window.URL.revokeObjectURL(geotagBlobUrl.value)
        geotagBlobUrl.value = null
      }
      geotagImageFile.value = null
      geotagImagePreview.value = null
      if (entrepreneurBlobUrl.value) {
        window.URL.revokeObjectURL(entrepreneurBlobUrl.value)
        entrepreneurBlobUrl.value = null
      }
      entrepreneurImageFile.value = null
      entrepreneurImagePreview.value = null
      if (enterpriseBlobUrl.value) {
        window.URL.revokeObjectURL(enterpriseBlobUrl.value)
        enterpriseBlobUrl.value = null
      }
      enterpriseImageFile.value = null
      enterpriseImagePreview.value = null
    }
  },
  { immediate: true }
)

const getTitle = () => {
  const titles = {
    [EDIT_TYPES.personal]: 'Edit Personal Information',
    [EDIT_TYPES.farming]: 'Edit Farming Information',
    [EDIT_TYPES.livestock]: 'Edit Livestock Information',
    [EDIT_TYPES.animalInventory]: 'Edit Animal Inventory',
    [EDIT_TYPES.income]: 'Edit Income Information',
    [EDIT_TYPES.feed]: 'Edit Feed Management',
    [EDIT_TYPES.manure]: 'Edit Manure Management',
    [EDIT_TYPES.water]: 'Edit Water Source',
    [EDIT_TYPES.poverty]: 'Edit Poverty Index',
    [EDIT_TYPES.geotag]: 'Edit GEOTAG',
    [EDIT_TYPES.socio]: 'Edit Socio-Economic',
    [EDIT_TYPES.rsbsa]: 'Edit RSBSA & Production',
  }
  const base = titles[editType.value] || 'Farmer Information'
  if (isCreateMode.value) {
    const hasDraft = !!createDrafts[modalState.value?.type]
    return `${hasDraft ? 'Edit' : 'Add'} ${base.replace(/^Edit /, '')}`
  }
  return base
}

const close = () => {
  modalState.value = { open: false, type: null, id: null }
}

const submit = () => {
  const isAssetEdit = [EDIT_TYPES.assets, EDIT_TYPES.geotag].includes(editType.value)
  const hasGeotagFile = isBrowserFile(geotagImageFile.value)
  const hasEntrepreneurFile = isBrowserFile(entrepreneurImageFile.value)
  const hasEnterpriseFile = isBrowserFile(enterpriseImageFile.value)
  const hasAnyFile = hasGeotagFile || hasEntrepreneurFile || hasEnterpriseFile

  const payload = {
    _method: 'PUT',
    ...form.data(),
    management_system: form.management_system === '' ? null : form.management_system,
    feeding_management: form.feeding_management === '' ? null : form.feeding_management,
    animal_ownership: animalOwnershipPayload(form.animal_ownership),
    farm_name: form.farm_name === '' ? null : form.farm_name,
    // Convert PSGC codes back to names for database storage
    farm_region: getRegionNameByCode(form.farm_region) || form.farm_region || null,
    farm_province: getProvinceNameByCode(form.farm_province) || form.farm_province || null,
    farm_municipality_city:
      getCityNameByCode(form.farm_municipality_city) || form.farm_municipality_city || null,
    farm_barangay: getBarangayNameByCode(form.farm_barangay) || form.farm_barangay || null,
    farm_purok_zone: form.farm_purok_zone === '' ? null : form.farm_purok_zone,
    farm_district: form.farm_district === '' ? null : form.farm_district,
    farm_street: form.farm_street === '' ? null : form.farm_street,
    proposed_farm_name: form.proposed_farm_name === '' ? null : form.proposed_farm_name,
    type_of_farm_business_registration: form.type_of_farm_business_registration.length
      ? form.type_of_farm_business_registration
      : null,
    status_of_registration: form.status_of_registration === '' ? null : form.status_of_registration,
    farm_registration_number:
      form.farm_registration_number === '' ? null : form.farm_registration_number,
    has_rsbsa: form.has_rsbsa,
    year_farm_established: form.year_farm_established === '' ? null : form.year_farm_established,
    pcc_projects_assets: form.pcc_projects_assets === '' ? null : form.pcc_projects_assets,
    pcc_assets_latitude: form.pcc_assets_latitude === '' ? null : form.pcc_assets_latitude,
    pcc_assets_longitude: form.pcc_assets_longitude === '' ? null : form.pcc_assets_longitude,
    other_livestock_details: form.other_livestock_details.length
      ? form.other_livestock_details
      : null,
    source_of_income_details: form.source_of_income_details.length
      ? form.source_of_income_details
      : null,
    animal_inventory: animalInventoryPayload(form.animal_inventory),
    additional_details: form.additional_details || {},
    // Convert address PSGC codes back to names
    region: getRegionNameByCode(form.region) || form.region || null,
    province: getProvinceNameByCode(form.province) || form.province || null,
    municipality: getCityNameByCode(form.municipality) || form.municipality || null,
    barangay: getBarangayNameByCode(form.barangay) || form.barangay || null,
    ...(hasGeotagFile && { geotag_image_file: geotagImageFile.value }),
    ...(hasEntrepreneurFile && { entrepreneur_image_file: entrepreneurImageFile.value }),
    ...(hasEnterpriseFile && { enterprise_image_file: enterpriseImageFile.value }),
  }

  if (!isAssetEdit) {
    delete payload.status_google_map
    delete payload.google_map_link
    delete payload.geotag_image
    delete payload.entrepreneur_image
    delete payload.enterprise_image
  }

  if (isCreateMode.value) {
    delete payload._method
    createDrafts[modalState.value.type] = pruneDraftPayload(payload)
    toastFlash({ success: 'Draft saved.' })
    close()
    return
  }

  router.post(`/farmers/${props.farmer.id}`, payload, {
    preserveScroll: true,
    forceFormData: hasAnyFile,
    onSuccess: () => {
      close()
      router.reload({ only: ['farmer'] })
    },
    onError: errors => {
      Object.entries(errors).forEach(([field, msg]) => toastFlash({ error: `${field}: ${msg}` }))
    },
  })
}
</script>

<template>
  <Dialog :open="isOpen" @update:open="val => !val && close()">
    <DialogContent class="flex h-fit max-h-[90vh] w-[95vw] max-w-4xl flex-col overflow-hidden p-0">
      <DialogHeader class="shrink-0 border-b p-6">
        <DialogTitle>{{ getTitle() }}</DialogTitle>
        <DialogDescription>{{
          isCreateMode
            ? 'Fill in the farmer information below and save it as a draft.'
            : 'Update the farmer information below.'
        }}</DialogDescription>
      </DialogHeader>

      <form v-if="isOpen" class="flex flex-col overflow-hidden" @submit.prevent="submit">
        <div class="max-h-[60vh] overflow-y-auto p-6">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Personal Information -->
            <template v-if="editType === EDIT_TYPES.personal">
              <div class="space-y-2">
                <Label>First Name</Label>
                <Input v-model="form.first_name" required />
              </div>
              <div class="space-y-2">
                <Label>Middle Name</Label>
                <Input v-model="form.middle_name" />
              </div>
              <div class="space-y-2">
                <Label>Last Name</Label>
                <Input v-model="form.last_name" required />
              </div>
              <div class="space-y-2">
                <Label>Extension Name</Label>
                <Input v-model="form.extension_name" />
              </div>
              <div class="space-y-2">
                <Label>Nickname</Label>
                <Input v-model="form.nickname" />
              </div>
              <div class="space-y-2">
                <Label>Gender</Label>
                <Select v-model="form.gender">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Male">Male</SelectItem>
                    <SelectItem value="Female">Female</SelectItem>
                    <SelectItem value="Other">Other</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Date of Interview</Label>
                <Input v-model="form.date_of_interview" type="date" />
              </div>
              <div class="space-y-2">
                <Label>Age</Label>
                <Input v-model.number="form.age" type="number" />
              </div>
              <div class="space-y-2">
                <Label>Birthday</Label>
                <Input v-model="form.birthday" type="date" />
              </div>
              <div class="space-y-2">
                <Label>Civil Status</Label>
                <Select v-model="form.civil_status">
                  <SelectTrigger><SelectValue placeholder="Select civil status" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(label, key) in enums.civilStatus" :key="key" :value="key">
                      {{ label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Spouse Name</Label>
                <Input v-model="form.spouse_name" />
              </div>
              <div class="space-y-2">
                <Label>Household Size</Label>
                <Input v-model.number="form.household_size" type="number" min="0" />
              </div>
              <div class="space-y-2">
                <Label>Contact Number</Label>
                <Input v-model="form.contact_number" type="tel" />
              </div>

              <div class="space-y-2">
                <Label>Has Personal Cellphone</Label>
                <Select v-model="form.has_personal_cellphone">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="true">Yes</SelectItem>
                    <SelectItem :value="false">No</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>PWD Member</Label>
                <Select v-model="form.is_pwd">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="true">Yes</SelectItem>
                    <SelectItem :value="false">No</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>4Ps Member</Label>
                <Select v-model="form.is_4ps">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="true">Yes</SelectItem>
                    <SelectItem :value="false">No</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>IPS Member</Label>
                <Select v-model="form.is_ips">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="true">Yes</SelectItem>
                    <SelectItem :value="false">No</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </template>

            <!-- Address Information -->
            <template v-if="editType === EDIT_TYPES.personal">
              <div class="space-y-2">
                <Label>Street</Label>
                <Input v-model="form.street" />
              </div>
              <div class="space-y-2">
                <Label>Region</Label>
                <Select v-model="form.region">
                  <SelectTrigger><SelectValue placeholder="Select region" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="region in regionsData"
                      :key="region.code"
                      :value="region.code"
                    >
                      {{ region.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Province</Label>
                <Select v-model="form.province" :disabled="!form.region">
                  <SelectTrigger><SelectValue placeholder="Select province" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="province in addressProvinces"
                      :key="province.code"
                      :value="province.code"
                    >
                      {{ province.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Municipality/City</Label>
                <Select
                  v-model="form.municipality"
                  :disabled="!form.province && addressRegionHasProvinces"
                >
                  <SelectTrigger
                    ><SelectValue placeholder="Select municipality/city"
                  /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="city in addressCities" :key="city.code" :value="city.code">
                      {{ city.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Barangay</Label>
                <Select v-model="form.barangay" :disabled="!form.municipality">
                  <SelectTrigger><SelectValue placeholder="Select barangay" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="barangay in addressBarangays"
                      :key="barangay.code"
                      :value="barangay.code"
                    >
                      {{ barangay.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </template>

            <!-- Farming Information -->
            <template v-if="editType === EDIT_TYPES.farming">
              <div class="col-span-1 space-y-6 md:col-span-2">
                <FarmInformationTab
                  :form="form"
                  :enums="enums"
                  :farm-barangays="farmBarangays"
                  :farm-cities="farmCities"
                  :farm-provinces="farmProvinces"
                  :regions-data="regionsData"
                  :farm-region-has-provinces="farmRegionHasProvinces"
                  :on-set-group-detail="setGroupDetail"
                  :on-toggle-group-value="toggleGroupValue"
                  :on-has-group-value="hasGroupValue"
                />
              </div>
            </template>

            <!-- Livestock Information -->
            <template v-if="editType === EDIT_TYPES.livestock">
              <LivestockTab :form="form" />
            </template>

            <!-- Animal Inventory -->
            <template v-if="editType === EDIT_TYPES.animalInventory">
              <div class="col-span-1 space-y-6 md:col-span-2">
                <AnimalInventoryTab :form="form" />
              </div>
            </template>

            <!-- Sources of Income -->
            <template v-if="editType === EDIT_TYPES.income">
              <SourcesOfIncomeTab :form="form" />
            </template>

            <!-- Feed Management -->
            <template v-if="editType === EDIT_TYPES.feed">
              <FeedManagementTab :form="form" />
            </template>

            <!-- Waste Management -->
            <template v-if="editType === EDIT_TYPES.manure">
              <WasteManagementTab :form="form" />
            </template>

            <template v-if="editType === EDIT_TYPES.water">
              <div class="col-span-1 space-y-6 md:col-span-2">
                <div class="space-y-3 rounded-lg border p-4">
                  <h4 class="text-base font-semibold">Water Source</h4>
                  <div class="space-y-2">
                    <label
                      class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 transition-colors hover:bg-gray-100"
                    >
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-400"
                        :checked="!!form.additional_details?.isdugwell"
                        @change="setAdditionalDetail('isdugwell', $event.target.checked)"
                      />
                      <span class="text-sm">Dug well with or without pump?</span>
                    </label>
                    <label
                      class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 transition-colors hover:bg-gray-100"
                    >
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-400"
                        :checked="!!form.additional_details?.isownwaterline"
                        @change="setAdditionalDetail('isownwaterline', $event.target.checked)"
                      />
                      <span class="text-sm">Own waterline like Maynilad or MWSS?</span>
                    </label>
                    <label
                      class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 transition-colors hover:bg-gray-100"
                    >
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-400"
                        :checked="!!form.additional_details?.issharedwaterline"
                        @change="setAdditionalDetail('issharedwaterline', $event.target.checked)"
                      />
                      <span class="text-sm">Shared water line like Maynilad or MWSS?</span>
                    </label>
                    <label
                      class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 transition-colors hover:bg-gray-100"
                    >
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-400"
                        :checked="!!form.additional_details?.isowndeepwell"
                        @change="setAdditionalDetail('isowndeepwell', $event.target.checked)"
                      />
                      <span class="text-sm">Own deep well with a hand or motor pump?</span>
                    </label>
                    <label
                      class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 transition-colors hover:bg-gray-100"
                    >
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-400"
                        :checked="!!form.additional_details?.isshareddeepwell"
                        @change="setAdditionalDetail('isshareddeepwell', $event.target.checked)"
                      />
                      <span class="text-sm">Shared deep well with a hand or motor pump?</span>
                    </label>
                    <label
                      class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 transition-colors hover:bg-gray-100"
                    >
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-400"
                        :checked="!!form.additional_details?.isothers"
                        @change="setAdditionalDetail('isothers', $event.target.checked)"
                      />
                      <span class="text-sm">Others?</span>
                    </label>
                    <div v-if="!!form.additional_details?.isothers" class="pt-1 pl-7">
                      <input
                        type="text"
                        class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-xs focus:border-gray-400 focus:ring-1 focus:ring-gray-400 focus:outline-none"
                        placeholder="Other Remarks"
                        :value="form.additional_details?.otherremarks || ''"
                        @input="setAdditionalDetail('otherremarks', $event.target.value)"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <!-- Poverty Index -->
            <template v-if="editType === EDIT_TYPES.poverty">
              <PovertyIndexTab
                :form="form"
                :enums="enums"
                :on-set-additional-detail="setAdditionalDetail"
                :on-set-tv-type="setTvType"
                :on-to-boolean-like="toBooleanLike"
              />
            </template>

            <!-- Assets -->
            <template v-if="editType === EDIT_TYPES.assets">
              <div class="col-span-1 space-y-6 md:col-span-2">
                <div class="space-y-4 rounded-lg border p-4">
                  <h4 class="text-foreground text-base font-semibold">PCC Projects</h4>
                  <div class="space-y-2">
                    <Label>PCC Projects Assets</Label>
                    <Input v-model="form.pcc_projects_assets" />
                  </div>
                </div>
                <div class="space-y-4 rounded-lg border p-4">
                  <h4 class="text-foreground text-base font-semibold">Google Map Information</h4>
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                      <Label>Status of Google Map Registration</Label>
                      <Input v-model="form.status_google_map" type="text" />
                    </div>
                    <div class="space-y-2">
                      <Label>Google Maps Link</Label>
                      <Input
                        v-model="form.google_map_link"
                        type="url"
                        placeholder="https://maps.google.com/..."
                      />
                    </div>
                  </div>
                </div>
                <div class="space-y-4 rounded-lg border p-4">
                  <h4 class="text-foreground text-base font-semibold">GPS Location</h4>
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                      <Label>Gps Location Latitude</Label>
                      <Input v-model="form.pcc_assets_latitude" type="text" />
                    </div>
                    <div class="space-y-2">
                      <Label>Gps Location Longitude</Label>
                      <Input v-model="form.pcc_assets_longitude" type="text" />
                    </div>
                  </div>
                </div>
                <div class="space-y-4 rounded-lg border p-4">
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                      <Label>Geotag Image</Label>
                      <div class="flex items-center gap-2">
                        <input
                          ref="geotagFileInput"
                          type="file"
                          accept="image/*"
                          class="hidden"
                          @change="handleGeotagFileChange"
                        />
                        <Button type="button" variant="outline" @click="geotagFileInput?.click()">
                          Upload Image
                        </Button>
                        <Button
                          v-if="geotagImageFile || geotagImagePreview"
                          type="button"
                          variant="destructive"
                          size="sm"
                          @click="handleGeotagRemove"
                        >
                          Remove
                        </Button>
                        <span v-if="geotagImageFile" class="text-muted-foreground text-sm">
                          File selected
                        </span>
                      </div>
                    </div>
                    <div class="flex items-center justify-center rounded-lg border bg-gray-50 p-4">
                      <img
                        v-if="geotagImagePreview"
                        :src="geotagImagePreview"
                        alt="Geotag Preview"
                        class="max-h-48 max-w-full rounded object-contain"
                      />
                      <span v-else class="text-sm text-slate-400">No image selected</span>
                    </div>
                  </div>
                </div>
                <div class="space-y-4 rounded-lg border p-4">
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                      <Label>Entrepreneur Image</Label>
                      <div class="flex items-center gap-2">
                        <input
                          ref="entrepreneurFileInput"
                          type="file"
                          accept="image/*"
                          class="hidden"
                          @change="handleEntrepreneurFileChange"
                        />
                        <Button
                          type="button"
                          variant="outline"
                          @click="entrepreneurFileInput?.click()"
                        >
                          Upload Image
                        </Button>
                        <Button
                          v-if="entrepreneurImageFile || entrepreneurImagePreview"
                          type="button"
                          variant="destructive"
                          size="sm"
                          @click="handleEntrepreneurRemove"
                        >
                          Remove
                        </Button>
                        <span v-if="entrepreneurImageFile" class="text-muted-foreground text-sm">
                          File selected
                        </span>
                      </div>
                    </div>
                    <div class="flex items-center justify-center rounded-lg border bg-gray-50 p-4">
                      <img
                        v-if="entrepreneurImagePreview"
                        :src="entrepreneurImagePreview"
                        alt="Entrepreneur Preview"
                        class="max-h-48 max-w-full rounded object-contain"
                      />
                      <span v-else class="text-sm text-slate-400">No image selected</span>
                    </div>
                  </div>
                </div>
                <div class="space-y-4 rounded-lg border p-4">
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                      <Label>Enterprise Image</Label>
                      <div class="flex items-center gap-2">
                        <input
                          ref="enterpriseFileInput"
                          type="file"
                          accept="image/*"
                          class="hidden"
                          @change="handleEnterpriseFileChange"
                        />
                        <Button
                          type="button"
                          variant="outline"
                          @click="enterpriseFileInput?.click()"
                        >
                          Upload Image
                        </Button>
                        <Button
                          v-if="enterpriseImageFile || enterpriseImagePreview"
                          type="button"
                          variant="destructive"
                          size="sm"
                          @click="handleEnterpriseRemove"
                        >
                          Remove
                        </Button>
                        <span v-if="enterpriseImageFile" class="text-muted-foreground text-sm">
                          File selected
                        </span>
                      </div>
                    </div>
                    <div class="flex items-center justify-center rounded-lg border bg-gray-50 p-4">
                      <img
                        v-if="enterpriseImagePreview"
                        :src="enterpriseImagePreview"
                        alt="Enterprise Preview"
                        class="max-h-48 max-w-full rounded object-contain"
                      />
                      <span v-else class="text-sm text-slate-400">No image selected</span>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <!-- Socio-Economic -->
            <template v-if="editType === EDIT_TYPES.socio">
              <SocioEconomicTab :form="form" :enums="enums" />
            </template>

            <!-- RSBSA & Production -->
            <template v-if="editType === EDIT_TYPES.rsbsa">
              <div class="col-span-1 space-y-6 md:col-span-2">
                <div class="space-y-4 rounded-lg border p-4">
                  <h4 class="text-foreground text-base font-semibold">
                    RSBSA & Production Information
                  </h4>
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                      <Label>RSBSA Cert Status</Label>
                      <Select v-model="form.rsbsa_cert_status">
                        <SelectTrigger><SelectValue placeholder="Select status" /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="registered">Registered</SelectItem>
                          <SelectItem value="not_registered">Not Registered</SelectItem>
                          <SelectItem value="pending">Pending</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div class="space-y-2">
                      <Label>RSBSA Certificate of Registration</Label>
                      <Input v-model="form.rsbsa_certificate_of_registration" />
                    </div>
                    <div class="space-y-2">
                      <Label>If Producer, Please Specify</Label>
                      <Input v-model="form.if_producer_please_specify" />
                    </div>
                    <div class="col-span-1 space-y-2 md:col-span-2">
                      <Label>Challenges or Problems Encountered in Dairy Farming</Label>
                      <textarea
                        v-model="form.challenges_or_problems_encountered_in_dairy_farming"
                        rows="3"
                        class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-xs focus:border-gray-400 focus:ring-1 focus:ring-gray-400 focus:outline-none"
                      ></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <!-- GEOTAG (backward compatibility) -->
            <template v-if="editType === EDIT_TYPES.geotag">
              <div class="space-y-2 md:col-span-2">
                <Label>Status of Google Map Registration</Label>
                <Input v-model="form.status_google_map" type="text" />
              </div>
              <div class="space-y-2 md:col-span-2">
                <Label>Google Maps Link</Label>
                <Input
                  v-model="form.google_map_link"
                  type="url"
                  placeholder="https://maps.google.com/..."
                />
              </div>
              <div class="space-y-2">
                <Label>Gps Location Latitude</Label>
                <Input v-model="form.gps_latitude" type="text" />
              </div>
              <div class="space-y-2">
                <Label>Gps Location Longitude</Label>
                <Input v-model="form.gps_longitude" type="text" />
              </div>
              <div class="space-y-2 md:col-span-2">
                <Label>Geotag Image</Label>
                <ImageUploadField
                  label="Geotag Image"
                  :model-value="geotagImagePreview"
                  @update:model-value="
                    val => {
                      if (val) {
                        form.geotag_image = val
                        geotagImagePreview.value = val
                      }
                    }
                  "
                />
                <div class="mt-2">
                  <Label class="text-xs text-slate-500">Or paste a URL/path</Label>
                  <Input v-model="form.geotag_image" type="text" placeholder="Filename or URL" />
                </div>
              </div>
            </template>
          </div>
        </div>

        <DialogFooter class="bg-muted/20 shrink-0 border-t p-6">
          <Button type="button" variant="outline" @click="close">Cancel</Button>
          <Button
            type="submit"
            :disabled="form.processing"
            class="bg-black text-white hover:bg-black/90"
          >
            {{ form.processing ? 'Saving...' : 'Save Changes' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
