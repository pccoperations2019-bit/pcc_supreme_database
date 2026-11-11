<script setup>
import { ref, watch, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Icon } from '@iconify/vue'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { toastFlash } from '@/lib/flashToasts'
import {
  monthlyTaskProductLabel,
  monthlyTaskProgramLabel,
  monthlyTaskTypeLabel,
} from '@/enums/MonthlyTaskEnum'
import { displayCenterLabel } from '@/lib/center'

const props = defineProps({
  open: { type: Boolean, default: false },
  task: { type: Object, default: null },
})

const emit = defineEmits(['update:open'])

const mode = ref('approve')

const form = useForm({
  note: '',
  return_reason: '',
})

watch(
  () => props.open,
  isOpen => {
    if (!isOpen) return

    mode.value = 'approve'
    form.note = ''
    form.return_reason = ''
    form.clearErrors()
  }
)

const close = () => emit('update:open', false)

const submit = () => {
  const isReturn = mode.value === 'return'

  if (isReturn && !form.return_reason.trim()) {
    form.setError('return_reason', 'Say what needs correcting, so the center knows what to fix.')

    return
  }

  form.post(
    isReturn
      ? route('monthly-tasks.return', props.task.id)
      : route('monthly-tasks.approve', props.task.id),
    {
      onSuccess: () => {
        close()
        toastFlash({ success: isReturn ? 'Returned to the center.' : 'Figures approved.' })
      },
      onError: errors => {
        const first = Object.values(errors || {})[0]
        const message = Array.isArray(first) ? first[0] : first
        toastFlash({ error: message || 'Could not record the decision.' })
      },
    }
  )
}

const describe = () => {
  if (!props.task) return ''

  return [
    monthlyTaskTypeLabel(props.task.task_type),
    monthlyTaskProductLabel(props.task.product_type),
    'for',
    props.task.period,
    'under',
    monthlyTaskProgramLabel(props.task.program),
  ].join(' ')
}

const centerLabel = computed(() => {
  if (!props.task?.center) return '—'
  return displayCenterLabel(props.task.center.code) || props.task.center.name
})
</script>

<template>
  <Dialog :open="open" @update:open="value => emit('update:open', value)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Review submitted figures</DialogTitle>
        <DialogDescription>
          {{ describe() }}
        </DialogDescription>
      </DialogHeader>

      <div v-if="task" class="flex flex-col gap-4">
        <dl
          class="grid grid-cols-2 gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 sm:grid-cols-3"
        >
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Center</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ centerLabel }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Total</dt>
            <dd class="mt-1 text-sm font-medium text-slate-900">
              {{ task.total_formatted || '—' }}
            </dd>
          </div>
        </dl>

        <div
          v-if="task.production_data_rows && task.production_data_rows.length > 0"
          class="space-y-3"
        >
          <h3 class="text-sm font-medium text-slate-900">
            Data Entries ({{ task.production_data_rows.length }})
          </h3>
          <div
            class="max-h-60 space-y-2 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50"
          >
            <div
              v-for="(row, index) in task.production_data_rows"
              :key="row.id || index"
              class="flex items-center justify-between rounded-md border border-slate-200 bg-white px-4 py-3"
            >
              <div class="flex-1">
                <template v-if="row.partner_name || row.farmer_name">
                  <div v-if="row.partner_name" class="text-sm text-slate-900">
                    <span class="font-medium text-slate-500">Partner:</span>
                    {{ row.partner_name }}
                  </div>
                  <div v-if="row.farmer_name" class="text-sm text-slate-900">
                    <span class="font-medium text-slate-500">Farmer:</span>
                    {{ row.farmer_name }}
                  </div>
                </template>
                <div v-else class="text-sm font-medium text-slate-900">Entry #{{ index + 1 }}</div>
                <div class="text-xs text-slate-500">
                  {{ task.task_type === 'sales' ? 'Sales' : 'Volume' }}:
                  {{ Number(row.volume ?? 0).toLocaleString() }}
                </div>
              </div>
              <div class="text-right">
                <div class="text-xs text-slate-500">Entered by</div>
                <div class="text-sm text-slate-900">
                  {{ row.encoder_name || task.encoder_name || '—' }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <p
          v-if="task.return_reason"
          class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
          <Icon icon="lucide:undo-2" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
          <span>
            <span class="font-medium">Previously returned:</span>
            {{ task.return_reason }}
          </span>
        </p>

        <div class="flex gap-2" role="group" aria-label="Decision">
          <Button
            type="button"
            variant="outline"
            class="flex-1 cursor-pointer"
            :class="{ 'bg-emerald-50 text-emerald-800': mode === 'approve' }"
            @click="mode = 'approve'"
          >
            <Icon icon="lucide:check" class="mr-2 h-4 w-4" />
            Approve
          </Button>
          <Button
            type="button"
            variant="outline"
            class="flex-1 cursor-pointer"
            :class="{ 'bg-amber-50 text-amber-900': mode === 'return' }"
            @click="mode = 'return'"
          >
            <Icon icon="lucide:undo-2" class="mr-2 h-4 w-4" />
            Return for correction
          </Button>
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
          <div v-if="mode === 'approve'" class="space-y-2">
            <Label for="review_note">Note (optional)</Label>
            <Textarea
              id="review_note"
              v-model="form.note"
              rows="2"
              placeholder="e.g. Figures tie out against the center's delivery receipts."
            />
          </div>

          <div v-else class="space-y-2">
            <Label for="review_reason"
              >What needs correcting <span class="text-red-500">*</span></Label
            >
            <Textarea
              id="review_reason"
              v-model="form.return_reason"
              rows="3"
              placeholder="e.g. The June volume looks like a daily figure entered as monthly — please re-check against the coop receipts."
            />
            <p class="text-xs text-slate-500">
              The center sees this text, so it should say what to fix rather than that something is
              wrong.
            </p>
            <p v-if="form.errors.return_reason" class="text-sm text-red-500">
              {{ form.errors.return_reason }}
            </p>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" class="cursor-pointer" @click="close">
              Cancel
            </Button>
            <Button
              type="submit"
              class="w-full cursor-pointer bg-black text-white hover:bg-black/90 sm:w-auto"
              :disabled="form.processing"
            >
              {{
                form.processing
                  ? 'Saving...'
                  : mode === 'approve'
                    ? 'Approve figures'
                    : 'Return to center'
              }}
            </Button>
          </DialogFooter>
        </form>
      </div>
    </DialogContent>
  </Dialog>
</template>
