<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import 'leaflet/dist/leaflet.css'
import { LMap, LTileLayer, LMarker, LTooltip, LGeoJson } from '@vue-leaflet/vue-leaflet'
import { Icon } from 'leaflet'
import { loadProvincesGeoJson } from '../utils/geoJsonLoader.js'

// center-selected emit kept for deep-link via parent (Dashboard.vue:340 handleCenterSelected)
// but marker/legend clicks are now local-only to avoid Inertia remount flicker
const emit = defineEmits(['center-selected']) // eslint-disable-line no-unused-vars

// Configure Leaflet default icons
delete Icon.Default.prototype._getIconUrl
Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.7.1/dist/images/marker-icon-2x.png',
  iconUrl: 'https://unpkg.com/leaflet@1.7.1/dist/images/marker-icon.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.7.1/dist/images/marker-shadow.png',
})

const props = defineProps({
  addresses: {
    type: Array,
    required: true,
  },
  midpoint: {
    type: Array,
    required: false,
    default: () => [12.8797, 121.774],
  },
  zoom: {
    type: Number,
    required: false,
    default: 4,
  },
  showProvinces: {
    type: Boolean,
    required: false,
    default: true,
  },
  selectedProvinces: {
    type: Array,
    required: false,
    default: () => ['cebu', 'bohol'],
  },
  provinceColors: {
    type: Object,
    required: false,
    default: () => ({}),
  },
  centers: {
    type: Object,
    required: false,
    default: () => ({}),
  },
  selectedCenterId: {
    type: Number,
    required: false,
    default: null,
  },
})

const mapOptions = {
  zoomControl: true,
  attributionControl: true,
}

const map = ref(null)
const selectedCenter = ref(null)
const showCenterDetails = ref(false)
const isInitialized = ref(false)
const currentZoom = ref(props.zoom)
const currentCenter = ref(props.midpoint)

// GeoJSON data storage
const geoJsonData = ref({})
const isLoadingGeoJson = ref(false)
const refreshKey = ref(0)

const hasValidLatLng = address => {
  const rawLatitude = address?.latitude
  const rawLongitude = address?.longitude

  if (rawLatitude === null || rawLatitude === undefined || rawLatitude === '') return false
  if (rawLongitude === null || rawLongitude === undefined || rawLongitude === '') return false

  if (rawLatitude === 'null' || rawLatitude === 'NULL') return false
  if (rawLongitude === 'null' || rawLongitude === 'NULL') return false

  const latitude = typeof rawLatitude === 'number' ? rawLatitude : Number.parseFloat(rawLatitude)
  const longitude =
    typeof rawLongitude === 'number' ? rawLongitude : Number.parseFloat(rawLongitude)

  return Number.isFinite(latitude) && Number.isFinite(longitude)
}

const validAddresses = computed(() => {
  return (props.addresses || []).filter(hasValidLatLng)
})

// Load GeoJSON data for provinces
const loadGeoJsonData = async () => {
  if (props.showProvinces && props.selectedProvinces.length > 0) {
    console.log('Loading GeoJSON for provinces:', props.selectedProvinces)
    console.log('Province colors mapping:', props.provinceColors)

    isLoadingGeoJson.value = true
    try {
      geoJsonData.value = await loadProvincesGeoJson(props.selectedProvinces)
      console.log('Successfully loaded GeoJSON data for provinces:', Object.keys(geoJsonData.value))
      console.log('Colors available for styling:', props.provinceColors)
    } catch (error) {
      console.error('Error loading GeoJSON data:', error)
      geoJsonData.value = {}
    } finally {
      isLoadingGeoJson.value = false
    }
  } else {
    geoJsonData.value = {}
  }
}

// Generate province-specific styling based on PCC center colors
const getProvinceStyle = provinceName => {
  const assignedColor = props.provinceColors[provinceName] || '#00563f'
  console.log(`Styling province: ${provinceName}, Color: ${assignedColor}`)

  return {
    weight: 2,
    color: assignedColor,
    opacity: 0.8,
    fillColor: assignedColor,
    fillOpacity: 0.3,
  }
}

// Watch for changes in provinces or color assignments
watch(
  [() => props.selectedProvinces, () => props.provinceColors, () => props.showProvinces],
  () => {
    console.log('Props changed, reloading GeoJSON data...')
    geoJsonData.value = {}
    refreshKey.value++
    loadGeoJsonData()
  },
  { deep: true }
)

const handleLegendClick = (center, evt) => {
  if (!hasValidLatLng(center)) return
  if (evt && typeof evt.originalEvent?.stopPropagation === 'function') {
    evt.originalEvent.stopPropagation()
  }
  if (evt && typeof evt.stopPropagation === 'function') {
    try {
      evt.stopPropagation()
    } catch (e) {
      void e
    }
  }

  // Show modal instantly without filtering dashboard — filtering is via sidebar.
  // Keeping modal local prevents the Inertia visit from handleCenterSelected
  // from remounting CBEDMap and hiding the modal.
  selectedCenter.value = center
  showCenterDetails.value = true

  if (map.value && map.value.leafletObject) {
    const leafletMap = map.value.leafletObject
    leafletMap.closePopup()
    leafletMap.flyTo([center.latitude, center.longitude], 7, {
      animate: true,
      duration: 1.5,
      easeLinearity: 0.25,
    })
  } else {
    currentCenter.value = [center.latitude, center.longitude]
    currentZoom.value = 7
  }
}

const handleMarkerClick = (address, evt) => {
  handleLegendClick(address, evt)
}

const closeCenterDetails = () => {
  showCenterDetails.value = false
  selectedCenter.value = null
  // Don't emit filter clear on close — modal close is local only.
}

