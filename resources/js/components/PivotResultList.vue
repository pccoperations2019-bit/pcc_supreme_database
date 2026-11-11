<script setup>
import { computed } from 'vue'

const props = defineProps({
  result: {
    type: Object,
    required: true,
  },

  fields: {
    type: Array,
    default: () => [],
  },
})

const columns = computed(() => props.result.columns ?? [])

const labelMap = computed(() =>
  Object.fromEntries(props.fields.map(field => [field.field, field.label]))
)
</script>

<template>
  <div class="rounded border bg-white p-4">
    <div class="mb-2 flex items-center justify-between">
      <h3 class="text-sm font-medium">List Results</h3>
      <span class="text-xs text-gray-500"> {{ result.total }} records </span>
    </div>

    <div v-if="columns.length === 0" class="py-4 text-center text-sm text-gray-500">
      No data available
    </div>

    <div v-else class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b text-left text-xs text-gray-500">
            <th v-for="col in columns" :key="col" class="py-1 pr-2 font-medium whitespace-nowrap">
              {{ labelMap[col] ?? col }}
            </th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="(row, index) in result.rows" :key="index" class="border-b border-gray-100">
            <td v-for="col in columns" :key="col" class="py-1 pr-2 whitespace-nowrap text-gray-700">
              {{ row[col] ?? '' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
