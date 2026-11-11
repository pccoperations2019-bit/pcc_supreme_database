<script setup>
import { computed, onMounted, ref } from 'vue'
import { usePsgcAddress } from '@/composable/usePsgcAddress'
import { useFilterSync } from '@/composable/useFilterSync'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const DISPLAY_LIMIT = 20

const searchRegion = ref('')
const searchProvince = ref('')
const searchCity = ref('')
const searchDistrict = ref('')

const filteredRegions = computed(() => {
  const q = searchRegion.value.toLowerCase().trim()
  const base = q ? regions.value.filter(r => r.name.toLowerCase().includes(q)) : regions.value
  return base.slice(0, DISPLAY_LIMIT)
})
const filteredProvinces = computed(() => {
  const q = searchProvince.value.toLowerCase().trim()
  const base = q ? provinces.value.filter(p => p.name.toLowerCase().includes(q)) : provinces.value
  return base.slice(0, DISPLAY_LIMIT)
})
const filteredCities = computed(() => {
  const q = searchCity.value.toLowerCase().trim()
  const base = q ? cities.value.filter(c => c.name.toLowerCase().includes(q)) : cities.value
  return base.slice(0, DISPLAY_LIMIT)
})
const filteredDistricts = computed(() => {
  const q = searchDistrict.value.toLowerCase().trim()
  const base = q ? districts.value.filter(d => d.name.toLowerCase().includes(q)) : districts.value
  return base.slice(0, DISPLAY_LIMIT)
})

function onSearchKeydown(e) {
  e.stopPropagation()
}

const { regionLabel, provinceLabel, municipalityLabel, districtLabel } = defineProps({
  regionLabel: { type: String, default: 'Region' },
  provinceLabel: { type: String, default: 'Province' },
  municipalityLabel: { type: String, default: 'Municipality' },
  districtLabel: { type: String, default: 'District:' },
})

const psgc = usePsgcAddress()
const { pushPsgc } = useFilterSync()

const {
  regions,
  provinces,
  allProvinces,
  cities,
  districts,
  districtByCity,
  loadingDistricts,
  load,
  fetchProvinces,
  fetchCities,
  fetchDistricts,
  fetchAllDistricts,
  getDistrictByCityCode,
  getDistrictLabelByCity,
  hasProvincesInRegion,
  getRegionCodeByName,
  getRegionNameByCode,
  getProvinceCodeByName,
  getProvinceNameByCode,
  getCityCodeByName,
  getCityNameByCode,
} = psgc

// Selected PSGC names at each level of the cascade.
const region = ref('')
const province = ref('')
const municipality = ref('')
const district = ref('')

// Multi-select state for location filters
const selectedRegions = ref([])
const selectedProvinces = ref([])
const selectedMunicipalities = ref([])

// Guards the fetch/hydration flow; the change handlers write the URL exactly
// once per user interaction.
const hydrating = ref(false)

function regionCode(name) {
  if (!name || name === 'all') return undefined
  const region = regions.value.find(r => r.name === name)
  if (region) return region.code
  // Try with the composable's getRegionCodeByName for fuzzy matching
  return getRegionCodeByName(name)
}

const addressRegionHasProvinces = computed(() => {
  const code = regionCode(region.value)
  return code ? hasProvincesInRegion(code) : false
})

const provinceDisabled = computed(() => selectedRegions.value.length === 0)
const municipalityDisabled = computed(() => selectedProvinces.value.length === 0)
const districtDisabled = computed(() => selectedMunicipalities.value.length === 0)

function readUrl() {
  const url = new window.URL(window.location.href)
  const parseArrayValue = value => {
    if (!value) return []
    if (value === 'all') return ['all']
    return value
      .split(',')
      .map(v => v.trim())
      .filter(v => v)
  }

  return {
    region: parseArrayValue(url.searchParams.get('filter[region]')),
    province: parseArrayValue(url.searchParams.get('filter[province]')),
    city: parseArrayValue(url.searchParams.get('filter[city]')),
    district: url.searchParams.get('filter[district]') || '',
  }
}

function writeUrl(values) {
  // Single source via useFilterSync — guarantees PsgcAddressFilter and FiltersPanel share the same pushState contract
  pushPsgc(values)
}

