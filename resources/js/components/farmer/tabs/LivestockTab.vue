<script setup>
import { defineProps, defineEmits } from 'vue'
import { Pencil } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

defineProps({
  farmer: { type: Object, required: true },
  isEditMode: { type: Boolean, default: false },
  actionLabel: { type: String, default: 'Edit' },
})

const emit = defineEmits(['edit'])
</script>

<template>
  <div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <Card>
        <CardHeader>
          <CardTitle class="text-base">Cattle</CardTitle>
        </CardHeader>
        <CardContent>
          <dl class="space-y-2">
            <div class="flex justify-between">
              <dt class="text-slate-600">Dairy Cattle:</dt>
              <dd class="font-medium">{{ farmer.dairy_cattle || 0 }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-600">Beef Cattle:</dt>
              <dd class="font-medium">{{ farmer.beef_cattle || 0 }}</dd>
            </div>
          </dl>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle class="text-base">Poultry</CardTitle>
        </CardHeader>
        <CardContent>
          <dl class="space-y-2">
            <div class="flex justify-between">
              <dt class="text-slate-600">Chicken:</dt>
              <dd class="font-medium">{{ farmer.chicken || 0 }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-600">Duck:</dt>
              <dd class="font-medium">{{ farmer.duck || 0 }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-600">Geese:</dt>
              <dd class="font-medium">{{ farmer.geese || 0 }}</dd>
            </div>
          </dl>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle class="text-base">Others</CardTitle>
        </CardHeader>
        <CardContent>
          <dl class="space-y-2">
            <div class="flex justify-between">
              <dt class="text-slate-600">Goat:</dt>
              <dd class="font-medium">{{ farmer.goat || 0 }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-600">Swine:</dt>
              <dd class="font-medium">{{ farmer.swine || 0 }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-600">Horse:</dt>
              <dd class="font-medium">{{ farmer.horse || 0 }}</dd>
            </div>
            <template v-if="farmer.other_livestock_details?.length">
              <div
                v-for="(item, i) in farmer.other_livestock_details"
                :key="i"
                class="flex justify-between"
              >
                <dt class="text-slate-600">{{ item.label || 'Other Livestock' }}:</dt>
                <dd class="font-medium">{{ item.count ?? 0 }}</dd>
              </div>
            </template>
            <template v-else>
              <div class="flex justify-between">
                <dt class="text-slate-600">Other Livestock:</dt>
                <dd class="font-medium">
                  {{ farmer.other_livestock_count || farmer.other_livestock || 0 }}
                </dd>
              </div>
            </template>
          </dl>
        </CardContent>
      </Card>
    </div>
    <div v-if="isEditMode" class="mt-4">
      <Button size="sm" variant="outline" @click="emit('edit', 'livestock-info')">
        <Pencil class="mr-2 h-4 w-4" />{{ actionLabel }} Livestock Information
      </Button>
    </div>
  </div>
</template>
