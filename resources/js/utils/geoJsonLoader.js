/**
 * GeoJSON Province Loader Utility
 * Loads Philippine province GeoJSON data dynamically
 */

// Province mapping for GeoJSON file names
const PROVINCE_MAPPING = {
  // Autonomous Region of Muslim Mindanao (ARMM)
  basilan: 'ph.autonomous-region-of-muslim-mindanao-armm.basilan.any.any.geo.json',
  'lanao-del-sur': 'ph.autonomous-region-of-muslim-mindanao-armm.lanao-del-sur.any.any.geo.json',
  maguindanao: 'ph.autonomous-region-of-muslim-mindanao-armm.maguindanao.any.any.geo.json',
  'shariff-kabunsuan':
    'ph.autonomous-region-of-muslim-mindanao-armm.shariff-kabunsuan.any.any.geo.json',
  sulu: 'ph.autonomous-region-of-muslim-mindanao-armm.sulu.any.any.geo.json',
  'tawi-tawi': 'ph.autonomous-region-of-muslim-mindanao-armm.tawi-tawi.any.any.geo.json',

  // Bicol Region (Region V)
  albay: 'ph.bicol-region-region-v.albay.any.any.geo.json',
  'camarines-norte': 'ph.bicol-region-region-v.camarines-norte.any.any.geo.json',
  'camarines-sur': 'ph.bicol-region-region-v.camarines-sur.any.any.geo.json',
  catanduanes: 'ph.bicol-region-region-v.catanduanes.any.any.geo.json',
  masbate: 'ph.bicol-region-region-v.masbate.any.any.geo.json',
  sorsogon: 'ph.bicol-region-region-v.sorsogon.any.any.geo.json',

  // Cagayan Valley (Region II)
  batanes: 'ph.cagayan-valley-region-ii.batanes.any.any.geo.json',
  cagayan: 'ph.cagayan-valley-region-ii.cagayan.any.any.geo.json',
  isabela: 'ph.cagayan-valley-region-ii.isabela.any.any.geo.json',
  'nueva-vizcaya': 'ph.cagayan-valley-region-ii.nueva-vizcaya.any.any.geo.json',
  quirino: 'ph.cagayan-valley-region-ii.quirino.any.any.geo.json',

  // CALABARZON (Region IV-A)
  batangas: 'ph.calabarzon-region-iv-a.batangas.any.any.geo.json',
  cavite: 'ph.calabarzon-region-iv-a.cavite.any.any.geo.json',
  laguna: 'ph.calabarzon-region-iv-a.laguna.any.any.geo.json',
  quezon: 'ph.calabarzon-region-iv-a.quezon.any.any.geo.json',
  rizal: 'ph.calabarzon-region-iv-a.rizal.any.any.geo.json',

  // CARAGA (Region XIII)
  'agusan-del-norte': 'ph.caraga-region-xiii.agusan-del-norte.any.any.geo.json',
  'agusan-del-sur': 'ph.caraga-region-xiii.agusan-del-sur.any.any.geo.json',
  'dinagat-islands': 'ph.caraga-region-xiii.dinagat-islands.any.any.geo.json',
  'surigao-del-norte': 'ph.caraga-region-xiii.surigao-del-norte.any.any.geo.json',
  'surigao-del-sur': 'ph.caraga-region-xiii.surigao-del-sur.any.any.geo.json',

  // Central Luzon (Region III)
  aurora: 'ph.central-luzon-region-iii.aurora.any.any.geo.json',
  bataan: 'ph.central-luzon-region-iii.bataan.any.any.geo.json',
  bulacan: 'ph.central-luzon-region-iii.bulacan.any.any.geo.json',
  'nueva-ecija': 'ph.central-luzon-region-iii.nueva-ecija.any.any.geo.json',
  pampanga: 'ph.central-luzon-region-iii.pampanga.any.any.geo.json',
  tarlac: 'ph.central-luzon-region-iii.tarlac.any.any.geo.json',
  zambales: 'ph.central-luzon-region-iii.zambales.any.any.geo.json',

  // Central Visayas (Region VII)
  bohol: 'ph.central-visayas-region-vii.bohol.any.any.geo.json',
  cebu: 'ph.central-visayas-region-vii.cebu.any.any.geo.json',
  'negros-oriental': 'ph.central-visayas-region-vii.negros-oriental.any.any.geo.json',
  siquijor: 'ph.central-visayas-region-vii.siquijor.any.any.geo.json',

  // Cordillera Administrative Region (CAR)
  abra: 'ph.cordillera-administrative-region-car.abra.any.any.geo.json',
  apayao: 'ph.cordillera-administrative-region-car.apayao.any.any.geo.json',
  benguet: 'ph.cordillera-administrative-region-car.benguet.any.any.geo.json',
  ifugao: 'ph.cordillera-administrative-region-car.ifugao.any.any.geo.json',
  kalinga: 'ph.cordillera-administrative-region-car.kalinga.any.any.geo.json',
  'mountain-province': 'ph.cordillera-administrative-region-car.mountain-province.any.any.geo.json',

  // Davao Region (Region XI)
  'compostela-valley': 'ph.davao-region-region-xi.compostela-valley.any.any.geo.json',
  'davao-del-norte': 'ph.davao-region-region-xi.davao-del-norte.any.any.geo.json',
  'davao-del-sur': 'ph.davao-region-region-xi.davao-del-sur.any.any.geo.json',
  'davao-oriental': 'ph.davao-region-region-xi.davao-oriental.any.any.geo.json',

  // Eastern Visayas (Region VIII)
  biliran: 'ph.eastern-visayas-region-viii.biliran.any.any.geo.json',
  'eastern-samar': 'ph.eastern-visayas-region-viii.eastern-samar.any.any.geo.json',
  leyte: 'ph.eastern-visayas-region-viii.leyte.any.any.geo.json',
  'northern-samar': 'ph.eastern-visayas-region-viii.northern-samar.any.any.geo.json',
  samar: 'ph.eastern-visayas-region-viii.samar.any.any.geo.json',
  'southern-leyte': 'ph.eastern-visayas-region-viii.southern-leyte.any.any.geo.json',

  // Ilocos Region (Region I)
  'ilocos-norte': 'ph.ilocos-region-region-i.ilocos-norte.any.any.geo.json',
  'ilocos-sur': 'ph.ilocos-region-region-i.ilocos-sur.any.any.geo.json',
  'la-union': 'ph.ilocos-region-region-i.la-union.any.any.geo.json',
  pangasinan: 'ph.ilocos-region-region-i.pangasinan.any.any.geo.json',

  // MIMAROPA (Region IV-B)
  marinduque: 'ph.mimaropa-region-iv-b.marinduque.any.any.geo.json',
  'occidental-mindoro': 'ph.mimaropa-region-iv-b.occidental-mindoro.any.any.geo.json',
  'oriental-mindoro': 'ph.mimaropa-region-iv-b.oriental-mindoro.any.any.geo.json',
  palawan: 'ph.mimaropa-region-iv-b.palawan.any.any.geo.json',
  romblon: 'ph.mimaropa-region-iv-b.romblon.any.any.geo.json',

  // National Capital Region
  'metropolitan-manila': 'ph.national-capital-region.metropolitan-manila.any.any.geo.json',

  // Northern Mindanao (Region X)
  bukidnon: 'ph.northern-mindanao-region-x.bukidnon.any.any.geo.json',
  camiguin: 'ph.northern-mindanao-region-x.camiguin.any.any.geo.json',
  'lanao-del-norte': 'ph.northern-mindanao-region-x.lanao-del-norte.any.any.geo.json',
  'misamis-occidental': 'ph.northern-mindanao-region-x.misamis-occidental.any.any.geo.json',
  'misamis-oriental': 'ph.northern-mindanao-region-x.misamis-oriental.any.any.geo.json',

  // SOCCSKSARGEN (Region XII)
  'north-cotabato': 'ph.soccsksargen-region-xii.north-cotabato.any.any.geo.json',
  sarangani: 'ph.soccsksargen-region-xii.sarangani.any.any.geo.json',
  'south-cotabato': 'ph.soccsksargen-region-xii.south-cotabato.any.any.geo.json',
  'sultan-kudarat': 'ph.soccsksargen-region-xii.sultan-kudarat.any.any.geo.json',

  // Western Visayas (Region VI)
  aklan: 'ph.western-visayas-region-vi.aklan.any.any.geo.json',
  antique: 'ph.western-visayas-region-vi.antique.any.any.geo.json',
  capiz: 'ph.western-visayas-region-vi.capiz.any.any.geo.json',
  guimaras: 'ph.western-visayas-region-vi.guimaras.any.any.geo.json',
  iloilo: 'ph.western-visayas-region-vi.iloilo.any.any.geo.json',
  'negros-occidental': 'ph.western-visayas-region-vi.negros-occidental.any.any.geo.json',

  // Zamboanga Peninsula (Region IX)
  'zamboanga-del-norte': 'ph.zamboanga-peninsula-region-ix.zamboanga-del-norte.any.any.geo.json',
  'zamboanga-del-sur': 'ph.zamboanga-peninsula-region-ix.zamboanga-del-sur.any.any.geo.json',
  'zamboanga-sibugay': 'ph.zamboanga-peninsula-region-ix.zamboanga-sibugay.any.any.geo.json',
}

