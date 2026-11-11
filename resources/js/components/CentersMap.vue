<script setup>
import { ref } from 'vue'
import 'leaflet/dist/leaflet.css'
import { LMap, LTileLayer, LMarker, LPopup, LTooltip } from '@vue-leaflet/vue-leaflet'
import { Icon } from 'leaflet'
delete Icon.Default.prototype._getIconUrl
Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.7.1/dist/images/marker-icon-2x.png',
  iconUrl: 'https://unpkg.com/leaflet@1.7.1/dist/images/marker-icon.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.7.1/dist/images/marker-shadow.png',
})

const props = defineProps({
  centers: {
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
    default: 6,
  },
})

const mapOptions = {
  zoomControl: true,
  attributionControl: true,
}
</script>

<template>
  <div>
    <LMap
      ref="map"
      v-model:zoom="props.zoom"
      :center="props.midpoint"
      :options="mapOptions"
      style="height: 100vh; width: 100%"
    >
      <LTileLayer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        layer-type="base"
        name="OpenStreetMap"
      />
      <template v-for="center in centers" :key="center.id">
        <LMarker :lat-lng="[center.latitude, center.longitude]">
          <LPopup>
            <div class="p-3">
              <h3 class="text-lg font-bold">PPC - {{ center.name }}</h3>
              <div class="mt-2 border-t border-gray-200 pt-2">
                <p class="mb-1"><strong>Partners:</strong> {{ center.total_partners || 0 }}</p>
                <p class="mb-1"><strong>Farmers:</strong> {{ center.total_farmers || 0 }}</p>
                <p v-if="center.meatProduction">
                  <strong>Meat Production:</strong>
                  {{ center.meatProduction }} kg
                </p>
              </div>
            </div>
          </LPopup>
          <LTooltip
            :options="{
              direction: 'top',
              permanent: false,
              className: 'carabao-center-tooltip',
            }"
          >
            <div>
              <strong>PPC - {{ center.name }}</strong>
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
