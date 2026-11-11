<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { auditActionLabels } from '@/enums/AuditActionEnum'
import { auditCategoryLabels } from '@/enums/AuditCategoryEnum'
import { formatDateTime } from '@/lib/utils'

/**
 * Everything one audit entry recorded, in the same words the table used.
 *
 * The backend has already turned the activity properties into labelled
 * from/to pairs and prose notes, so this dialog never has to interpret raw
 * values - it only lays them out.
 */
const props = defineProps({
  open: { type: Boolean, default: false },
  activity: { type: Object, required: false, default: null },
})

const emit = defineEmits(['update:open'])

const action = computed(() => (props.activity ? auditActionLabels[props.activity.action] : null))
const category = computed(() =>
  props.activity ? auditCategoryLabels[props.activity.category] || props.activity.category : null
)
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent
      v-if="activity"
      class="flex max-h-[90dvh] min-h-[min(24rem,90dvh)] flex-col overflow-hidden p-0 sm:max-w-lg"
    >
      <DialogHeader class="shrink-0 px-6 pt-6">
        <DialogTitle>{{ activity.activity }}</DialogTitle>
        <DialogDescription>
          {{ category }}<span v-if="action"> &middot; {{ action }}</span>
        </DialogDescription>
      </DialogHeader>

      <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 pb-6">
        <dl class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
          <dt class="text-slate-500">User</dt>
          <dd class="col-span-2 text-gray-900">
            {{ activity.user?.name || 'System' }}
            <span v-if="activity.user?.role" class="block text-xs text-slate-500">
              {{ activity.user.role }}
            </span>
          </dd>

          <dt class="text-slate-500">Subject</dt>
          <dd class="col-span-2 min-w-0 text-gray-900">
            <span v-if="activity.subject?.type" class="block text-xs text-slate-500">
              {{ activity.subject.type }}
            </span>
            <template v-if="activity.subject?.label">
              <span class="break-words">{{ activity.subject.label }}</span>
            </template>
            <span v-else>—</span>
          </dd>

          <dt class="text-slate-500">Organization</dt>
          <dd class="col-span-2 min-w-0 text-gray-900">
            <template v-if="activity.organization">
              <span class="block break-words">{{ activity.organization.name }}</span>
              <span class="block text-xs text-slate-500">{{ activity.organization.code }}</span>
            </template>
            <span v-else>—</span>
          </dd>

          <dt class="text-slate-500">Date &amp; Time</dt>
          <dd class="col-span-2 text-gray-900">{{ formatDateTime(activity.occurred_at) }}</dd>
        </dl>

        <template v-if="activity.changes?.length">
          <Separator />
          <div class="space-y-3">
            <p
              v-for="change in activity.changes"
              :key="change.label"
              class="flex flex-col gap-1 text-sm"
            >
              <span class="text-slate-500">{{ change.label }}</span>
              <span class="flex flex-wrap items-center gap-2">
                <Badge
                  v-if="change.from"
                  variant="secondary"
                  class="bg-slate-100 font-normal text-slate-700 hover:bg-slate-200"
                >
                  {{ change.from }}
                </Badge>
                <Icon
                  v-if="change.from && change.to"
                  icon="lucide:arrow-right"
                  class="h-4 w-4 text-slate-400"
                />
                <Badge
                  v-if="change.to"
                  variant="secondary"
                  class="bg-slate-100 font-normal text-slate-700 hover:bg-slate-200"
                >
                  {{ change.to }}
                </Badge>
              </span>
            </p>
          </div>
        </template>

        <template v-if="activity.notes?.length">
          <Separator />
          <div class="space-y-3">
            <p v-for="note in activity.notes" :key="note.label" class="text-sm">
              <span class="block text-slate-500">{{ note.label }}</span>
              <span class="mt-1 block rounded-md bg-slate-50 p-2 whitespace-pre-line text-gray-900">
                {{ note.value }}
              </span>
            </p>
          </div>
        </template>
      </div>
    </DialogContent>
  </Dialog>
</template>
