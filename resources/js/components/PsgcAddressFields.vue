<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { usePsgcAddress } from '@/composable/usePsgcAddress'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const props = defineProps({
  modelValue: { type: Object, required: true },
  isViewMode: { type: Boolean, default: false },
  cityField: { type: String, default: 'city' },
  regionLabel: { type: String, default: 'Region' },
  provinceLabel: { type: String, default: 'Province' },
  cityLabel: { type: String, default: 'City/Municipality' },
  districtLabel: { type: String, default: 'District' },
  barangayLabel: { type: String, default: 'Barangay' },
  // 'name' -> select values are PSGC names; 'code' -> values are PSGC codes.
  valueMode: { type: String, default: 'name' },
  // District is rendered/auto-filled from the table-backed `districts`
  // source (same cascade as region -> province -> city). Set false when the
  // consuming form manages `district` itself (e.g. milk feeding programs).
  showDistrict: { type: Boolean, default: true },
  // Optional shared PSGC store. When provided, this component reuses the
  // caller's `usePsgcAddress()` instance (whose province/city/barangay lists
  // may already be populated) instead of creating its own.
  psgc: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const initializing = ref(true)

const isCodeMode = computed(() => props.valueMode === 'code')

const regionCodeFor = value =>
  isCodeMode.value ? value : regions.value.find(r => r.name === value)?.code
const provinceCodeFor = value =>
  isCodeMode.value ? value : provinces.value.find(p => p.name === value)?.code
const cityCodeFor = value =>
  isCodeMode.value ? value : cities.value.find(c => c.name === value)?.code

const regionDisplay = value => (isCodeMode.value ? getRegionNameByCode(value) || value : value)
const provinceDisplay = value => (isCodeMode.value ? getProvinceNameByCode(value) || value : value)
const cityDisplay = value => (isCodeMode.value ? getCityNameByCode(value) || value : value)
const barangayDisplay = value => (isCodeMode.value ? getBarangayNameByCode(value) || value : value)

const psgc = props.psgc || usePsgcAddress()
const {
  regions,
  provinces,
  cities,
  barangays,
  districts,
  load,
  fetchProvinces,
  fetchCities,
  fetchBarangays,
  hasProvincesInRegion,
  getRegionNameByCode,
  getProvinceNameByCode,
  getCityNameByCode,
  getBarangayNameByCode,
  fetchDistricts,
} = psgc

const districtDisplay = value => {
  if (!value) return ''
  const d = districts.value.find(x => x.code === value || x.name === value)
  return d?.name || value
}

// The district table holds exactly one row per city, so once the city is
// known we can auto-fill the district value, just like the other cascade
// fields get resolved from table data. The stored value is the district's
// `name` label (e.g. "1st District"/"Lone District") in both value modes,
// because downstream exports/reports render that column as a human label.
function applyDistrictForCity(cityCode) {
  if (!cityCode || !props.showDistrict) return
  const d = districts.value.find(x => x.city_code === cityCode)
  if (!d) return
  updateField('district', d.name)
}

const addressRegionHasProvinces = computed(() => {
  if (!props.modelValue.region) return false
  const regionCode = regionCodeFor(props.modelValue.region)
  return regionCode ? hasProvincesInRegion(regionCode) : false
})

function updateField(field, value) {
  emit('update:modelValue', { ...props.modelValue, [field]: value })
}

watch(
  () => props.modelValue.region,
  async (region, oldRegion) => {
    if (initializing.value) return
    if (!region) return
    if (oldRegion && region !== oldRegion) {
      updateField('province', '')
      updateField(props.cityField, '')
      updateField('barangay', '')
    }
    const regionCode = regionCodeFor(region)
    if (regionCode) {
      fetchProvinces(regionCode)
      if (!hasProvincesInRegion(regionCode)) {
        await fetchCities({ regionCode })
      }
    }
  }
)

watch(
  () => props.modelValue.province,
  async (province, oldProvince) => {
    if (initializing.value) return
    if (!province) return
    if (oldProvince && province !== oldProvince) {
      updateField(props.cityField, '')
      updateField('barangay', '')
    }
    const provinceCode = provinceCodeFor(province)
    if (provinceCode) {
      await fetchCities({ provinceCode })
    }
  }
)

watch(
  () => props.modelValue[props.cityField],
  async (city, oldCity) => {
    if (initializing.value) return
    if (!city) {
      updateField('barangay', '')
      if (props.showDistrict) updateField('district', '')
      return
    }
    if (oldCity && city !== oldCity) {
      updateField('barangay', '')
    }
    const cityCode = cityCodeFor(city)
    if (cityCode) {
      await fetchBarangays(cityCode)
      await fetchDistricts(cityCode)
      applyDistrictForCity(cityCode)
    }
  }
)

onMounted(async () => {
  await load()

  const region = props.modelValue.region
  const province = props.modelValue.province
  const city = props.modelValue[props.cityField]

  if (region) {
    const regionCode = regionCodeFor(region)
    if (regionCode) {
      fetchProvinces(regionCode)

      let cityCode = ''
      if (province) {
        const provinceCode = provinceCodeFor(province)
        if (provinceCode) {
          await fetchCities({ provinceCode })
        }
        cityCode = city ? cityCodeFor(city) : ''
      } else if (!hasProvincesInRegion(regionCode)) {
        await fetchCities({ regionCode })
        cityCode = city ? cityCodeFor(city) : ''
      }

      if (cityCode) {
        await fetchBarangays(cityCode)
        await fetchDistricts(cityCode)
        applyDistrictForCity(cityCode)
      }
    }
  }

  initializing.value = false
})
</script>

<template>
  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="space-y-2">
      <Label>{{ regionLabel }}</Label>
      <Select
        v-if="!isViewMode"
        :model-value="modelValue.region"
        @update:model-value="updateField('region', $event)"
      >
        <SelectTrigger class="h-9 w-full">
          <SelectValue placeholder="Select region" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="r in regions" :key="r.code" :value="isCodeMode ? r.code : r.name">
            {{ r.name }}
          </SelectItem>
          <SelectItem
            v-if="
              modelValue.region &&
              !regions.some(r => (isCodeMode ? r.code : r.name) === modelValue.region)
            "
            :value="modelValue.region"
          >
            {{ regionDisplay(modelValue.region) }}
          </SelectItem>
        </SelectContent>
      </Select>
      <div v-else class="rounded bg-gray-50 p-2 text-sm">
        {{ regionDisplay(modelValue.region) || 'N/A' }}
      </div>
    </div>

    <div class="space-y-2">
      <Label>{{ provinceLabel }}</Label>
      <Select
        v-if="!isViewMode"
        :model-value="modelValue.province"
        :disabled="!modelValue.region || !addressRegionHasProvinces"
        @update:model-value="updateField('province', $event)"
      >
        <SelectTrigger class="h-9 w-full">
          <SelectValue placeholder="Select province" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="p in provinces" :key="p.code" :value="isCodeMode ? p.code : p.name">
            {{ p.name }}
          </SelectItem>
          <SelectItem
            v-if="
              modelValue.province &&
              !provinces.some(p => (isCodeMode ? p.code : p.name) === modelValue.province)
            "
            :value="modelValue.province"
          >
            {{ provinceDisplay(modelValue.province) }}
          </SelectItem>
        </SelectContent>
      </Select>
      <div v-else class="rounded bg-gray-50 p-2 text-sm">
        {{ provinceDisplay(modelValue.province) || 'N/A' }}
      </div>
    </div>

    <div class="space-y-2">
      <Label>{{ cityLabel }}</Label>
      <Select
        v-if="!isViewMode"
        :model-value="modelValue[cityField]"
        :disabled="addressRegionHasProvinces ? !modelValue.province : !modelValue.region"
        @update:model-value="updateField(cityField, $event)"
      >
        <SelectTrigger class="h-9 w-full">
          <SelectValue placeholder="Select city/municipality" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="c in cities" :key="c.code" :value="isCodeMode ? c.code : c.name">
            {{ c.name }}
          </SelectItem>
          <SelectItem
            v-if="
              modelValue[cityField] &&
              !cities.some(c => (isCodeMode ? c.code : c.name) === modelValue[cityField])
            "
            :value="modelValue[cityField]"
          >
            {{ cityDisplay(modelValue[cityField]) }}
          </SelectItem>
        </SelectContent>
      </Select>
      <div v-else class="rounded bg-gray-50 p-2 text-sm">
        {{ cityDisplay(modelValue[cityField]) || 'N/A' }}
      </div>
    </div>

    <div v-if="showDistrict" class="space-y-2">
      <Label>{{ districtLabel }}</Label>
      <Select
        v-if="!isViewMode"
        :model-value="modelValue.district"
        :disabled="!modelValue[cityField]"
        @update:model-value="updateField('district', $event)"
      >
        <SelectTrigger class="h-9 w-full">
          <SelectValue placeholder="Select district" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="d in districts" :key="d.code" :value="d.name">
            {{ d.name }}
          </SelectItem>
          <SelectItem
            v-if="modelValue.district && !districts.some(d => d.name === modelValue.district)"
            :value="modelValue.district"
          >
            {{ districtDisplay(modelValue.district) }}
          </SelectItem>
        </SelectContent>
      </Select>
      <div v-else class="rounded bg-gray-50 p-2 text-sm">
        {{ districtDisplay(modelValue.district) || 'N/A' }}
      </div>
    </div>

    <div class="space-y-2">
      <Label>{{ barangayLabel }}</Label>
      <Select
        v-if="!isViewMode"
        :model-value="modelValue.barangay"
        :disabled="!modelValue[cityField]"
        @update:model-value="updateField('barangay', $event)"
      >
        <SelectTrigger class="h-9 w-full">
          <SelectValue placeholder="Select barangay" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="b in barangays" :key="b.code" :value="isCodeMode ? b.code : b.name">
            {{ b.name }}
          </SelectItem>
          <SelectItem
            v-if="
              modelValue.barangay &&
              !barangays.some(b => (isCodeMode ? b.code : b.name) === modelValue.barangay)
            "
            :value="modelValue.barangay"
          >
            {{ barangayDisplay(modelValue.barangay) }}
          </SelectItem>
        </SelectContent>
      </Select>
      <div v-else class="rounded bg-gray-50 p-2 text-sm">
        {{ barangayDisplay(modelValue.barangay) || 'N/A' }}
      </div>
    </div>
  </div>
</template>
