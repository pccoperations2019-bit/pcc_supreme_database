<script setup lang="ts">
import { FolderOpen } from 'lucide-vue-next'

import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: 'No data found' },
})
</script>

<template>
  <Empty class="p-8">
    <EmptyHeader>
      <EmptyMedia variant="icon">
        <FolderOpen />
      </EmptyMedia>
      <EmptyTitle>{{ title }}</EmptyTitle>
      <EmptyDescription>{{ description }}</EmptyDescription>
    </EmptyHeader>
  </Empty>
</template>