async function hydrate() {
  const values = readUrl()
  hydrating.value = true
  try {
    const hasAny =
      (values.region && values.region.length > 0) ||
      (values.province && values.province.length > 0) ||
      (values.city && values.city.length > 0) ||
      !!values.district
    if (!hasAny) return

    // Immediately reflect URL in UI – fixes "refresh it not selected"
    if (values.region.length > 0) {
      selectedRegions.value = [...values.region]
      region.value = values.region.includes('all')
        ? 'all'
        : values.region.length === 1
          ? values.region[0]
          : values.region.join(', ')
    }
    if (values.province.length > 0) {
      selectedProvinces.value = [...values.province]
      province.value = values.province.includes('all')
        ? 'all'
        : values.province.length === 1
          ? values.province[0]
          : values.province.join(', ')
    }
    if (values.city.length > 0) {
      selectedMunicipalities.value = [...values.city]
      municipality.value = values.city.includes('all')
        ? 'all'
        : values.city.length === 1
          ? values.city[0]
          : values.city.join(', ')
    }
    if (values.district) {
      district.value = values.district
    }

    // Populate province options for display and stub missing entries
    if (values.region.includes('all')) {
      provinces.value = [...allProvinces.value]
    } else if (values.region.length > 0) {
      const regionCodes = values.region.map(r => getRegionCodeByName(r)).filter(Boolean)
      const list = []
      regionCodes.forEach(code => {
        list.push(...allProvinces.value.filter(p => p.region_code === code))
      })
      const seen = new Set()
      provinces.value = list.filter(p => !seen.has(p.code) && seen.add(p.code))
      if (values.province.length && !values.province.includes('all')) {
        values.province.forEach(name => {
          if (!provinces.value.some(p => p.name === name)) {
            provinces.value.push({ code: '', name, region_code: regionCodes[0] || '' })
          }
        })
      }
      // Regions without provinces (e.g. NCR) need cities by region
      const regionsWithoutProvinces = regionCodes.filter(c => !hasProvincesInRegion(c))
      if (regionsWithoutProvinces.length > 0 && !values.province.length) {
        // Only fetch if no province selected – otherwise cities are fetched via province
        await Promise.all(regionsWithoutProvinces.map(c => fetchCities({ regionCode: c })))
      }
    }

    // Fetch cities for first selected province only (avoid fetching all provinces' cities on hydrate)
    // Stub remaining cities so UI shows all selected values
    if (values.province.length > 0 && !values.province.includes('all')) {
      const firstProvinceCode = getProvinceCodeByName(values.province[0])
      if (firstProvinceCode) {
        await fetchCities({ provinceCode: firstProvinceCode })
      }
      if (values.city.length && !values.city.includes('all')) {
        values.city.forEach(name => {
          if (!cities.value.some(c => c.name === name)) {
            cities.value.push({ code: '', name, province_code: firstProvinceCode || '' })
          }
        })
      }
    } else if (
      values.province.includes('all') &&
      values.city.length &&
      !values.city.includes('all')
    ) {
      values.city.forEach(name => {
        if (!cities.value.some(c => c.name === name)) {
          cities.value.push({ code: '', name })
        }
      })
    }

    // Fetch district for first selected city and auto-fill if needed
    let districtAutoFilled = false
    if (values.city.length > 0 && !values.city.includes('all')) {
      const firstProvCode =
        values.province.length === 1 ? getProvinceCodeByName(values.province[0]) : ''
      const cityCode = getCityCodeByName(values.city[0], firstProvCode, '')
      if (cityCode) {
        await fetchDistricts(cityCode)
        if (!values.district) {
          const label = getDistrictLabelByCity(cityCode)
          if (label) district.value = label
        }
        districtAutoFilled = true
      }
      if (values.district && !districts.value.some(d => d.name === values.district)) {
        districts.value.push({ code: '', name: values.district, city_code: cityCode || '' })
      }
    } else if (values.district && !districts.value.some(d => d.name === values.district)) {
      districts.value.push({ code: '', name: values.district })
    }

    // Persist auto-filled district so params show without needing select/unselect toggle
    if (districtAutoFilled && district.value && !values.district) {
      writeUrl({
        region: selectedRegions.value.includes('all') ? 'all' : selectedRegions.value.join(','),
        province: selectedProvinces.value.includes('all')
          ? 'all'
          : selectedProvinces.value.join(','),
        city: selectedMunicipalities.value.includes('all')
          ? 'all'
          : selectedMunicipalities.value.join(','),
        district: district.value,
      })
    }
  } finally {
    hydrating.value = false
  }
}

