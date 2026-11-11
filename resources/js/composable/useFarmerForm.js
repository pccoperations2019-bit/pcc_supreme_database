/* global FormData */
export const TEXT_FIELDS = [
  'code',
  'first_name',
  'middle_name',
  'last_name',
  'extension_name',
  'nickname',
  'status',
  'gender',
  'civil_status',
  'spouse_name',
  'contact_number',
  'operation_in_charge',
  'purok_zone',
  'zip_code',
  'district',
  'position',
  'indigenous_group',
  'membership_in_organization',
  'name_of_organization',
  'educational_attainment',
  'ndalto_certification_status',
  'ndalto_certification',
  'type_of_entrepreneur',
  'rsbsa_cert_status',
  'rsbsa_certificate_of_registration',
  'if_producer_please_specify',
  'if_no_highest_educational_attainment_of_head_of_family',
  'challenges_or_problems_encountered_in_dairy_farming',
]

export const DATE_FIELDS = ['date_of_interview', 'birthday', 'date_of_enrollment', 'date_assisted']

export const NUMBER_FIELDS = [
  'household_size',
  'year_involvement_in_business',
  'family_member_attending_school_count',
  'female_count',
  'male_count',
  'how_many_in_family_are_involved_in_buffalo_farming',
]

export const BOOL_FIELDS = [
  'is_assisted',
  'has_personal_cellphone',
  'is_pwd',
  'is_4ps',
  'is_ips',
  'involved_in_buffalo_farming',
  'are_you_the_head_of_the_family',
]

export const ALL_SIMPLE_FIELDS = [...TEXT_FIELDS, ...DATE_FIELDS, ...NUMBER_FIELDS, ...BOOL_FIELDS]

export const ANIMAL_INVENTORY_CLASSIFICATIONS = [
  'Cow Lactating',
  'Cow Lactating Pregnant',
  'Cow Dry Pregnant',
  'Cow Dry',
  'Heifer Pregnant',
  'Heifer',
  'Sr. Bull (more than 3 yrs)',
  'Jr. Bull (2-3 yrs.)',
  'Yearling Female (1 yr - 2 yrs)',
  'Yearling Male (1 yr - 2 yrs)',
  'Female Calf (less than 1 year)',
  'Male Calf (less than 1 year)',
]

export const ANIMAL_INVENTORY_BREEDS = ['Native', 'CB', 'Purebred']

export const ANIMAL_OWNERSHIP_TYPES = [
  { key: 'pcc_owned', label: 'PCC Owned' },
  { key: 'lgu_owned', label: 'LGU Owned' },
  { key: 'coop_owned', label: 'Coop Owned' },
  { key: 'palwi', label: 'Palwi from other farmer' },
  { key: 'farmer_owned', label: 'Farmer Owned' },
  { key: 'share_50_50', label: '50/50 Share' },
  { key: 'others', label: 'Others', hasSpecify: true },
]

export function defaultAnimalOwnership() {
  return ANIMAL_OWNERSHIP_TYPES.reduce((acc, type) => {
    acc[type.key] = { checked: false, count: '', ...(type.hasSpecify ? { specify: '' } : {}) }
    return acc
  }, {})
}

export function normalizeAnimalOwnership(data) {
  const defaults = defaultAnimalOwnership()
  const stored = data && typeof data === 'object' ? data : {}
  return Object.keys(defaults).reduce((acc, key) => {
    const entry = stored[key] && typeof stored[key] === 'object' ? stored[key] : {}
    acc[key] = {
      checked: !!entry.checked,
      count: entry.count ?? '',
      ...(defaults[key].specify !== undefined ? { specify: entry.specify ?? '' } : {}),
    }
    return acc
  }, {})
}

export function animalOwnershipPayload(data) {
  const normalized = normalizeAnimalOwnership(data)
  return Object.entries(normalized).reduce((acc, [key, entry]) => {
    acc[key] = {
      checked: !!entry.checked,
      count:
        entry.count === '' || entry.count === null || entry.count === undefined
          ? null
          : Number(entry.count),
      ...(entry.specify !== undefined ? { specify: entry.specify || null } : {}),
    }
    return acc
  }, {})
}