const initializeSelectedCenter = () => {
  // Keep modal open if the same center is already selected — avoids flicker
  // when parent triggers an Inertia visit (handleCenterSelected) that remounts props.
  if (props.selectedCenterId && selectedCenter.value?.id === props.selectedCenterId) {
    return
  }
  if (!props.selectedCenterId) {
    showCenterDetails.value = false
    selectedCenter.value = null
    return
  }
  if (props.addresses && props.addresses.length > 0) {
    const center = props.addresses.find(c => c.id === props.selectedCenterId)
    if (center && hasValidLatLng(center)) {
      selectedCenter.value = center
      showCenterDetails.value = true
      if (map.value && map.value.leafletObject) {
        const leafletMap = map.value.leafletObject
        leafletMap.flyTo([center.latitude, center.longitude], 7, {
          animate: false,
        })
      } else {
        currentCenter.value = [center.latitude, center.longitude]
        currentZoom.value = 7
      }
      return
    }
  }
  // No match — close
  showCenterDetails.value = false
  selectedCenter.value = null
}

watch(
  () => props.selectedCenterId,
  () => {
    initializeSelectedCenter()
  }
)

// Initialize component
onMounted(() => {
  showCenterDetails.value = false
  selectedCenter.value = null
  isInitialized.value = false

  loadGeoJsonData()

  window.setTimeout(() => {
    if (props.addresses && props.addresses.length > 0) {
      initializeSelectedCenter()
    }
    window.setTimeout(() => {
      isInitialized.value = true
    }, 100)
  }, 300)
})
</script>

<template>
  <!-- Contain Leaflet's pane and control layers below page dialogs. -->
  <div class="relative isolate">
    <!-- PCC Centers Legend - Overlay on Map (Top Right, Vertical List) — outside LMap so Leaflet doesn't manage its DOM -->
    <div
      v-if="addresses && addresses.length > 0"
      class="absolute top-4 right-4 z-[1000] max-h-[50vh] w-22 overflow-y-auto rounded-xl border border-gray-200 bg-white/95 p-2 shadow-lg backdrop-blur-md lg:top-6 lg:right-8 lg:max-h-[70vh] lg:w-31 lg:p-3"
    >
      <div class="space-y-2">
        <div
          v-for="center in validAddresses"
          :key="center.id || center.code"
          class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 transition-all hover:bg-gray-100"
          @click.stop="handleLegendClick(center, $event)"
        >
          <span
            class="h-3 w-3 flex-shrink-0 rounded-sm shadow-sm"
            :style="{ backgroundColor: center.color || '#00563f' }"
          ></span>
          <span class="truncate text-xs font-bold text-gray-700">
            {{ center.code || center.name }}
          </span>
        </div>
      </div>
    </div>
    <!-- Center Details Panel — outside LMap so flyTo/Leaflet DOM updates don't unmount it -->
    <div
      v-if="isInitialized && showCenterDetails && selectedCenter && selectedCenter.id"
      class="absolute top-16 left-2 z-[1001] w-72 rounded-lg bg-white p-4 shadow-xl lg:top-20 lg:left-4 lg:w-80 lg:p-6"
      @click.stop
    >
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold text-gray-800">
          {{ selectedCenter.name || selectedCenter.code }}
        </h3>
        <button
          class="rounded-full p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
          @click="closeCenterDetails"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clip-rule="evenodd"
            />
          </svg>
        </button>
      </div>
      <div class="space-y-3 border-t border-gray-200 pt-4">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-gray-600">Partners:</span>
          <span class="text-sm font-bold text-gray-900">
            {{ selectedCenter.total_partners ?? 0 }}
          </span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-gray-600">Farmers:</span>
          <span class="text-sm font-bold text-gray-900">
            {{ selectedCenter.total_farmers ?? 0 }}
          </span>
        </div>
        <div v-if="selectedCenter.meatProduction" class="flex items-center justify-between">
          <span class="text-sm font-medium text-gray-600">Meat Production:</span>
          <span class="text-sm font-bold text-gray-900">
            {{ selectedCenter.meatProduction }} kg
          </span>
        </div>
        <div v-if="selectedCenter.address" class="mt-3 border-t border-gray-200 pt-3">
          <span class="text-xs text-gray-500">{{ selectedCenter.address }}</span>
        </div>
      </div>
    </div>

    <LMap
      ref="map"
      v-model:zoom="currentZoom"
      v-model:center="currentCenter"
      :options="mapOptions"
      style="height: 100vh; width: 100%"
    >
      <LTileLayer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        layer-type="base"
        name="OpenStreetMap"
      />

      <!-- PCC Province Coverage Areas with Dynamic Colors -->
      <template v-if="showProvinces && !isLoadingGeoJson">
        <template
          v-for="(data, province) in geoJsonData"
          :key="`${province}-${refreshKey}-${provinceColors[province] || 'default'}`"
        >
          <LGeoJson :geojson="data" :options-style="getProvinceStyle(province)" />
        </template>
      </template>

      <template v-for="address in validAddresses" :key="address.id">
        <LMarker
          :lat-lng="[address.latitude, address.longitude]"
          @click="e => handleMarkerClick(address, e)"
        >
          <LTooltip
            :options="{
              direction: 'top',
              permanent: false,
              className: 'carabao-center-tooltip',
            }"
          >
            <div>
              <strong>{{ address.name || address.code }}</strong>
            </div>
          </LTooltip>
        </LMarker>
      </template>
    </LMap>
  </div>
</template>

<style>
.carabao-center-tooltip {
  background-color: white;
  border: 1px solid #ccc;
  padding: 5px 8px;
  border-radius: 4px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  font-size: 13px;
  font-weight: 500;
}
.carabao-center-tooltip strong {
  color: #00563f;
}
</style>