/**
 * Load GeoJSON data for specified provinces
 * @param {string[]} provinces - Array of province names
 * @returns {Promise<Object>} Object with province name as key and GeoJSON data as value
 */
export async function loadProvincesGeoJson(provinces) {
  const geoJsonData = {}
  const promises = []

  for (const province of provinces) {
    if (!PROVINCE_MAPPING[province]) {
      console.warn(`Province "${province}" not found in mapping`)
      continue
    }

    const fileName = PROVINCE_MAPPING[province]
    console.log(`Loading province: ${province} -> file: ${fileName}`)
    const url = `/geojson_province/${fileName}?t=${Date.now()}`
    const promise = fetch(url)
      .then(response => {
        if (!response.ok) {
          throw new Error(`Failed to fetch ${fileName}: ${response.status}`)
        }
        return response.json()
      })
      .then(data => {
        geoJsonData[province] = data
      })
      .catch(error => {
        console.warn(`Error loading GeoJSON for ${province}:`, error)
      })

    promises.push(promise)
  }

  await Promise.all(promises)
  return geoJsonData
}

export async function loadProvinceGeoJsonSingle(province) {
  if (!PROVINCE_MAPPING[province]) {
    console.warn(`Province "${province}" not found in mapping`)
    return null
  }

  const fileName = PROVINCE_MAPPING[province]
  console.log(`Loading province: ${province} -> file: ${fileName}`)
  const url = `/geojson_province/${fileName}?t=${Date.now()}`

  try {
    const response = await fetch(url)
    if (!response.ok) {
      throw new Error(`Failed to fetch ${fileName}: ${response.status}`)
    }
    const data = await response.json()
    return data
  } catch (error) {
    console.warn(`Error loading GeoJSON for ${province}:`, error)
    return null
  }
}

