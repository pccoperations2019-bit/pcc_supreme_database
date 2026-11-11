<script setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import DetailList from '@/components/DetailList.vue'

defineProps({
  title: { type: String, required: true },
  rows: { type: Array, required: true }, // [{ label, value }]
  cols: { type: String, default: 'sm:grid-cols-2' }, // passed to DetailList
})
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-base">{{ title }}</CardTitle>
    </CardHeader>
    <CardContent>
      <DetailList :rows="rows" :cols="cols" />
    </CardContent>
  </Card>
</template>
