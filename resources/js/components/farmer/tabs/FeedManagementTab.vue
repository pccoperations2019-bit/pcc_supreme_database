<script setup>
import { defineEmits } from 'vue'
import { Pencil } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { formatBoolYesNo } from '@/lib/utils'

defineProps({
  additionalDetails: { type: Object, default: () => ({}) },
  isEditMode: { type: Boolean, default: false },
  actionLabel: { type: String, default: 'Edit' },
})

const emit = defineEmits(['edit'])

const FEED_CATEGORIES = [
  {
    name: 'Roughage',
    items: [
      { key: 'napier', label: 'Napier' },
      { key: 'guine', label: 'Guinea Grass' },
      { key: 'paragrass', label: 'Paragrass' },
      { key: 'mombasa', label: 'Mombasa' },
      { key: 'sorghum', label: 'Sorghum' },
      { key: 'ricestraw', label: 'Rice Straw' },
      { key: 'cornstover', label: 'Corn Stover' },
      { key: 'legumes', label: 'Legumes' },
      { key: 'utrs', label: 'UTRS' },
      { key: 'silage', label: 'Silage' },
      { key: 'sakate', label: 'Sakate' },
      { key: 'forage', label: 'Forage' },
      { key: 'kamote', label: 'Kamote Leaves' },
      { key: 'ipil', label: 'Ipil-ipil' },
      { key: 'banana', label: 'Banana' },
      { key: 'acacia', label: 'Acacia' },
      { key: 'kakawate', label: 'Kakawate' },
    ],
  },
  {
    name: 'Concentrates',
    items: [
      { key: 'commercial', label: 'Commercial Feeds' },
      { key: 'ricebran', label: 'Rice Bran' },
      { key: 'grain', label: 'Corn Grain' },
      { key: 'bran', label: 'Corn Bran' },
    ],
  },
  {
    name: 'Supplements',
    items: [
      { key: 'dicalcium', label: 'Dicalcium Phosphate' },
      { key: 'salt', label: 'Salt' },
      { key: 'molasses', label: 'Molasses' },
      { key: 'atovi', label: 'Atowi' },
      { key: 'ummb', label: 'UMMB (Mineral Block)' },
    ],
  },
]

const seasonValue = (details, prefix, key) => formatBoolYesNo(details?.[`${prefix}${key}`])
</script>

<template>
  <div>
    <Card>
      <CardHeader>
        <CardTitle class="text-base">Feed Resources</CardTitle>
      </CardHeader>
      <CardContent class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left">
              <th class="py-2 pr-4 font-medium text-slate-500">Feed Resources</th>
              <th class="py-2 pr-4 text-center font-medium text-slate-500">Rainy Season</th>
              <th class="py-2 text-center font-medium text-slate-500">Dry Season</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="category in FEED_CATEGORIES" :key="category.name">
              <tr class="border-b bg-slate-50">
                <td colspan="3" class="py-2 font-semibold text-slate-700">{{ category.name }}</td>
              </tr>
              <tr v-for="item in category.items" :key="item.key" class="border-b last:border-0">
                <td class="py-2 pr-4">{{ item.label }}</td>
                <td class="py-2 pr-4 text-center">
                  {{ seasonValue(additionalDetails, 'israiny', item.key) }}
                </td>
                <td class="py-2 text-center">
                  {{ seasonValue(additionalDetails, 'isdry', item.key) }}
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </CardContent>
    </Card>
    <div v-if="isEditMode" class="mt-4">
      <Button size="sm" variant="outline" @click="emit('edit', 'feed-management')">
        <Pencil class="mr-2 h-4 w-4" />{{ actionLabel }} Feed Management
      </Button>
    </div>
  </div>
</template>
