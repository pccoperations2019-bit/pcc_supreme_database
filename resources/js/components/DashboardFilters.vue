<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

defineProps({
  years: { type: Array, default: () => [] },
  months: { type: Array, default: () => [] },
  programOptions: { type: Array, default: () => [] },
  productionYear: { type: [Number, String], default: null },
  productionMonth: { type: [Number, String], default: 'all' },
  programFilter: { type: String, default: '' },
})

const emit = defineEmits([
  'update:productionYear',
  'update:productionMonth',
  'update:programFilter',
])
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex gap-2">
      <div>
        <div class="text-xs font-medium text-slate-300">Year</div>
        <Select
          :model-value="String(productionYear)"
          @update:model-value="val => emit('update:productionYear', Number(val))"
        >
          <SelectTrigger class="mt-1 h-8 w-20 sm:w-24">
            <SelectValue placeholder="Select year" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="y in years" :key="y" :value="String(y)">
              {{ y }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div>
        <div class="text-xs font-medium text-slate-300">Month</div>
        <Select
          :model-value="
            productionMonth === 'all' || productionMonth === '' || productionMonth === null
              ? 'all'
              : String(productionMonth)
          "
          @update:model-value="
            val => emit('update:productionMonth', val === 'all' ? 'all' : Number(val))
          "
        >
          <SelectTrigger class="mt-1 h-8 w-28 sm:w-32">
            <SelectValue placeholder="All Months" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Months</SelectItem>
            <SelectItem v-for="m in months" :key="m.value" :value="String(m.value)">
              {{ m.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>
    <div class="flex gap-2">
      <div>
        <div class="text-xs font-medium text-slate-300">Program</div>
        <Select
          :model-value="String(programFilter)"
          @update:model-value="val => emit('update:programFilter', val)"
        >
          <SelectTrigger class="mt-1 h-8 w-40 sm:w-48">
            <SelectValue placeholder="Select program" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="option in programOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>
  </div>
</template>