async function onRegionChange(value) {
  if (hydrating.value) return

  // Handle multi-select array
  let regions = Array.isArray(value) ? value : value ? [value] : []

  // District-style toggle: clicking All again clears it, selecting other while All active deselects All
  if (regions.includes('all')) {
    const wasAll = selectedRegions.value.includes('all')
    const hasOther = regions.some(v => v !== 'all')
    if (wasAll && hasOther) {
      // Was All, now adding another -> deselect All, keep others
      regions = regions.filter(v => v !== 'all')
    } else if (wasAll && regions.length === 1) {
      // Clicking All again while All active -> toggle off (district behavior)
      selectedRegions.value = []
      region.value = ''
      selectedProvinces.value = []
      province.value = ''
      selectedMunicipalities.value = []
      municipality.value = ''
      district.value = ''
      provinces.value = []
      cities.value = []
      districts.value = []
      writeUrl({ region: '', province: '', city: '', district: '' })
      return
    } else if (!wasAll) {
      // Newly selecting All
      selectedRegions.value = ['all']
      region.value = 'all'
      selectedProvinces.value = []
      province.value = ''
      selectedMunicipalities.value = []
      municipality.value = ''
      district.value = ''
      searchProvince.value = ''
      searchCity.value = ''
      searchDistrict.value = ''

      provinces.value = allProvinces.value
      districts.value = []
      // Immediate URL sync — single-click contract (was after heavy fetch → required double-click)
      writeUrl({ region: 'all', province: province.value, city: '', district: '' })
      // Fetch cities in background, never blocks URL (scalable for 80+ provinces)
      if (cities.value.length < 500) {
        const provinceCodes = allProvinces.value.map(p => p.code).filter(Boolean)
        if (provinceCodes.length > 0) {
          const batchSize = 10
          for (let i = 0; i < provinceCodes.length; i += batchSize) {
            const batch = provinceCodes.slice(i, i + batchSize)
            Promise.all(batch.map(code => fetchCities({ provinceCode: code }))).catch(() => {})
            await new Promise(r => setTimeout(r, 0))
          }
        }
      }
      return
    }
    // wasAll + hasOther fell through -> treat as normal selection below
  }

  selectedRegions.value = regions
  region.value = regions.length === 1 ? regions[0] : regions.join(', ')
  selectedProvinces.value = []
  province.value = ''
  selectedMunicipalities.value = []
  municipality.value = ''
  district.value = ''

  // Load provinces for all selected regions
  if (regions.length > 0) {
    const regionCodes = regions.map(r => regionCode(r)).filter(Boolean)

    if (regionCodes.length > 0) {
      // Manually filter provinces for all selected regions
      provinces.value = []
      cities.value = []

      // Collect provinces from all selected regions
      const allProvincesList = []
      regionCodes.forEach(code => {
        const regionProvinces = allProvinces.value.filter(p => p.region_code === code)
        allProvincesList.push(...regionProvinces)
      })

      // Remove duplicates based on province code
      const uniqueProvinces = []
      const seenCodes = new Set()
      allProvincesList.forEach(p => {
        if (!seenCodes.has(p.code)) {
          seenCodes.add(p.code)
          uniqueProvinces.push(p)
        }
      })

      provinces.value = uniqueProvinces

      // If any region doesn't have provinces, fetch cities directly
      const regionsWithoutProvinces = regionCodes.filter(code => !hasProvincesInRegion(code))
      if (regionsWithoutProvinces.length > 0) {
        await Promise.all(regionsWithoutProvinces.map(code => fetchCities({ regionCode: code })))
      }
    } else {
      provinces.value = []
      cities.value = []
      districts.value = []
    }
  } else {
    provinces.value = []
    cities.value = []
    districts.value = []
  }

  writeUrl({ region: regions.join(','), province: '', city: '', district: '' })
}

