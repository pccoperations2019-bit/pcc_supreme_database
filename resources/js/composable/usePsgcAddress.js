import { ref } from 'vue'

/**
 * Known region abbreviation → PSGC code aliases that don't resolve via
 * name/short_name matching (e.g. "NCR", "CAR", pre-2015 region codes).
 */
const REGION_ALIASES = {
  car: '1400000000', // Cordillera Administrative Region
  ncr: '1300000000', // National Capital Region
  barmm: '1900000000', // Bangsamoro
  caraga: '1600000000',
  nir: '1800000000', // Negros Island Region
  mimaropa: '1700000000', // MIMAROPA was formerly Region IV-B
  'mimaropa region': '1700000000',
  'iv-b': '1700000000', // MIMAROPA was formerly Region IV-B
  iv: '400000000', // Bare "IV" -> treat as IV-A (CALABARZON)
}

const PAGE_SIZE = 100
const MAX_PAGES = 500 // hard ceiling so a misbehaving API can't loop forever

class PsgcApiError extends Error {
  constructor(message, { status, url } = {}) {
    super(message)
    this.name = 'PsgcApiError'
    this.status = status
    this.url = url
  }
}

export function usePsgcAddress() {
  const loaded = ref(false)
  const regions = ref([])
  const provinces = ref([])
  const allProvinces = ref([])
  const cities = ref([])
  const barangays = ref([])
  /* District auto-fill + options list, table-backed (app-owned `districts`
     table served by Xhr\DistrictController at /psgc/districts, seeded from
     resources/psgc/district.json). Cascade: region -> province -> city ->
     district -> barangay, mirroring barangays exactly. */
  const districts = ref([])
  const loadingDistricts = ref(false)
  const districtByCity = ref({})

  const loading = ref(false) // true only during the initial load()
  const loadingCities = ref(false)
  const loadingBarangays = ref(false)
  const error = ref(null)

  // Track in-flight requests per resource so a fast cascade of
  // region -> province -> city -> barangay changes can't let an
  // older, slower response clobber a newer one.
  const controllers = {
    cities: null,
    barangays: null,
    districts: null,
  }

  function abortPending(key) {
    controllers[key]?.abort()
    controllers[key] = new AbortController()
    return controllers[key]
  }

  async function fetchAll(endpoint, query = {}, signal) {
    const url = new URL(endpoint, window.location.origin)
    const baseParams = new URLSearchParams(url.search)

    Object.entries(query).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        baseParams.set(key, String(value))
      }
    })

    let offset = 0
    let total = null
    const rows = []

    for (let page = 0; page < MAX_PAGES; page++) {
      const params = new URLSearchParams(baseParams)
      params.set('limit', String(PAGE_SIZE))
      params.set('offset', String(offset))

      const requestUrl = `${url.pathname}?${params.toString()}`
      const res = await fetch(requestUrl, { signal })

      if (!res.ok) {
        throw new PsgcApiError(`PSGC request failed (${res.status})`, {
          status: res.status,
          url: requestUrl,
        })
      }

      const json = await res.json()

      const chunk = Array.isArray(json.data)
        ? json.data
        : Array.isArray(json.results)
          ? json.results
          : Array.isArray(json)
            ? json
            : []

      rows.push(...chunk)

      // Default to chunk length when total is not provided (non-paginated shape).
      const reportedTotal = Number(json.recordsTotal ?? json.recordsFiltered)
      total = Number.isFinite(reportedTotal) && reportedTotal >= 0 ? reportedTotal : chunk.length

      offset += chunk.length

      if (chunk.length < PAGE_SIZE || offset >= total) break
    }

    return rows
  }

  /**
   * Runs an async loader, tracking a loading flag and surfacing errors
   * via `error`. Swallows AbortError silently since that just means a
   * newer request superseded this one.
   */
  async function withState(loadingRef, task) {
    loadingRef.value = true
    error.value = null
    try {
      return await task()
    } catch (err) {
      if (err?.name === 'AbortError') return null
      error.value = err instanceof Error ? err.message : String(err)
      throw err
    } finally {
      loadingRef.value = false
    }
  }

  async function load() {
    if (loaded.value) return
    await withState(loading, async () => {
      const [regionsData, provincesData] = await Promise.all([
        fetchAll('/psgc/regions'),
        fetchAll('/psgc/provinces'),
      ])
      regions.value = regionsData
      allProvinces.value = provincesData
      loaded.value = true
    })
  }

  // Synchronous - filters the already-loaded province cache.
  function fetchProvinces(regionCode) {
    provinces.value = []
    cities.value = []
    barangays.value = []
    if (!regionCode) return
    provinces.value = allProvinces.value.filter(p => p.region_code === regionCode)
  }

  async function fetchCities(filters = {}) {
    cities.value = []
    barangays.value = []

    const provinceCode =
      typeof filters === 'string'
        ? filters
        : (filters?.provinceCode ?? filters?.province_code ?? '')
    const regionCode =
      typeof filters === 'object' ? (filters?.regionCode ?? filters?.region_code ?? '') : ''

    if (!provinceCode && !regionCode) return

    const { signal } = abortPending('cities')

    await withState(loadingCities, async () => {
      const query = {
        ...(provinceCode ? { province_code: provinceCode } : {}),
        ...(regionCode ? { region_code: regionCode } : {}),
      }
      const result = await fetchAll('/psgc/cities', query, signal)
      cities.value = result
    })
  }

  async function fetchBarangays(cityCode) {
    barangays.value = []
    if (!cityCode) return

    const { signal } = abortPending('barangays')

    await withState(loadingBarangays, async () => {
      const result = await fetchAll('/psgc/barangays', { city_code: cityCode }, signal)
      barangays.value = result
    })
  }

  // Districts are table-backed (config `psgc.tables.districts`: `districts`),
  // served by the app's own Xhr\DistrictController at /psgc/districts, and
  // mirror the barangay cascade: one row per city_code, auto-filled into
  // the address cascade when a city resolves. districtByCity keys the map
  // by city_code so the city watch can splice the label synchronously.
  async function fetchDistricts(cityCode) {
    districts.value = []
    districtByCity.value = {}
    if (!cityCode) return

    const { signal } = abortPending('districts')

    await withState(loadingDistricts, async () => {
      const result = await fetchAll('/psgc/districts', { city_code: cityCode }, signal)

      // Deduplicate districts by name to show unique values like regions/provinces/cities
      const uniqueDistricts = []
      const seenNames = new Set()
      result.forEach(d => {
        districtByCity.value[d.city_code] = d
        if (!seenNames.has(d.name)) {
          seenNames.add(d.name)
          uniqueDistricts.push(d)
        }
      })

      districts.value = uniqueDistricts
    })
  }

  // New function to fetch all districts when no city_code is provided
  // Optimized: single request with limit=5000 (DistrictController allows 10000)
  // vs previous 17 sequential limit=100 pages via fetchAll (~5s).
  async function fetchAllDistricts() {
    districts.value = []
    districtByCity.value = {}

    const { signal } = abortPending('districts')

    await withState(loadingDistricts, async () => {
      const url = new URL('/psgc/districts', window.location.origin)
      url.searchParams.set('limit', '5000')
      const requestUrl = `${url.pathname}?${url.searchParams.toString()}`
      const res = await fetch(requestUrl, { signal })

      if (!res.ok) {
        throw new PsgcApiError(`PSGC request failed (${res.status})`, {
          status: res.status,
          url: requestUrl,
        })
      }

      const json = await res.json()
      const result = Array.isArray(json.data)
        ? json.data
        : Array.isArray(json.results)
          ? json.results
          : Array.isArray(json)
            ? json
            : []

      // Deduplicate districts by name to show unique values like regions/provinces/cities
      const uniqueDistricts = []
      const seenNames = new Set()
      result.forEach(d => {
        districtByCity.value[d.city_code] = d
        if (!seenNames.has(d.name)) {
          seenNames.add(d.name)
          uniqueDistricts.push(d)
        }
      })

      districts.value = uniqueDistricts
    })
  }

  function getDistrictByCityCode(cityCode) {
    if (!cityCode) return ''
    return districtByCity.value[cityCode]?.name ?? ''
  }

  // Label variant used by the address cascade's district auto-fill (the
  // stored value is the district's `name` label, e.g. "3rd" / "Lone" —
  // NOT an enum key, "don't use the enum now").
  function getDistrictLabelByCity(cityCode) {
    if (!cityCode) return ''
    return districtByCity.value[cityCode]?.name ?? ''
  }

  // ---- Name/code lookup helpers -------------------------------------
  // All the get*CodeByName functions share the same shape: try an exact
  // match with scoping, then case-insensitive with scoping, then fall
  // back to unscoped matches. Centralize that here so a fix only has to
  // happen once.

  function findCode(list, name, { scope, needScope = false } = {}) {
    if (!name) return ''
    const needle = name.trim()
    if (!needle) return ''
    const needleLower = needle.toLowerCase()

    // Normalize a PSGC name for fuzzy matching: strip "City of " prefix,
    // " City"/" Town"/" Village" suffix, and "Barangay "/"Brgy. " prefix.
    const normalizeName = raw =>
      raw
        .toLowerCase()
        .replace(/^city\s+of\s+/, '')
        .replace(/ (city|town|village)$/, '')
        .replace(/^(barangay|brgy\.?)\s+/, '')

    const matches = (item, exact, normalize = false) => {
      const nameMatches = normalize
        ? normalizeName(item.name) === normalizeName(needle)
        : exact
          ? item.name === needle
          : item.name.toLowerCase() === needleLower
      if (!nameMatches) return false
      return scope ? scope(item) : true
    }

    let found = list.find(item => matches(item, true))
    if (found) return found.code

    found = list.find(item => matches(item, false))
    if (found) return found.code

    // Scoped fallback with suffix/prefix normalization
    found = list.find(item => matches(item, false, true))
    if (found) return found.code

    if (needScope) return '' // caller requires scoping; don't fall back unscoped

    found = list.find(item => item.name === needle)
    if (found) return found.code

    found = list.find(item => item.name.toLowerCase() === needleLower)
    if (found) return found.code

    // Unscoped fallback with full normalization (both sides)
    found = list.find(item => normalizeName(item.name) === normalizeName(needle))
    if (found) return found.code

    // Unscoped fallback: try prepending "City of " and "Barangay "
    found = list.find(item => item.name.toLowerCase() === `city of ${needleLower}`)
    if (found) return found.code

    found = list.find(item => item.name.toLowerCase() === `barangay ${needleLower}`)
    if (found) return found.code

    return ''
  }

  function getRegionCodeByName(name) {
    if (!name) return ''
    const needle = name.trim()
    const needleLower = needle.toLowerCase()

    const direct = findCode(regions.value, needle, {
      scope: r =>
        r.name === needle || r.short_name === needle || r.short_name?.toLowerCase() === needleLower,
    })
    if (direct) return direct

    // Roman-numeral shorthand stored in DB (e.g. "I", "II", "IV-A", "IV-B")
    // "Region IV-A (CALABARZON)" -> "IV-A"
    const romanMatch = regions.value.find(r => {
      const m = r.name.match(/^Region\s+([\w-]+)\s*\(/i)
      return m ? m[1].trim().toLowerCase() === needleLower : false
    })
    if (romanMatch) return romanMatch.code

    const aliasCode = REGION_ALIASES[needleLower.replace(/\s+/g, '')]
    if (aliasCode) return aliasCode

    const partial = regions.value.find(r => r.name.toLowerCase().includes(needleLower))
    return partial ? partial.code : ''
  }

  function getProvinceCodeByName(name, regionCode) {
    if (regionCode) {
      const scoped = findCode(allProvinces.value, name, {
        scope: p => p.region_code === regionCode,
        needScope: true,
      })
      if (scoped) return scoped
    }
    return findCode(allProvinces.value, name)
  }

  function getCityCodeByName(name, provinceCode = '', regionCode = '') {
    if (provinceCode || regionCode) {
      const scoped = findCode(cities.value, name, {
        scope: c =>
          provinceCode ? c.province_code === provinceCode : c.region_code === regionCode,
        needScope: true,
      })
      if (scoped) return scoped
    }
    return findCode(cities.value, name)
  }

  function getBarangayCodeByName(name, cityCode) {
    if (!cityCode) return ''
    return findCode(barangays.value, name, {
      scope: b => b.city_code === cityCode,
      needScope: true,
    })
  }

  function getRegionNameByCode(code) {
    if (!code) return ''
    return regions.value.find(r => r.code === code)?.name || ''
  }

  function getProvinceNameByCode(code) {
    if (!code) return ''
    return allProvinces.value.find(p => p.code === code)?.name || ''
  }

  function getCityNameByCode(code) {
    if (!code) return ''
    return cities.value.find(c => c.code === code)?.name || ''
  }

  function getBarangayNameByCode(code) {
    if (!code) return ''
    return barangays.value.find(b => b.code === code)?.name || ''
  }

  function hasProvincesInRegion(regionCode) {
    if (!regionCode) return false
    return allProvinces.value.some(province => province.region_code === regionCode)
  }

  return {
    loaded,
    regions,
    provinces,
    allProvinces,
    cities,
    barangays,
    districts,
    districtByCity,
    loading,
    loadingCities,
    loadingBarangays,
    loadingDistricts,
    error,
    load,
    fetchProvinces,
    fetchCities,
    fetchBarangays,
    fetchDistricts,
    fetchAllDistricts,
    getDistrictByCityCode,
    getDistrictLabelByCity,
    getRegionCodeByName,
    getProvinceCodeByName,
    getCityCodeByName,
    getBarangayCodeByName,
    getRegionNameByCode,
    getProvinceNameByCode,
    getCityNameByCode,
    getBarangayNameByCode,
    hasProvincesInRegion,
  }
}