export function normalizeAnimalInventory(data) {
  const rows = Array.isArray(data) ? data : []
  return ANIMAL_INVENTORY_CLASSIFICATIONS.map(classification => {
    const match = rows.find(
      row =>
        row?.classification === classification ||
        (row?.classification?.value && row.classification.value === classification)
    )
    return {
      classification,
      count: match?.count ?? '',
      breed: match?.breed ?? '',
      remarks: match?.remarks ?? '',
    }
  })
}

export function animalInventoryPayload(rows) {
  return (rows || [])
    .map(row => ({
      classification: row.classification,
      count:
        row.count === '' || row.count === null || row.count === undefined
          ? null
          : Number(row.count),
      breed: row.breed || null,
      remarks: row.remarks || null,
    }))
    .filter(row => row.count !== null || row.breed !== null || row.remarks !== null)
}

export function boolField(val) {
  return val === null || val === undefined ? '' : val ? '1' : '0'
}

export function buildInitialFormState(farmer, defaultCenterId) {
  const centerId = farmer.center_id || defaultCenterId || farmer.center?.id || null
  const state = {
    center_id: centerId ? Number(centerId) : null,
    partner_id: farmer.partner_id || null,
    project_ids: (farmer.projects || []).map(p => p.id),
    street: farmer.address?.street || '',
    barangay: '',
    municipality: '',
    province: '',
    region: '',
    photo: null,
    additional_details: farmer.metadata || {},
    animal_inventory: normalizeAnimalInventory(farmer.animal_inventory),
  }
  TEXT_FIELDS.forEach(f => (state[f] = farmer[f] || ''))
  state.district = farmer.address?.district || ''
  DATE_FIELDS.forEach(f => (state[f] = farmer[f] ? farmer[f].split('T')[0] : ''))
  NUMBER_FIELDS.forEach(f => (state[f] = farmer[f] ?? ''))
  BOOL_FIELDS.forEach(f => (state[f] = boolField(farmer[f])))
  return state
}

export function buildFarmerFormData(form, { method, photoFile, addressLookups } = {}) {
  const fd = new FormData()
  if (method) fd.append('_method', method)

  console.log(
    '[buildFarmerFormData] form.center_id:',
    form.center_id,
    'type:',
    typeof form.center_id
  )

  ALL_SIMPLE_FIELDS.forEach(f => fd.append(f, form[f]))

  fd.append('center_id', form.center_id ? Number(form.center_id) : '')
  fd.append('partner_id', form.partner_id ?? '')
  if (form.project_ids && form.project_ids.length > 0) {
    form.project_ids.forEach((id, i) => fd.append(`project_ids[${i}]`, id))
  }
  fd.append('street', form.street)
  fd.append('barangay', addressLookups?.getBarangayNameByCode?.(form.barangay) || form.barangay)
  fd.append(
    'municipality',
    addressLookups?.getCityNameByCode?.(form.municipality) || form.municipality
  )
  fd.append('province', addressLookups?.getProvinceNameByCode?.(form.province) || form.province)
  fd.append('region', addressLookups?.getRegionNameByCode?.(form.region) || form.region)

  Object.entries(form.additional_details || {}).forEach(([key, rawValue]) => {
    const value = rawValue === true ? '1' : rawValue === false ? '0' : rawValue
    if (value === null || value === undefined || value === '') return
    fd.append(`additional_details[${String(key).toLowerCase()}]`, value)
  })

  animalInventoryPayload(form.animal_inventory).forEach((row, index) => {
    Object.entries(row).forEach(([key, value]) => {
      if (value === null || value === undefined) return
      fd.append(`animal_inventory[${index}][${key}]`, value)
    })
  })

  if (photoFile) fd.append('photo', photoFile)
  return fd
}