async function onProvinceChange(value) {
  if (hydrating.value) return

  // Handle multi-select array
  let provinces = Array.isArray(value) ? value : value ? [value] : []

  // District-style toggle for All
  if (provinces.includes('all')) {
    const wasAll = selectedProvinces.value.includes('all')
    const hasOther = provinces.some(v => v !== 'all')
    if (wasAll && hasOther) {
      provinces = provinces.filter(v => v !== 'all')
    } else if (wasAll && provinces.length === 1) {
      // Toggle All off
      selectedProvinces.value = []
      province.value = ''
      selectedMunicipalities.value = []
      municipality.value = ''
      district.value = ''
      cities.value = []
      districts.value = []
      searchCity.value = ''
      searchDistrict.value = ''
      writeUrl({
        region: region.value === 'all' ? 'all' : selectedRegions.value.join(','),
        province: '',
        city: '',
        district: '',
      })
      return
    } else if (!wasAll) {
      selectedProvinces.value = ['all']
      province.value = 'all'
      searchCity.value = ''
      searchDistrict.value = ''
      district.value = ''
      // Immediate URL sync before heavy city fetch
      writeUrl({
        region: region.value === 'all' ? 'all' : selectedRegions.value.join(','),
        province: 'all',
        city: municipality.value === 'all' ? 'all' : selectedMunicipalities.value.join(','),
        district: district.value,
      })
      if (cities.value.length >= 500) {
        return
      }
      // Load cities for newly selected All (sequential accumulate to avoid replacement race)
      if (selectedRegions.value.length > 0) {
        const accumulated = []
        const seen = new Set()
        const accumulateFetch = async code => {
          await fetchCities({ provinceCode: code })
          for (const c of [...cities.value]) {
            if (!seen.has(c.code)) {
              seen.add(c.code)
              accumulated.push(c)
            }
          }
        }
        if (selectedRegions.value.includes('all')) {
          const provinceCodes = allProvinces.value.map(p => p.code).filter(Boolean)
          for (const code of provinceCodes) {
            // batch still sequential to preserve merge; throttled
            await accumulateFetch(code)
            if (accumulated.length > 800) break // cap to avoid huge payload
          }
          cities.value = accumulated
        } else {
          const regionCodes = selectedRegions.value.map(r => regionCode(r)).filter(Boolean)
          if (regionCodes.length > 0) {
            const allProvincesList = []
            regionCodes.forEach(code => {
              const regionProvinces = allProvinces.value.filter(p => p.region_code === code)
              allProvincesList.push(...regionProvinces)
            })
            const provinceCodes = allProvincesList.map(p => p.code).filter(Boolean)
            for (const code of provinceCodes) {
              await accumulateFetch(code)
            }
            cities.value = accumulated
          }
        }
        if (accumulated.length === 0) {
          cities.value = []
          districts.value = []
        }
      } else {
        cities.value = []
        districts.value = []
      }
      // URL already synced above — no duplicate pushState
      return
    }
  }

  selectedProvinces.value = provinces
  province.value = provinces.length === 1 ? provinces[0] : provinces.join(', ')
  // BUG FIX: Reset municipality when province changes
  selectedMunicipalities.value = []
  municipality.value = ''
  district.value = ''

  // Load cities for all selected provinces (merge, not replace)
  if (provinces.length > 0) {
    const currentRegionCode = regionCode(region.value)
    const provinceCodes = provinces
      .map(p => getProvinceCodeByName(p, currentRegionCode))
      .filter(Boolean)

    if (provinceCodes.length > 0) {
      const accumulated = []
      const seen = new Set()
      for (const code of provinceCodes) {
        await fetchCities({ provinceCode: code })
        for (const c of cities.value) {
          if (!seen.has(c.code)) {
            seen.add(c.code)
            accumulated.push(c)
          }
        }
      }
      cities.value = accumulated
    } else {
      cities.value = []
      districts.value = []
    }
  } else {
    cities.value = []
    districts.value = []
  }

  writeUrl({
    region: region.value === 'all' ? 'all' : selectedRegions.value.join(','),
    province: provinces.join(','),
    city: '',
    district: '',
  })
}

