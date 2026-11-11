# CBED Map with GeoJSON Support

This enhanced map component provides visualization of PCC centers with Philippine province boundaries using GeoJSON data.

## Features

- Interactive map with Leaflet and Vue
- Province boundary visualization using GeoJSON
- Dynamic province selection by region or individual provinces
- Customizable styling for provinces
- Hover effects and click handlers for provinces
- Support for all Philippine provinces across all regions

## Components

### CBEDMap.vue
The main map component with GeoJSON support.

**Props:**
- `addresses` (Array, required): Array of address/center objects with coordinates
- `midpoint` (Array, optional): Map center coordinates [lat, lng]. Default: [12.8797, 121.774]
- `zoom` (Number, optional): Initial zoom level. Default: 6
- `showProvinces` (Boolean, optional): Show/hide province boundaries. Default: true
- `selectedProvinces` (Array, optional): Array of province names to display. Default: ['cebu', 'bohol']

**Address Object Structure:**
```javascript
{
  id: 1,
  name: 'Center Name',
  latitude: 14.5995,
  longitude: 120.9842,
  total_partners: 45,
  total_farmers: 230,
  meatProduction: 1200
}
```

### CBEDMapExample.vue
Example implementation showing how to use the map with controls.

## GeoJSON Utility

### geoJsonLoader.js
Utility functions for loading Philippine province GeoJSON data.

**Functions:**
- `loadProvinceGeoJson(provinces)`: Load GeoJSON data for specified provinces
- `getAvailableProvinces()`: Get array of all available province names
- `getProvincesByRegion(region)`: Get provinces for a specific region

**Supported Regions:**
- `central-visayas`: Bohol, Cebu, Negros Oriental, Siquijor
- `central-luzon`: Aurora, Bataan, Bulacan, Nueva Ecija, Pampanga, Tarlac, Zambales
- `calabarzon`: Batangas, Cavite, Laguna, Quezon, Rizal
- `ilocos`: Ilocos Norte, Ilocos Sur, La Union, Pangasinan
- `western-visayas`: Aklan, Antique, Capiz, Guimaras, Iloilo, Negros Occidental
- And many more...

## Usage Examples

### Basic Usage
```vue
<template>
  <CBEDMap
    :addresses="centers"
    :show-provinces="true"
    :selected-provinces="['cebu', 'bohol']"
  />
</template>

<script setup>
import CBEDMap from './CBEDMap.vue'

const centers = [
  {
    id: 1,
    name: 'Cebu Center',
    latitude: 10.3157,
    longitude: 123.8854,
    total_partners: 32,
    total_farmers: 180
  }
]
</script>
```

### Advanced Usage with Controls
```vue
<template>
  <div>
    <!-- Province Selection Controls -->
    <div class="mb-4">
      <select v-model="selectedRegion" @change="onRegionChange">
        <option value="central-visayas">Central Visayas</option>
        <option value="central-luzon">Central Luzon</option>
      </select>
    </div>
    
    <!-- Map -->
    <CBEDMap
      :addresses="centers"
      :show-provinces="showProvinces"
      :selected-provinces="selectedProvinces"
      :midpoint="[12.8797, 121.774]"
      :zoom="6"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import CBEDMap from './CBEDMap.vue'
import { getProvincesByRegion } from '../utils/geoJsonLoader.js'

const showProvinces = ref(true)
const selectedProvinces = ref(['cebu', 'bohol'])
const selectedRegion = ref('central-visayas')

const onRegionChange = () => {
  selectedProvinces.value = getProvincesByRegion(selectedRegion.value)
}
</script>
```

### Loading Province Data Programmatically
```javascript
import { loadProvinceGeoJson, getAvailableProvinces } from '../utils/geoJsonLoader.js'

// Load specific provinces
const geoData = await loadProvinceGeoJson(['cebu', 'bohol', 'negros-oriental'])

// Get all available provinces
const allProvinces = getAvailableProvinces()
console.log(allProvinces) // ['cebu', 'bohol', 'negros-oriental', ...]
```

## Customization

### Province Styling
You can customize the province appearance by modifying the style objects in CBEDMap.vue:

```javascript
const provinceStyle = {
  weight: 2,
  color: '#00563f',
  opacity: 0.8,
  fillColor: '#00563f',
  fillOpacity: 0.1,
}

const provinceHoverStyle = {
  weight: 3,
  color: '#00563f',
  opacity: 1,
  fillOpacity: 0.2,
}
```

### Event Handling
Province click and hover events can be customized in the `onEachFeature` function:

```javascript
const onEachFeature = (feature, layer) => {
  layer.on({
    click: (e) => {
      // Custom click handler
      console.log('Province clicked:', feature.properties)
      // Emit event, show modal, etc.
    }
  })
}
```

## File Structure

```
resources/js/
├── components/
│   ├── CBEDMap.vue              # Main map component
│   ├── CBEDMapExample.vue       # Example usage component
│   └── geojson_province/        # GeoJSON data files
│       ├── ph.central-visayas-region-vii.cebu.any.any.geo.json
│       ├── ph.central-visayas-region-vii.bohol.any.any.geo.json
│       └── ... (all province files)
└── utils/
    └── geoJsonLoader.js         # GeoJSON utility functions
```

## Integration with Laravel/Inertia

To use in a Laravel Inertia page:

```php
// In your Controller
public function index()
{
    $centers = Center::with(['partners', 'farmers'])
        ->get()
        ->map(function ($center) {
            return [
                'id' => $center->id,
                'name' => $center->name,
                'latitude' => $center->latitude,
                'longitude' => $center->longitude,
                'total_partners' => $center->partners->count(),
                'total_farmers' => $center->farmers->count(),
            ];
        });

    return Inertia::render('Dashboard/Map', [
        'centers' => $centers,
        'selectedProvinces' => ['cebu', 'bohol'], // Based on user selection
    ]);
}
```

```vue
<!-- In your Inertia page -->
<template>
  <Layout>
    <CBEDMap
      :addresses="centers"
      :selected-provinces="selectedProvinces"
      :show-provinces="true"
    />
  </Layout>
</template>

<script setup>
import Layout from '@/Layouts/AppLayout.vue'
import CBEDMap from '@/Components/CBEDMap.vue'

defineProps(['centers', 'selectedProvinces'])
</script>
```

## Performance Considerations

- GeoJSON files are loaded on-demand when provinces are selected
- Use `selectedProvinces` prop to limit the number of provinces loaded
- Province boundaries are cached in browser memory
- Consider implementing lazy loading for large datasets

## Browser Support

- Requires modern browser with ES6+ support
- Leaflet map library compatibility
- GeoJSON standard support