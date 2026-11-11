<script setup>
import { computed, defineProps, defineEmits } from 'vue'
import { Pencil } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import DetailList from '@/components/DetailList.vue'

const props = defineProps({
  farmer: { type: Object, required: true },
  isEditMode: { type: Boolean, default: false },
  actionLabel: { type: String, default: 'Edit' },
})

const emit = defineEmits(['edit'])

const assetDetailRows = computed(() => [
  { label: 'PCC Projects Assets:', value: props.farmer.pcc_projects_assets },
  {
    label: 'Status of Google Map Registration:',
    value: props.farmer.status_google_map,
  },
  { label: 'Gps Location Latitude:', value: props.farmer.pcc_assets_latitude },
  { label: 'Gps Location Longitude:', value: props.farmer.pcc_assets_longitude },
])
</script>

<template>
  <div>
    <Card>
      <CardHeader>
        <CardTitle class="text-base">Assets</CardTitle>
      </CardHeader>
      <CardContent>
        <DetailList :rows="assetDetailRows" />
        <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
          <dt class="text-muted-foreground font-medium">Google Maps Link:</dt>
          <dd class="font-medium sm:text-right">
            <a
              v-if="farmer.google_map_link"
              :href="farmer.google_map_link"
              target="_blank"
              rel="noopener noreferrer"
              class="text-blue-600 hover:underline"
              >Open link</a
            >
            <span v-else>N/A</span>
          </dd>
          <dt class="text-muted-foreground font-medium">Geotag Image:</dt>
          <dd class="font-medium sm:text-right">
            <div class="flex items-center gap-3 sm:justify-end">
              <img
                v-if="farmer.geotag_image_url"
                :src="farmer.geotag_image_url"
                alt="Geotag"
                class="h-10 w-10 rounded border object-cover"
              />
              <span v-if="!farmer.geotag_image_url">N/A</span>
            </div>
          </dd>
          <dt class="text-muted-foreground font-medium">Entrepreneur Image:</dt>
          <dd class="font-medium sm:text-right">
            <div class="flex items-center gap-3 sm:justify-end">
              <img
                v-if="farmer.entrepreneur_image_url"
                :src="farmer.entrepreneur_image_url"
                alt="Entrepreneur"
                class="h-10 w-10 rounded border object-cover"
              />
              <span v-if="!farmer.entrepreneur_image_url">N/A</span>
            </div>
          </dd>
          <dt class="text-muted-foreground font-medium">Enterprise Image:</dt>
          <dd class="font-medium sm:text-right">
            <div class="flex items-center gap-3 sm:justify-end">
              <img
                v-if="farmer.enterprise_image_url"
                :src="farmer.enterprise_image_url"
                alt="Enterprise"
                class="h-10 w-10 rounded border object-cover"
              />
              <span v-if="!farmer.enterprise_image_url">N/A</span>
            </div>
          </dd>
        </dl>
      </CardContent>
    </Card>
    <div v-if="isEditMode" class="mt-4">
      <Button size="sm" variant="outline" @click="emit('edit', 'geotag')">
        <Pencil class="mr-2 h-4 w-4" />{{ actionLabel }} Assets
      </Button>
    </div>
  </div>
</template>