async function onMunicipalityChange(value) {
  if (hydrating.value) return

  // Handle multi-select array
  let municipalities = Array.isArray(value) ? value : value ? [value] : []

  // District-style toggle for All
  if (municipalities.includes('all')) {
    const wasAll = selectedMunicipalities.value.includes('all')
    const hasOther = municipalities.some(v => v !== 'all')
    if (wasAll && hasOther) {
      municipalities = municipalities.filter(v => v !== 'all')
    } else if (wasAll && municipalities.length === 1) {
      // Toggle All off
      selectedMunicipalities.value = []
      municipality.value = ''
      district.value = ''
      districts.value = []
      searchDistrict.value = ''
      writeUrl({
        region: region.value === 'all' ? 'all' : selectedRegions.value.join(','),
        province: province.value === 'all' ? 'all' : selectedProvinces.value.join(','),
        city: '',
        district: '',
      })
      return
    } else if (!wasAll) {
      selectedMunicipalities.value = ['all']
      municipality.value = 'all'
      district.value = ''
      searchDistrict.value = ''
      // Immediate URL sync
      writeUrl({
        region: region.value === 'all' ? 'all' : selectedRegions.value.join(','),
        province: province.value === 'all' ? 'all' : selectedProvinces.value.join(','),
        city: 'all',
        district: '',
      })
      fetchAllDistricts().catch(() => {})
      return
    }
  }

  selectedMunicipalities.value = municipalities
  municipality.value = municipalities.length === 1 ? municipalities[0] : ''
  district.value = ''

  // Load districts for all selected municipalities
  if (municipalities.length > 0) {
    const cityCodes = cities.value
      .filter(c => municipalities.includes(c.name))
      .map(c => c.code)
      .filter(Boolean)

    if (cityCodes.length > 0) {
      // Fetch districts for all selected cities
      await Promise.all(cityCodes.map(code => fetchDistricts(code)))
      // Set district to the first one for display
      district.value = getDistrictLabelByCity(cityCodes[0])
    } else {
      districts.value = []
    }
  } else {
    districts.value = []
  }

  writeUrl({
    region: region.value === 'all' ? 'all' : selectedRegions.value.join(','),
    province: province.value === 'all' ? 'all' : selectedProvinces.value.join(','),
    city: municipalities.join(','),
    district: district.value === 'all' ? 'all' : district.value,
  })
}

function onDistrictChange(value) {
  if (hydrating.value) return

  // Clicking the currently-selected value (including "all") toggles it off.
  const resolved = value === district.value ? '' : value
  district.value = resolved
  writeUrl({
    region: region.value === 'all' ? 'all' : selectedRegions.value.join(','),
    province: province.value === 'all' ? 'all' : selectedProvinces.value.join(','),
    city: municipality.value === 'all' ? 'all' : selectedMunicipalities.value.join(','),
    district: resolved,
  })
}

onMounted(async () => {
  await load()
  await hydrate()
})
</script>

