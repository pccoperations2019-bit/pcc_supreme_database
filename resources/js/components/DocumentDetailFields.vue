<script setup>
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'

/**
 * The resolved facts about one upload: what it is, what it is attached to, and
 * the tags it was filed under.
 *
 * The backend has already turned tag keys and values into things a person can
 * read, so this only lays them out. Shared by the Documents list dialog and the
 * document page so both describe an upload in the same words.
 */
const props = defineProps({
  details: { type: Object, required: false, default: () => ({}) },
})

const facts = computed(() => props.details?.details ?? [])
const tags = computed(() => props.details?.tags ?? [])
</script>

<template>
  <div class="space-y-6">
    <!-- Named facts: the kind of document, what it hangs off, its own columns -->
    <dl v-if="facts.length" class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
      <template v-for="fact in facts" :key="fact.label">
        <dt class="text-slate-500">{{ fact.label }}</dt>
        <dd class="col-span-2 min-w-0 break-words text-gray-900">{{ fact.value }}</dd>
      </template>
    </dl>

    <!-- Tags: the upload form's own wording, so a mis-tag is visible at a glance -->
    <div v-if="tags.length">
      <ul class="space-y-2">
        <li
          v-for="tag in tags"
          :key="tag.key"
          class="flex flex-wrap items-baseline gap-x-2 text-sm"
        >
          <span class="shrink-0 text-slate-500">{{ tag.label }}</span>
          <Badge
            variant="secondary"
            class="bg-slate-100 font-normal text-slate-700 hover:bg-slate-200"
          >
            {{ tag.label_value }}
          </Badge>
        </li>
      </ul>
    </div>

    <p v-else-if="!facts.length" class="text-sm text-slate-500">This document carries no tags.</p>
  </div>
</template>
