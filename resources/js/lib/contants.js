export const MODAL_TYPES = {
  personalInfo: 'personal-info',
  farmInformation: 'farm-information',
  livestockInfo: 'livestock-info',
  incomeInfo: 'income-info',
  feedManagement: 'feed-management',
  waterSource: 'water-source',
  assets: 'assets',
  povertyIndex: 'poverty-index',
}

export const EDIT_TYPES = {
  personal: 'personal',
  farming: 'farming',
  livestock: 'livestock',
  animalInventory: 'animal-inventory',
  income: 'income',
  feed: 'feed',
  water: 'water',
  assets: 'assets',
  poverty: 'poverty',
  socio: 'socio',
  rsbsa: 'rsbsa',
  manure: 'manure',
}

export const EDIT_TYPE_BY_MODAL_TYPE = {
  [MODAL_TYPES.personalInfo]: EDIT_TYPES.personal,
  [MODAL_TYPES.farmInformation]: EDIT_TYPES.farming,
  [MODAL_TYPES.livestockInfo]: EDIT_TYPES.livestock,
  [MODAL_TYPES.animalInventory]: EDIT_TYPES.animalInventory,
  [MODAL_TYPES.incomeInfo]: EDIT_TYPES.income,
  [MODAL_TYPES.feedManagement]: EDIT_TYPES.feed,
  [MODAL_TYPES.waterSource]: EDIT_TYPES.water,
  [MODAL_TYPES.assets]: EDIT_TYPES.assets,
  [MODAL_TYPES.povertyIndex]: EDIT_TYPES.poverty,
  // Backward compatibility for older button payloads.
  'feed-management': EDIT_TYPES.feed,
  'water-source': EDIT_TYPES.water,
  'farming-info': EDIT_TYPES.farming,
  'livestock-info': EDIT_TYPES.livestock,
  'animal-inventory': EDIT_TYPES.animalInventory,
  'income-info': EDIT_TYPES.income,
  geotag: EDIT_TYPES.assets,
  'socio-economic': EDIT_TYPES.socio,
  rsbsa: EDIT_TYPES.rsbsa,
  'waste-management': EDIT_TYPES.manure,
}

export const DOCUMENT_STATUS = {
  DRAFT: 'draft',
  PENDING_REVIEW: 'pending_review',
  PENDING_CENTER_REVIEW: 'pending_center_review',
  PENDING_AGENCY_REVIEW: 'pending_agency_review',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  REJECTED_BY_CENTER: 'rejected_by_center',
  REJECTED_BY_AGENCY: 'rejected_by_agency',
  PROCESSING: 'processing',
  PROCESSED: 'processed',
  FAILED: 'failed',
}
