import { ref, watch } from 'vue'

/**
 * Manages address hydration for marketing-infrastructure rows.
 *
 * Responsibilities:
 *  1. Resolve any address (name strings OR PSGC codes) into PSGC codes and
 *     stamp the result onto a marketing-infrastructure row — used both when a
 *     DairyBox is selected and when the "copy from processing infrastructure"
 *     checkbox is toggled.
 *  2. Maintain the per-row "copy processing address" checkbox flags and keep
 *     checked rows live-synced whenever processing_infrastructure[0] changes.
 *
 * @param {object} options
 * @param {import('@inertiajs/vue3').InertiaForm} options.form           – the Inertia form object
 * @param {ReturnType<import('./usePsgcAddress').usePsgcAddress>} options.psgc – shared PSGC instance (must already be load()-ed)
 */
export function useMarketingInfrastructureAddress({ form, psgc }) {
  // ── Internal address resolver ──────────────────────────────────────────────
  /**
   * Resolve a flat address object (whose fields may be plain name strings or
   * PSGC codes) into PSGC codes and write them back onto the mk row at `index`.
   *
   * `source` shape: { region, province, city|municipality, barangay, district }
   * The `city` key is preferred over `municipality` so this works for both
   * DairyBox addresses (which use `city`) and processing-infrastructure rows
   * (which use `municipality`).
   */
  const resolveAddressToRow = async (index, source) => {
    const mk = form.marketing_infrastructure[index]
    if (!mk) return

    const addressRegion = source.region || ''
    const addressProvince = source.province || ''
    const addressCity = source.city || source.municipality || ''
    const addressBarangay = source.barangay || ''
    const addressDistrict = source.district || ''

    // Region — getRegionCodeByName handles both plain names AND codes gracefully
    const regionCode = psgc.getRegionCodeByName(addressRegion) || addressRegion
    mk.region = regionCode

    psgc.fetchProvinces(regionCode)
    const regionHasProvinces = psgc.hasProvincesInRegion(regionCode)

    const provinceCode = psgc.getProvinceCodeByName(addressProvince, regionCode) || addressProvince
    mk.province = provinceCode

    await psgc.fetchCities({
      provinceCode,
      regionCode: !regionHasProvinces ? regionCode : '',
    })

    const cityCode =
      psgc.getCityCodeByName(addressCity, provinceCode, !regionHasProvinces ? regionCode : '') ||
      addressCity
    mk.municipality = cityCode

    await psgc.fetchBarangays(cityCode)
    mk.barangay = psgc.getBarangayCodeByName(addressBarangay, cityCode) || addressBarangay

    await psgc.fetchDistricts(cityCode)
    mk.district = psgc.getDistrictLabelByCity(cityCode) || addressDistrict

    // Trigger Vue reactivity with a full object replacement
    form.marketing_infrastructure[index] = { ...mk }
  }

  // ── Public: populate a row from a selected DairyBox ───────────────────────
  /**
   * Fill non-address fields (partner, business name, etc.) and then hydrate
   * the address from the DairyBox's own address record.
   * Call this whenever mk.dairy_box_id changes.
   */
  const populateFromDairyBox = async (index, dairyBox) => {
    if (!dairyBox) return

    const mk = form.marketing_infrastructure[index]
    if (!mk) return

    mk.partner = dairyBox.partner?.name || ''
    mk.business_name = dairyBox.business_name || ''
    mk.manager = dairyBox.manager || ''
    mk.operational_status = String(dairyBox.status || '')
    mk.launched_date = dairyBox.launched_date
      ? new Date(dairyBox.launched_date).toISOString().split('T')[0]
      : ''

    await resolveAddressToRow(index, dairyBox.address || {})
  }

  // ── Public: "copy processing address" checkbox logic ──────────────────────
  /**
   * One boolean per marketing-infrastructure row.
   * UI-only state — not stored in `form`.
   */
  const copyFlags = ref((form.marketing_infrastructure || [{}]).map(() => false))

  const _applyProcessingAddress = async index => {
    const infra = form.processing_infrastructure?.[0] || {}
    await resolveAddressToRow(index, infra)
  }

  const _clearRowAddress = index => {
    form.marketing_infrastructure[index] = {
      ...form.marketing_infrastructure[index],
      region: '',
      province: '',
      municipality: '',
      barangay: '',
      district: '',
    }
  }

  /**
   * Call this from the checkbox @change handler.
   * Also stamps `update_dairy_box_address` onto the row for the backend.
   */
  const onCopyToggle = async (index, checked) => {
    copyFlags.value[index] = checked
    form.marketing_infrastructure[index] = {
      ...form.marketing_infrastructure[index],
      update_dairy_box_address: checked,
    }
    if (checked) {
      await _applyProcessingAddress(index)
    } else {
      _clearRowAddress(index)
    }
  }

  // Live-sync: re-apply whenever processing_infrastructure[0] address changes
  watch(
    () => {
      const pi = form.processing_infrastructure?.[0] || {}
      return [pi.region, pi.province, pi.municipality, pi.barangay, pi.district].join('|')
    },
    async () => {
      for (let i = 0; i < form.marketing_infrastructure.length; i++) {
        if (copyFlags.value[i]) {
          await _applyProcessingAddress(i)
        }
      }
    }
  )

  // Keep copyFlags length in sync as rows are added / removed
  watch(
    () => form.marketing_infrastructure.length,
    newLen => {
      while (copyFlags.value.length < newLen) copyFlags.value.push(false)
      copyFlags.value.length = newLen
    }
  )

  return {
    /** Per-row "copy processing address" boolean flags */
    copyFlags,
    /** Toggle handler — wire to checkbox @change */
    onCopyToggle,
    /** Hydrate a marketing row from a selected DairyBox object */
    populateFromDairyBox,
  }
}