/**
 * Get all available provinces
 * @returns {string[]} Array of all available province names
 */
export function getAvailableProvinces() {
  return Object.keys(PROVINCE_MAPPING)
}

/**
 * Get provinces by region
 * @param {string} region - Region identifier (PHP enum values: I, II, III, IV-A, etc.)
 * @returns {string[]} Array of province names in the region
 */
export function getProvincesByRegion(region) {
  console.log(`getProvincesByRegion called with: "${region}"`)

  const regionMappings = {
    // PHP enum style codes
    I: ['ilocos-norte', 'ilocos-sur', 'la-union', 'pangasinan'],
    II: ['batanes', 'cagayan', 'isabela', 'nueva-vizcaya', 'quirino'],
    III: ['aurora', 'bataan', 'bulacan', 'nueva-ecija', 'pampanga', 'tarlac', 'zambales'],
    'IV-A': ['batangas', 'cavite', 'laguna', 'quezon', 'rizal'],
    'IV-B': ['marinduque', 'occidental-mindoro', 'oriental-mindoro', 'palawan', 'romblon'],
    V: ['albay', 'camarines-norte', 'camarines-sur', 'catanduanes', 'masbate', 'sorsogon'],
    VI: ['aklan', 'antique', 'capiz', 'guimaras', 'iloilo', 'negros-occidental'],
    VII: ['bohol', 'cebu', 'negros-oriental', 'siquijor'],
    VIII: ['biliran', 'eastern-samar', 'leyte', 'northern-samar', 'samar', 'southern-leyte'],
    IX: ['zamboanga-del-norte', 'zamboanga-del-sur', 'zamboanga-sibugay'],
    X: ['bukidnon', 'camiguin', 'lanao-del-norte', 'misamis-occidental', 'misamis-oriental'],
    XI: ['compostela-valley', 'davao-del-norte', 'davao-del-sur', 'davao-oriental'],
    XII: ['north-cotabato', 'sarangani', 'south-cotabato', 'sultan-kudarat'],
    XIII: [
      'agusan-del-norte',
      'agusan-del-sur',
      'dinagat-islands',
      'surigao-del-norte',
      'surigao-del-sur',
    ],
    NCR: ['metropolitan-manila'],
    CAR: ['abra', 'apayao', 'benguet', 'ifugao', 'kalinga', 'mountain-province'],
    BARMM: ['basilan', 'lanao-del-sur', 'maguindanao', 'shariff-kabunsuan', 'sulu', 'tawi-tawi'],
    NIR: ['negros-occidental', 'negros-oriental'], // Negros Island Region

    // Full name format support (from backend data)
    'Region I (Ilocos Region)': ['ilocos-norte', 'ilocos-sur', 'la-union', 'pangasinan'],
    'Region II (Cagayan Valley)': ['batanes', 'cagayan', 'isabela', 'nueva-vizcaya', 'quirino'],
    'Region III (Central Luzon)': [
      'aurora',
      'bataan',
      'bulacan',
      'nueva-ecija',
      'pampanga',
      'tarlac',
      'zambales',
    ],
    'Region IV-A (CALABARZON)': ['batangas', 'cavite', 'laguna', 'quezon', 'rizal'],
    'Region IV-B (MIMAROPA)': [
      'marinduque',
      'occidental-mindoro',
      'oriental-mindoro',
      'palawan',
      'romblon',
    ],
    'Region V (Bicol Region)': [
      'albay',
      'camarines-norte',
      'camarines-sur',
      'catanduanes',
      'masbate',
      'sorsogon',
    ],
    'Region VI (Western Visayas)': [
      'aklan',
      'antique',
      'capiz',
      'guimaras',
      'iloilo',
      'negros-occidental',
    ],
    'Region VII (Central Visayas)': ['bohol', 'cebu', 'negros-oriental', 'siquijor'],
    'Region VIII (Eastern Visayas)': [
      'biliran',
      'eastern-samar',
      'leyte',
      'northern-samar',
      'samar',
      'southern-leyte',
    ],
    'Region IX (Zamboanga Peninsula)': [
      'zamboanga-del-norte',
      'zamboanga-del-sur',
      'zamboanga-sibugay',
    ],
    'Region X (Northern Mindanao)': [
      'bukidnon',
      'camiguin',
      'lanao-del-norte',
      'misamis-occidental',
      'misamis-oriental',
    ],
    'Region XI (Davao Region)': [
      'compostela-valley',
      'davao-del-norte',
      'davao-del-sur',
      'davao-oriental',
    ],
    'Region XII (SOCCSKSARGEN)': [
      'north-cotabato',
      'sarangani',
      'south-cotabato',
      'sultan-kudarat',
    ],
    'Region XIII (Caraga)': [
      'agusan-del-norte',
      'agusan-del-sur',
      'dinagat-islands',
      'surigao-del-norte',
      'surigao-del-sur',
    ],

    // Legacy support for friendly names (optional - can be removed if not needed)
    ilocos: ['ilocos-norte', 'ilocos-sur', 'la-union', 'pangasinan'],
    'cagayan-valley': ['batanes', 'cagayan', 'isabela', 'nueva-vizcaya', 'quirino'],
    'central-luzon': [
      'aurora',
      'bataan',
      'bulacan',
      'nueva-ecija',
      'pampanga',
      'tarlac',
      'zambales',
    ],
    calabarzon: ['batangas', 'cavite', 'laguna', 'quezon', 'rizal'],
    mimaropa: ['marinduque', 'occidental-mindoro', 'oriental-mindoro', 'palawan', 'romblon'],
    bicol: ['albay', 'camarines-norte', 'camarines-sur', 'catanduanes', 'masbate', 'sorsogon'],
    'western-visayas': ['aklan', 'antique', 'capiz', 'guimaras', 'iloilo', 'negros-occidental'],
    'central-visayas': ['bohol', 'cebu', 'negros-oriental', 'siquijor'],
    'eastern-visayas': [
      'biliran',
      'eastern-samar',
      'leyte',
      'northern-samar',
      'samar',
      'southern-leyte',
    ],
    zamboanga: ['zamboanga-del-norte', 'zamboanga-del-sur', 'zamboanga-sibugay'],
    'northern-mindanao': [
      'bukidnon',
      'camiguin',
      'lanao-del-norte',
      'misamis-occidental',
      'misamis-oriental',
    ],
    davao: ['compostela-valley', 'davao-del-norte', 'davao-del-sur', 'davao-oriental'],
    soccsksargen: ['north-cotabato', 'sarangani', 'south-cotabato', 'sultan-kudarat'],
    caraga: [
      'agusan-del-norte',
      'agusan-del-sur',
      'dinagat-islands',
      'surigao-del-norte',
      'surigao-del-sur',
    ],
    ncr: ['metropolitan-manila'],
    car: ['abra', 'apayao', 'benguet', 'ifugao', 'kalinga', 'mountain-province'],
    barmm: ['basilan', 'lanao-del-sur', 'maguindanao', 'shariff-kabunsuan', 'sulu', 'tawi-tawi'],
    nir: ['negros-occidental', 'negros-oriental'],
  }

  const provinces = regionMappings[region] || []
  console.log(`Region "${region}" mapped to provinces:`, provinces)
  return provinces
}
