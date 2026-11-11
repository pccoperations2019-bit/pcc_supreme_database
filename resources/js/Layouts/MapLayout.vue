<template>
  <div class="grid min-h-svh lg:grid-cols-2">
    <div class="bg-background flex flex-col gap-4 p-6 md:p-10">
      <div class="flex justify-center gap-2 md:justify-start">
        <a href="#" class="flex items-center gap-2 font-medium">
          <div
            class="bg-primary text-primary-foreground flex h-6 w-6 items-center justify-center rounded-md"
          >
            <GalleryVerticalEnd class="size-4" />
          </div>
          Philippine Carabao Center
        </a>
      </div>

      <div class="flex flex-1 items-center justify-center">
        <div class="w-full max-w-xs">
          <LoginForm />
        </div>
      </div>
    </div>

    <div class="bg-muted relative hidden lg:block">
      <div id="map" class="absolute inset-0 h-full w-full"></div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { GalleryVerticalEnd } from 'lucide-vue-next'
import LoginForm from '@/components/auth/LoginForm.vue'

// centers from Inertia shared props
const page = usePage()
const centers = computed(() => page.props.centers ?? [])

onMounted(() => {
  const map = L.map('map', {
    zoomControl: true,
    attributionControl: false,
    minZoom: 6,
    maxZoom: 10,
  }).setView([12.8797, 121.774], 6)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map)

  const philippinesBounds = [
    [4.5, 116],
    [21.5, 127],
  ]
  map.setMaxBounds(philippinesBounds)
  map.fitBounds(philippinesBounds)
  map.on('drag', () => map.panInsideBounds(philippinesBounds, { animate: false }))

  // add markers from centers
  centers.value
    .filter(c => c?.latitude != null && c?.longitude != null)
    .forEach(c => {
      L.marker([Number(c.latitude), Number(c.longitude)])
        .addTo(map)
        .bindPopup(`<strong>${c.name}</strong>`)
    })
})
</script>

<style scoped>
#map {
  height: 100%;
  width: 100%;
}
</style>
