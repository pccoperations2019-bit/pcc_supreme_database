<script setup lang="ts">
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
</script>

<template>
  <Tabs default-value="overview" class="space-y-4">
    <TabsList>
      <TabsTrigger value="overview">Overview</TabsTrigger>
      <TabsTrigger value="production" disabled>Production</TabsTrigger>
      <TabsTrigger value="consolidation" disabled>Consolidation</TabsTrigger>
      <TabsTrigger value="processing" disabled>Processing</TabsTrigger>
      <TabsTrigger value="marketing" disabled>Marketing</TabsTrigger>
    </TabsList>
  </Tabs>
</template>