<template>
  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
    <div>
      <label class="mb-1 block font-medium">{{ regionLabel }}</label>
      <Select :model-value="selectedRegions" multiple @update:model-value="onRegionChange">
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select region" />
        </SelectTrigger>
        <SelectContent class="max-h-[220px]">
          <div class="bg-popover sticky top-0 z-10 p-1 pb-2" @mousedown.stop @click.stop>
            <Input
              v-model="searchRegion"
              placeholder="Search region..."
              @keydown="onSearchKeydown"
              @mousedown.stop
            />
          </div>
          <SelectItem value="all">All Regions</SelectItem>
          <SelectItem v-for="r in filteredRegions" :key="r.code" :value="r.name">
            {{ r.name }}
          </SelectItem>
          <div v-if="filteredRegions.length === 0" class="text-muted-foreground px-2 py-2 text-sm">
            No results. Type to filter {{ regions.length }} regions.
          </div>
          <div
            v-else-if="regions.length > DISPLAY_LIMIT && !searchRegion"
            class="text-muted-foreground px-2 py-1 text-xs"
          >
            Showing {{ filteredRegions.length }} of {{ regions.length }} — search to filter
          </div>
        </SelectContent>
      </Select>
    </div>

    <div>
      <label class="mb-1 block font-medium">{{ provinceLabel }}</label>
      <Select
        :model-value="selectedProvinces"
        multiple
        :disabled="provinceDisabled"
        @update:model-value="onProvinceChange"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select province" />
        </SelectTrigger>
        <SelectContent class="max-h-[220px]">
          <div class="bg-popover sticky top-0 z-10 p-1 pb-2" @mousedown.stop @click.stop>
            <Input
              v-model="searchProvince"
              placeholder="Search province..."
              @keydown="onSearchKeydown"
              @mousedown.stop
            />
          </div>
          <SelectItem value="all">All Provinces</SelectItem>
          <SelectItem v-for="p in filteredProvinces" :key="p.code" :value="p.name">
            {{ p.name }}
          </SelectItem>
          <div
            v-if="filteredProvinces.length === 0"
            class="text-muted-foreground px-2 py-2 text-sm"
          >
            No results. Type to filter {{ provinces.length }} provinces.
          </div>
          <div
            v-else-if="provinces.length > DISPLAY_LIMIT && !searchProvince"
            class="text-muted-foreground px-2 py-1 text-xs"
          >
            Showing {{ filteredProvinces.length }} of {{ provinces.length }} — search to filter
          </div>
        </SelectContent>
      </Select>
    </div>

    <div>
      <label class="mb-1 block font-medium">{{ municipalityLabel }}</label>
      <Select
        :model-value="selectedMunicipalities"
        multiple
        :disabled="municipalityDisabled"
        @update:model-value="onMunicipalityChange"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select municipality" />
        </SelectTrigger>
        <SelectContent class="max-h-[220px]">
          <div class="bg-popover sticky top-0 z-10 p-1 pb-2" @mousedown.stop @click.stop>
            <Input
              v-model="searchCity"
              placeholder="Search municipality..."
              @keydown="onSearchKeydown"
              @mousedown.stop
            />
          </div>
          <SelectItem v-if="cities.length > 0" value="all">All Municipalities</SelectItem>
          <SelectItem v-for="c in filteredCities" :key="c.code" :value="c.name">
            {{ c.name }}
          </SelectItem>
          <div
            v-if="filteredCities.length === 0 && cities.length > 0"
            class="text-muted-foreground px-2 py-2 text-sm"
          >
            No results. Type to filter {{ cities.length }} municipalities.
          </div>
          <div
            v-else-if="cities.length > DISPLAY_LIMIT && !searchCity"
            class="text-muted-foreground px-2 py-1 text-xs"
          >
            Showing {{ filteredCities.length }} of {{ cities.length }} — search to filter
          </div>
        </SelectContent>
      </Select>
    </div>

    <div>
      <label class="mb-1 block font-medium">{{ districtLabel }}</label>
      <Select
        :model-value="district"
        :disabled="districtDisabled"
        @update:model-value="onDistrictChange"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select districts" />
        </SelectTrigger>
        <SelectContent class="max-h-[220px]">
          <div class="bg-popover sticky top-0 z-10 p-1 pb-2" @mousedown.stop @click.stop>
            <Input
              v-model="searchDistrict"
              placeholder="Search district..."
              @keydown="onSearchKeydown"
              @mousedown.stop
            />
          </div>
          <SelectItem value="all">All Districts</SelectItem>
          <SelectItem v-for="d in filteredDistricts" :key="d.name" :value="d.name">
            {{ d.name }}
          </SelectItem>
          <div
            v-if="filteredDistricts.length === 0 && districts.length > 0"
            class="text-muted-foreground px-2 py-2 text-sm"
          >
            No results.
          </div>
          <div
            v-else-if="districts.length > DISPLAY_LIMIT && !searchDistrict"
            class="text-muted-foreground px-2 py-1 text-xs"
          >
            Showing {{ filteredDistricts.length }} of {{ districts.length }} — search to filter
          </div>
        </SelectContent>
      </Select>
    </div>
  </div>
</template>
