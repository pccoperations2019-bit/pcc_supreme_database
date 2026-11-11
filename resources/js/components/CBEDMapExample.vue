<template>
  <div class="space-y-6">
    <!-- Map Controls -->
    <div class="rounded-lg bg-white p-4 shadow">
      <h2 class="mb-4 text-lg font-semibold text-gray-800">Map Controls</h2>

      <!-- Province Selection -->
      <div class="mb-4 grid grid-cols-1 gap-4 text-black md:grid-cols-2">
        <div>
          <label class="mb-2 block text-sm font-medium text-gray-700"> Select Region </label>
          <select
            v-model="selectedRegion"
            class="w-full rounded-md border border-gray-300 p-2 text-black focus:border-green-500 focus:ring-green-500"
            @change="onRegionChange"
          >
            <option value="">Custom Selection</option>
            <option value="VII">Region VII - Central Visayas</option>
            <option value="III">Region III - Central Luzon</option>
            <option value="IV-A">Region IV-A - CALABARZON</option>
            <option value="I">Region I - Ilocos Region</option>
            <option value="VI">Region VI - Western Visayas</option>
            <option value="NCR">NCR - National Capital Region</option>
            <option value="V">Region V - Bicol Region</option>
            <option value="VIII">Region VIII - Eastern Visayas</option>
            <option value="XI">Region XI - Davao Region</option>
            <option value="X">Region X - Northern Mindanao</option>
          </select>
        </div>

        <div class="text-black">
          <label class="flex items-center space-x-2">
            <input
              v-model="showProvinces"
              type="checkbox"
              class="text-green-600 focus:ring-green-500"
            />
            <span class="text-sm font-medium text-gray-700">Show Province Boundaries</span>
          </label>
        </div>
      </div>

      <!-- Province Checkboxes -->
      <div v-if="selectedRegion === ''" class="mb-4 text-black">
        <label class="mb-2 block text-sm font-medium text-gray-700">
          Select Provinces ({{ selectedProvinces.length }}/{{ availableProvinces.length }})
        </label>
        <div
          class="grid max-h-60 grid-cols-2 gap-2 overflow-y-auto rounded border border-gray-200 p-3 md:grid-cols-3 lg:grid-cols-4"
        >
          <label
            v-for="province in availableProvinces"
            :key="province"
            class="flex items-center space-x-2 text-sm"
          >
            <input
              v-model="selectedProvinces"
              type="checkbox"
              :value="province"
              class="text-green-600 focus:ring-green-500"
            />
            <span class="capitalize">{{ province.replace(/-/g, ' ') }}</span>
          </label>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="flex flex-wrap gap-2">
        <button
          class="rounded bg-green-100 px-3 py-1 text-sm text-green-700 hover:bg-green-200"
          @click="selectAllProvinces"
        >
          Select All
        </button>
        <button
          class="rounded bg-red-100 px-3 py-1 text-sm text-red-700 hover:bg-red-200"
          @click="clearSelection"
        >
          Clear All
        </button>
        <button
          class="rounded bg-blue-100 px-3 py-1 text-sm text-blue-700 hover:bg-blue-200"
          @click="centerOnPhilippines"
        >
          Center Philippines
        </button>
      </div>
    </div>

    <!-- Map Container -->
    <div class="overflow-hidden rounded-lg bg-white shadow">
      <CBEDMap
        :addresses="addresses"
        :midpoint="midpoint"
        :zoom="zoom"
        :show-provinces="showProvinces"
        :selected-provinces="selectedProvinces"
      />
    </div>

    <!-- Statistics -->
    <div class="rounded-lg bg-white p-4 shadow">
      <h3 class="mb-4 text-lg font-semibold text-gray-800">Map Statistics</h3>
      <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
        <div>
          <span class="text-gray-600">Centers:</span>
          <span class="ml-1 font-semibold">{{ addresses.length }}</span>
        </div>
        <div>
          <span class="text-gray-600">Provinces:</span>
          <span class="ml-1 font-semibold">{{ selectedProvinces.length }}</span>
        </div>
        <div>
          <span class="text-gray-600">Total Partners:</span>
          <span class="ml-1 font-semibold">{{ totalPartners }}</span>
        </div>
        <div>
          <span class="text-gray-600">Total Farmers:</span>
          <span class="ml-1 font-semibold">{{ totalFarmers }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import CBEDMap from './CBEDMap.vue'
import { getAvailableProvinces, getProvincesByRegion } from '../utils/geoJsonLoader.js'

// Sample data - replace with actual data from your backend
const addresses = ref([
  {
    id: 1,
    name: 'Central Office',
    latitude: 14.5995,
    longitude: 120.9842,
    total_partners: 45,
    total_farmers: 230,
    meatProduction: 1200,
  },
  {
    id: 2,
    name: 'Visayas Center',
    latitude: 10.3157,
    longitude: 123.8854,
    total_partners: 32,
    total_farmers: 180,
    meatProduction: 950,
  },
  {
    id: 3,
    name: 'Mindanao Center',
    latitude: 7.1907,
    longitude: 125.4553,
    total_partners: 28,
    total_farmers: 165,
    meatProduction: 880,
  },
])

// Map configurationVII
const midpoint = ref([12.8797, 121.774]) // Center of Philippines
const zoom = ref(6)
const showProvinces = ref(true)
const selectedProvinces = ref(['cebu', 'bohol']) // Default to Central Visayas
const selectedRegion = ref('central-visayas')
const availableProvinces = ref([])

// Computed properties
const totalPartners = computed(() =>
  addresses.value.reduce((sum, addr) => sum + (addr.total_partners || 0), 0)
)

const totalFarmers = computed(() =>
  addresses.value.reduce((sum, addr) => sum + (addr.total_farmers || 0), 0)
)

// Methods
const onRegionChange = () => {
  if (selectedRegion.value) {
    selectedProvinces.value = getProvincesByRegion(selectedRegion.value)
  }
}

const selectAllProvinces = () => {
  selectedProvinces.value = [...availableProvinces.value]
  selectedRegion.value = ''
}

const clearSelection = () => {
  selectedProvinces.value = []
  selectedRegion.value = ''
}

const centerOnPhilippines = () => {
  midpoint.value = [12.8797, 121.774]
  zoom.value = 6
}

// Initialize
onMounted(() => {
  availableProvinces.value = getAvailableProvinces()
})
</script>

<style scoped>
/* Custom styles for the map example */
.space-y-6 > * + * {
  margin-top: 1.5rem;
}

.grid {
  display: grid;
}

.grid-cols-1 {
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

.grid-cols-2 {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.grid-cols-3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.grid-cols-4 {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

@media (min-width: 768px) {
  .md\\:grid-cols-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .md\\:grid-cols-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .md\\:grid-cols-4 {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (min-width: 1024px) {
  .lg\\:grid-cols-4 {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.gap-2 {
  gap: 0.5rem;
}

.gap-4 {
  gap: 1rem;
}
</style>
