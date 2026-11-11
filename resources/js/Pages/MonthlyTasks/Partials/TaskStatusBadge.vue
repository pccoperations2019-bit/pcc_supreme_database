<script setup>
import { Badge } from '@/components/ui/badge'
import { Icon } from '@iconify/vue'
import { computed } from 'vue'
import {
  monthlyTaskStatus,
  monthlyTaskStatusBadgeClass,
  monthlyTaskStatusIcon,
  monthlyTaskStatusLabel,
} from '@/enums/MonthlyTaskEnum'

const props = defineProps({
  task: { type: Object, required: true },
})

const label = computed(() => monthlyTaskStatusLabel(props.task))
const badgeClass = computed(() => monthlyTaskStatusBadgeClass(props.task))
const icon = computed(() => monthlyTaskStatusIcon(props.task))
const status = computed(() => monthlyTaskStatus(props.task))
</script>

<template>
  <Badge
    variant="secondary"
    class="w-fit gap-1 border"
    :class="badgeClass"
    :aria-label="`Status: ${label}`"
    :data-status="status"
  >
    <Icon :icon="icon" class="h-3.5 w-3.5" aria-hidden="true" />
    {{ label }}
  </Badge>
</template>
