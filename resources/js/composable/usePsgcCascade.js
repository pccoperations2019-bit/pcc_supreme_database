import { ref, computed, watch } from 'vue'

export function usePsgcCascade({
  form,
  psgc,
  cityField = 'city',
  withDistrict = false,
  districtField = 'district',
}) {
  const hydratingAddress = ref(false)

  const addressRegionHasProvinces = computed(() => psgc.hasProvincesInRegion(form.region))
  const districtDisabled = computed(() => !form[cityField])

  const hydrateAddressFields = async source => {
    const addressRegion = source.address?.region || ''
    const addressProvince = source.address?.province || ''
    const addressCity = source.address?.city || source.address?.municipality || ''
    const addressBarangay = source.address?.barangay || ''

    hydratingAddress.value = true
    try {
      form.region = psgc.getRegionCodeByName(addressRegion) || addressRegion

      await psgc.fetchProvinces(form.region)
      const regionHasProvinces = psgc.hasProvincesInRegion(form.region)
      form.province = psgc.getProvinceCodeByName(addressProvince, form.region) || addressProvince

      await psgc.fetchCities({
        provinceCode: form.province,
        regionCode: !regionHasProvinces ? form.region : '',
      })
      form[cityField] =
        psgc.getCityCodeByName(
          addressCity,
          form.province,
          !regionHasProvinces ? form.region : ''
        ) || addressCity

      await psgc.fetchBarangays(form[cityField])
      form.barangay =
        psgc.getBarangayCodeByName(addressBarangay, form[cityField]) || addressBarangay

      if (withDistrict) {
        await psgc.fetchDistricts(form[cityField])
        form[districtField] =
          psgc.getDistrictLabelByCity(form[cityField]) || source.address?.district || ''
      }
    } finally {
      hydratingAddress.value = false
    }
  }

  watch(
    () => form.region,
    async () => {
      if (hydratingAddress.value) return
      form.province = ''
      form[cityField] = ''
      form.barangay = ''
      if (withDistrict) form[districtField] = ''
      await psgc.fetchProvinces(form.region)

      if (!psgc.hasProvincesInRegion(form.region)) {
        await psgc.fetchCities({ regionCode: form.region })
      }
    }
  )

  watch(
    () => form.province,
    async () => {
      if (hydratingAddress.value) return
      form[cityField] = ''
      form.barangay = ''
      if (withDistrict) form[districtField] = ''

      if (form.province) {
        await psgc.fetchCities({ provinceCode: form.province })
        return
      }

      if (form.region && !psgc.hasProvincesInRegion(form.region)) {
        await psgc.fetchCities({ regionCode: form.region })
      }
    }
  )

  watch(
    () => form[cityField],
    async () => {
      if (hydratingAddress.value) return
      form.barangay = ''
      await psgc.fetchBarangays(form[cityField])

      if (withDistrict) {
        form[districtField] = ''
        await psgc.fetchDistricts(form[cityField])
        form[districtField] = psgc.getDistrictLabelByCity(form[cityField]) || ''
      }
    }
  )

  return {
    hydratingAddress,
    hydrateAddressFields,
    addressRegionHasProvinces,
    districtDisabled,
    districts: psgc.districts,
  }
}
