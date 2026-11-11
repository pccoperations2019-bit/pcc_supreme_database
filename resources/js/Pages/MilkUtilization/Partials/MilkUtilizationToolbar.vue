<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import { Button } from '@/components/ui/button'
import { ButtonGroup } from '@/components/ui/button-group'
import { Label } from '@/components/ui/label'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useAuth } from '@/composable/useAuth'

const props = defineProps({
  years: { type: Array, default: () => [] },
  centers: { type: Array, default: () => [] },
  modelValue: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
  canExport: { type: Boolean, default: false },
  queueing: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'export'])

const { isCenterScoped, user } = useAuth()

const ALL_CENTERS = 'all'

const update = patch => emit('update:modelValue', patch)

const year = computed({
  get: () => (props.modelValue.year === null ? '' : String(props.modelValue.year)),
  set: value => update({ year: Number(value) }),
})

const center = computed({
  get: () =>
    String(
      isCenterScoped.value && user.value?.center_id
        ? user.value.center_id
        : (props.modelValue.center ?? ALL_CENTERS)
    ),
  set: value => update({ center: value }),
})

const pinnedCenterLabel = computed(() => {
  const option = props.centers.find(item => String(item.value) === String(user.value?.center_id))
  const code = user.value?.center?.code ?? option?.label
  return code
    ? `PCC at ${String(code)
        .replace(/^(?:PCC\s+at\s+)+/i, '')
        .trim()}`
    : 'Your center'
})

const yearOptions = computed(() =>
  props.years.map(option => ({ ...option, value: String(option.value) }))
)
const centerOptions = computed(() =>
  props.centers.map(option => ({ ...option, value: String(option.value) }))
)

const handleExport = format => {
  emit('export', format)
}
</script>

<template>
  <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
    <div class="space-y-2">
      <Label for="milk_utilization_year">Year</Label>
      <Select v-model="year" :disabled="disabled">
        <SelectTrigger id="milk_utilization_year" class="h-9 w-full">
          <SelectValue placeholder="Year" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="option in yearOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div class="space-y-2">
      <Label for="milk_utilization_center">Center</Label>
      <Select v-model="center" :disabled="disabled || isCenterScoped">
        <SelectTrigger id="milk_utilization_center" class="h-9 w-full">
          <SelectValue v-if="isCenterScoped">{{ pinnedCenterLabel }}</SelectValue>
          <SelectValue v-else placeholder="All centers" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="ALL_CENTERS">All centers</SelectItem>
          <SelectItem v-for="option in centerOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </SelectItem>
        </SelectContent>
      </Select>
      <p v-if="isCenterScoped" class="text-xs text-slate-500">Pinned to your center.</p>
    </div>

    <div class="flex items-end md:justify-end">
      <DropdownMenu v-if="canExport">
        <DropdownMenuTrigger as-child>
          <ButtonGroup prevent-default>
            <Button
              variant="secondary"
              prevent-default
              class="text-xs sm:text-sm"
              :disabled="queueing"
            >
              Export
            </Button>
            <Button variant="secondary" class="px-2" :disabled="queueing">
              <Icon
                :icon="queueing ? 'lucide:loader' : 'lucide:download'"
                class="size-3.5 sm:size-4"
              />
            </Button>
          </ButtonGroup>
        </DropdownMenuTrigger>
        <DropdownMenuContent>
          <DropdownMenuGroup>
            <DropdownMenuItem @click="handleExport('xlsx')"> Download as xlsx </DropdownMenuItem>
            <DropdownMenuItem @click="handleExport('csv')"> Download as csv </DropdownMenuItem>
          </DropdownMenuGroup>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </div>
</template>
