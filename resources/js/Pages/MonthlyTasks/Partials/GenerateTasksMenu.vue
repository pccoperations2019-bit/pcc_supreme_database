<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Icon } from '@iconify/vue'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const route = globalThis.route

const props = defineProps({
  months: { type: Object, default: () => ({}) },
  years: { type: Array, default: () => [] },
})

const now = new Date()

const year = ref(String(now.getFullYear()))
const month = ref(String(now.getMonth() + 1))
const sending = ref(false)
const open = ref(false)

const yearOptions = computed(() => {
  const years = props.years?.length ? props.years : [now.getFullYear()]

  return [...years].sort((a, b) => b - a)
})

const generate = () => {
  sending.value = true

  router.post(
    route('monthly-tasks.generate'),
    { year: Number(year.value), month: Number(month.value) },
    {
      preserveScroll: true,
      onFinish: () => {
        sending.value = false
        open.value = false
      },
    }
  )
}

watch(open, isOpen => {
  if (isOpen) {
    const today = new Date()
    year.value = String(today.getFullYear())
    month.value = String(today.getMonth() + 1)
  }
})
</script>

<template>
  <DropdownMenu v-model:open="open">
    <DropdownMenuTrigger as-child>
      <Button
        variant="outline"
        class="w-full cursor-pointer sm:w-auto"
        title="Create the full checklist for one month, for every center"
      >
        <Icon icon="lucide:calendar-plus" class="mr-2 h-4 w-4" />
        <span class="flex-1 text-left">Generate month</span>
        <Icon icon="lucide:chevron-down" class="ml-2 h-4 w-4" />
      </Button>
    </DropdownMenuTrigger>

    <DropdownMenuContent align="end" class="w-72 bg-white p-4">
      <DropdownMenuLabel class="font-normal">
        <span class="block text-sm font-semibold text-slate-900">Generate a month of tasks</span>
        <span class="mt-1 block text-xs text-slate-500">
          Creates a production and a sales task for Milk, Meat and Manure at every center. Months
          that already have tasks are left alone.
        </span>
      </DropdownMenuLabel>

      <DropdownMenuSeparator class="my-3 bg-slate-100" />

      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1.5">
          <Label for="generate_month_year" class="text-xs text-slate-700">Year</Label>
          <Select v-model="year">
            <SelectTrigger id="generate_month_year" class="h-9 w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="option in yearOptions" :key="option" :value="String(option)">
                {{ option }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-1.5">
          <Label for="generate_month_month" class="text-xs text-slate-700">Month</Label>
          <Select v-model="month">
            <SelectTrigger id="generate_month_month" class="h-9 w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="(label, value) in months" :key="value" :value="String(value)">
                {{ label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <Button class="mt-4 w-full cursor-pointer" size="sm" :disabled="sending" @click="generate">
        {{ sending ? 'Generating…' : 'Generate' }}
      </Button>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
