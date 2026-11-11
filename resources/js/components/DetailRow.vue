<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number, null], default: null },
})
</script>

<template>
  <dt class="text-muted-foreground font-medium">{{ label }}</dt>
  <dd class="font-medium sm:text-right">{{ value ?? 'N/A' }}</dd>
</template>
