/**
 * useFilterSync — single source of truth for report filter URL sync.
 *
 * Problem it solves:
 * - PsgcAddressFilter wrote URL *after* heavy fetches → user had to click twice to see ?filter[region]=all
 * - FiltersPanel duplicated 12 watchers with `isInitialLoad` gate and copy-pasted All logic → double-click, drift
 *
 * Contract (mirrors PsgcAddressFilter.onRegionChange):
 * - Single click updates URL immediately via history.pushState (no Inertia visit, no debounce)
 * - `all` is a sentinel: selecting it collapses to ['all']; toggling it again clears; picking specific while `all` is active drops `all`
 * - `0` is treated as alias for `all` (legacy)
 * - Heavy data fetching happens *after* URL sync, fire-and-forget, never blocks URL
 */
const ALL_SENTINELS = new Set(['all', '0'])

function includesAll(arr) {
  return arr.some(v => ALL_SENTINELS.has(String(v)))
}
function hasAll(arr) {
  return includesAll(arr)
}
function stripAll(arr) {
  return arr.filter(v => !ALL_SENTINELS.has(String(v)))
}

export function useFilterSync() {
  let isUpdatingUrl = false

  function pushUrl(key, rawValue) {
    // Prevent re-entrant hydration loops (FiltersPanel watches page.url)
    isUpdatingUrl = true

    // Map legacy keys
    let filterKey = key
    if (filterKey === 'business_type') filterKey = 'partner_category'

    const url = new URL(window.location.href)
    const params = new URLSearchParams(url.search)

    if (Array.isArray(rawValue)) {
      if (rawValue.length === 0) {
        params.delete(`filter[${filterKey}]`)
      } else if (hasAll(rawValue)) {
        // Normalized sentinel — backend treats `all` as no filter, but URL must record it
        params.set(`filter[${filterKey}]`, 'all')
      } else {
        params.set(`filter[${filterKey}]`, rawValue.join(','))
      }
    } else {
      if (rawValue === '' || rawValue == null) {
        params.delete(`filter[${filterKey}]`)
      } else {
        params.set(`filter[${filterKey}]`, String(rawValue))
      }
    }

    if (filterKey === 'partner_category') {
      params.delete('filter[business_type]')
    }

    const qs = params.toString()
    const next = qs ? `${url.pathname}?${qs}` : url.pathname
    window.history.pushState(null, '', next)

    // Release guard after next tick — mirrors FiltersPanel's 100ms but without blocking first interaction
    setTimeout(() => {
      isUpdatingUrl = false
    }, 0)
  }

  function isUpdating() {
    return isUpdatingUrl
  }

  /**
   * Multi-select handler — PSGC-style All toggle.
   * @param {import('vue').Ref|Object} filter — reactive filter object (or ref holding array)
   * @param {string} key — filter key (e.g. 'year', 'region')
   * @param {string[]|string} rawValue — new value from Select @update:model-value
   */
  function handleMulti(filter, key, rawValue) {
    const arr = Array.isArray(rawValue) ? [...rawValue] : rawValue ? [String(rawValue)] : []
    const current = Array.isArray(filter[key]) ? filter[key] : []
    const wasAll = hasAll(current)
    const incomingHasAll = hasAll(arr)

    if (incomingHasAll) {
      const hasOther = stripAll(arr).length > 0
      if (wasAll && hasOther) {
        // Was All, now picking specific(s) → drop All, keep specifics (PSGC onRegionChange)
        const next = stripAll(arr)
        filter[key] = next
        pushUrl(key, next)
        return next
      }
      if (wasAll && arr.length === 1) {
        // Toggle All off
        filter[key] = []
        pushUrl(key, [])
        return []
      }
      if (!wasAll) {
        // Newly selecting All
        filter[key] = ['all']
        pushUrl(key, ['all'])
        return ['all']
      }
    }

    filter[key] = arr
    pushUrl(key, arr)
    return arr
  }

  function handleSingle(filter, key, value) {
    const newValue = value === filter[key] ? '' : String(value ?? '')
    filter[key] = newValue
    pushUrl(key, newValue)
    return newValue
  }

  /**
   * Batch writer for PSGC cascade (region/province/city/district) — writes all four keys atomically.
   * Immediate, like FiltersPanel::updateFilters but for 4 keys.
   */
  function pushPsgc({ region, province, city, district }) {
    isUpdatingUrl = true
    const url = new URL(window.location.href)
    const params = new URLSearchParams(url.search)
    const setOrDelete = (k, v) => {
      if (v) params.set(`filter[${k}]`, v)
      else params.delete(`filter[${k}]`)
    }
    setOrDelete('region', region)
    setOrDelete('province', province)
    setOrDelete('city', city)
    setOrDelete('district', district)
    const qs = params.toString()
    const next = qs ? `${url.pathname}?${qs}` : url.pathname
    window.history.pushState(null, '', next)
    setTimeout(() => {
      isUpdatingUrl = false
    }, 0)
  }

  function parseArrayParam(value) {
    if (!value) return []
    if (value === 'all') return ['all']
    return value
      .split(',')
      .map(v => v.trim())
      .filter(Boolean)
  }

  return {
    isUpdating,
    pushUrl,
    pushPsgc,
    handleMulti,
    handleSingle,
    parseArrayParam,
    hasAll,
    stripAll,
  }
}
