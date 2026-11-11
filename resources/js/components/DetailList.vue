<script setup>
import DetailRow from './DetailRow.vue'

defineProps({
  rows: { type: Array, required: true }, // [{ label, value }]
  cols: { type: String, default: 'sm:grid-cols-2' },
})
</script>

<template>
  <dl :class="['grid grid-cols-1 gap-x-8 gap-y-3', cols]">
    <DetailRow v-for="row in rows" :key="row.label" :label="row.label" :value="row.value" />
  </dl>
</template>
