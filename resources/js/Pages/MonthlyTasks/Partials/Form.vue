<script setup>
import { displayCenterLabel } from "@/lib/center";
import { computed, ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { Icon } from "@iconify/vue";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from "@/components/ui/select";
import { useAuth } from "@/composable/useAuth";
import { toastFlash } from "@/lib/flashToasts";
import {
  monthlyTaskProductIcon,
  monthlyTaskProductLabel,
  monthlyTaskProgramLabel,
  monthlyTaskTypeLabel,
} from "@/enums/MonthlyTaskEnum";
const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (value) => ["create", "edit", "view"].includes(value),
  },
  monthlyTask: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  enums: { type: Object, default: () => ({}) },
});

const route = globalThis.route;

const { hasPermission } = useAuth();

const isCreateMode = computed(() => props.mode === "create");
const isEditMode = computed(() => props.mode === "edit");
const isViewMode = computed(() => props.mode === "view");

const canUpdate = computed(() => hasPermission("update-monthly-tasks"));

const form = useForm({
  center_id: props.monthlyTask.center_id || null,
  program: props.monthlyTask.program || "ih",
  product_type: props.monthlyTask.product_type || "",
  task_type: props.monthlyTask.task_type || "production",
  year: props.monthlyTask.year || new Date().getFullYear(),
  month: props.monthlyTask.month || new Date().getMonth() + 1,
});

const title = computed(() => {
  if (isViewMode.value) return "Monthly Task";
  if (isEditMode.value) return "Edit Monthly Task";

  return "Create Monthly Task";
});

const canReview = computed(() => hasPermission("review-monthly-tasks"));

const reviewForm = useForm({
  note: "",
  return_reason: "",
});

const reviewing = ref(false);

const startReview = (mode) => {
  reviewing.value = true;
  reviewForm.note = "";
  reviewForm.return_reason = "";
  reviewForm.clearErrors();
};

const toggleReview = () => {
  reviewing.value = !reviewing.value;
  reviewForm.note = "";
  reviewForm.return_reason = "";
  reviewForm.clearErrors();
};

const approve = () => {
  reviewForm.post(route("monthly-tasks.approve", props.monthlyTask.id), {
    preserveScroll: true,
    onSuccess: () => {
      reviewing.value = false;
      //toastFlash({ success: "Figures approved." });
    },
    onError: (errors) => {
      const first = Object.values(errors || {})[0];
      const message = Array.isArray(first) ? first[0] : first;
      toastFlash({ error: message || "Could not approve the figures." });
    },
  });
};

const returnFigures = () => {
  reviewForm.post(route("monthly-tasks.return", props.monthlyTask.id), {
    preserveScroll: true,
    onError: (errors) => {
      const first = Object.values(errors || {})[0];
      const message = Array.isArray(first) ? first[0] : first;
      toastFlash({ error: message || "Could not return the figures." });
    },
  });
};

const monthOptions = computed(() =>
  Object.entries(props.enums?.months || {}).map(([value, label]) => ({
    value: String(value),
    label,
  }))
);

const selectedCenter = computed(
  () =>
    props.centers.find((center) => String(center.id) === String(form.center_id)) || null
);

const dueDate = computed(() => {
  const year = Number(form.year);
  const month = Number(form.month);

  if (!year || !month) return null;

  const due = new Date(Date.UTC(year, month, 0));
  due.setUTCDate(due.getUTCDate() + 7);

  return due.toISOString().slice(0, 10);
});

const onSubmit = () => {
  if (isViewMode.value) return;

  form.submit(
    isCreateMode.value ? "post" : "put",
    isCreateMode.value
      ? route("monthly-tasks.store")
      : route("monthly-tasks.update", props.monthlyTask.id),
    {
      preserveScroll: true,
      onError: (errors) => {
        const first = Object.values(errors || {})[0];
        const message = Array.isArray(first) ? first[0] : first;
        toastFlash({ error: message || "Validation failed. Please check the form." });
      },
    }
  );
};
</script>

<template>
  <div class="flex flex-col gap-4">
    <Card>
      <CardHeader>
        <div class="flex items-start justify-between gap-3">
          <div>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription>
              {{
                isViewMode
                  ? "A single reporting obligation for one center, product and month"
                  : "One product, one half of its reporting, one center, one month"
              }}
            </CardDescription>
          </div>
        </div>
      </CardHeader>

      <CardContent>
        <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
          <dl
            v-if="isViewMode || isEditMode"
            class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 sm:grid-cols-3"
          >
            <div>
              <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">
                Due
              </dt>
              <dd class="mt-1 text-sm text-slate-900">{{ monthlyTask.due_date }}</dd>
            </div>
            <div>
              <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">
                Total
              </dt>
              <dd class="mt-1 text-sm font-medium text-slate-900">
                {{ monthlyTask.total_formatted || "—" }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">
                Completed by
              </dt>
              <dd class="mt-1 text-sm text-slate-900">
                {{ monthlyTask.encoder_name || "—" }}
              </dd>
              <dd class="text-xs text-slate-500">{{ monthlyTask.encoder_role || "" }}</dd>
            </div>
          </dl>

          <p
            v-if="monthlyTask.is_no_data_report"
            class="flex items-start gap-2 rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700"
            role="status"
          >
            <Icon
              icon="lucide:circle-minus"
              class="mt-0.5 h-4 w-4 shrink-0"
              aria-hidden="true"
            />
            <span>
              Reported as having nothing to report for this period
              <span v-if="monthlyTask.encoder_name !== '—'">
                by {{ monthlyTask.encoder_name }}
              </span>
              . No figures were found in the production or sales data.
            </span>
          </p>

          <Card>
            <CardHeader>
              <CardTitle class="text-base">What is being reported</CardTitle>
              <CardDescription>
                {{
                  isCreateMode
                    ? "Pick the center, program, product and month this task covers"
                    : "The center, program, product and period a task belongs to cannot be changed"
                }}
              </CardDescription>
            </CardHeader>

            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="monthly_task_center"
                  >Center <span class="text-red-500">*</span></Label
                >
                <Select v-if="isCreateMode" v-model="form.center_id">
                  <SelectTrigger id="monthly_task_center" class="h-9 w-full">
                    <SelectValue placeholder="Select center" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="center in centers"
                      :key="center.id"
                      :value="String(center.id)"
                    >
                      {{ displayCenterLabel(center.code) }}
                    </SelectItem>
                  </SelectContent>
                </Select>

                <div
                  v-else
                  id="monthly_task_center"
                  class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm"
                >
                  {{
                    monthlyTask.center ? displayCenterLabel(monthlyTask.center.code) : "—"
                  }}
                </div>
                <p v-if="form.errors.center_id" class="text-sm text-red-500">
                  {{ form.errors.center_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="monthly_task_product"
                  >Product <span class="text-red-500">*</span></Label
                >
                <Select v-if="isCreateMode" v-model="form.product_type">
                  <SelectTrigger id="monthly_task_product" class="h-9 w-full">
                    <SelectValue placeholder="Select product" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="product in enums?.products || []"
                      :key="product.value"
                      :value="product.value"
                    >
                      {{ product.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div
                  v-else
                  id="monthly_task_product"
                  class="bg-muted flex h-9 w-full items-center gap-2 rounded-md border px-3 py-2 text-sm"
                >
                  <Icon
                    :icon="
                      monthlyTask.product_type
                        ? monthlyTaskProductIcon(monthlyTask.product_type)
                        : 'lucide:package'
                    "
                    class="h-4 w-4 text-slate-500"
                    aria-hidden="true"
                  />
                  {{
                    monthlyTask.product_type
                      ? monthlyTaskProductLabel(monthlyTask.product_type)
                      : "—"
                  }}
                </div>
                <p v-if="form.errors.product_type" class="text-sm text-red-500">
                  {{ form.errors.product_type }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="monthly_task_program"
                  >Program <span class="text-red-500">*</span></Label
                >
                <Select v-if="isCreateMode" v-model="form.program">
                  <SelectTrigger id="monthly_task_program" class="h-9 w-full">
                    <SelectValue placeholder="Select program" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="program in enums?.programs || []"
                      :key="program.value"
                      :value="program.value"
                    >
                      {{ program.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div
                  v-else
                  id="monthly_task_program"
                  class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm"
                >
                  {{ monthlyTaskProgramLabel(monthlyTask.program) }}
                </div>
                <p v-if="form.errors.program" class="text-sm text-red-500">
                  {{ form.errors.program }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="monthly_task_type"
                  >Task type <span class="text-red-500">*</span></Label
                >
                <Select v-if="isCreateMode" v-model="form.task_type">
                  <SelectTrigger id="monthly_task_type" class="h-9 w-full">
                    <SelectValue placeholder="Select task type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="taskType in enums?.taskTypes || []"
                      :key="taskType.value"
                      :value="taskType.value"
                    >
                      {{ taskType.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div
                  v-else
                  id="monthly_task_type"
                  class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm"
                >
                  {{
                    monthlyTask.task_type
                      ? monthlyTaskTypeLabel(monthlyTask.task_type)
                      : "—"
                  }}
                </div>
                <p v-if="form.errors.task_type" class="text-sm text-red-500">
                  {{ form.errors.task_type }}
                </p>
              </div>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                  <Label for="monthly_task_year"
                    >Year <span class="text-red-500">*</span></Label
                  >
                  <Select v-if="isCreateMode" v-model="form.year">
                    <SelectTrigger id="monthly_task_year" class="h-9 w-full">
                      <SelectValue placeholder="Select year" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="year in enums?.availableYears || []"
                        :key="year"
                        :value="String(year)"
                      >
                        {{ year }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <div
                    v-else
                    class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm"
                  >
                    {{ monthlyTask.year }}
                  </div>
                  <p v-if="form.errors.year" class="text-sm text-red-500">
                    {{ form.errors.year }}
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="monthly_task_month"
                    >Month <span class="text-red-500">*</span></Label
                  >
                  <Select v-if="isCreateMode" v-model="form.month">
                    <SelectTrigger id="monthly_task_month" class="h-9 w-full">
                      <SelectValue placeholder="Select month" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="month in monthOptions"
                        :key="month.value"
                        :value="month.value"
                      >
                        {{ month.label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <div
                    v-else
                    class="bg-muted h-9 w-full rounded-md border px-3 py-2 text-sm"
                  >
                    {{ enums?.months?.[String(monthlyTask.month)] || monthlyTask.month }}
                  </div>
                  <p v-if="form.errors.month" class="text-sm text-red-500">
                    {{ form.errors.month }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card
            v-if="
              monthlyTask.production_data_rows &&
              monthlyTask.production_data_rows.length > 0
            "
          >
            <CardHeader>
              <CardTitle class="text-base">
                Data Entries ({{ monthlyTask.production_data_rows.length }})
              </CardTitle>
              <CardDescription>What the center entered for this period.</CardDescription>
            </CardHeader>

            <CardContent>
              <div
                class="max-h-60 space-y-2 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50"
              >
                <div
                  v-for="(row, index) in monthlyTask.production_data_rows"
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
                    <div v-else class="text-sm font-medium text-slate-900">
                      Entry #{{ index + 1 }}
                    </div>
                    <div class="text-xs text-slate-500">
                      {{ monthlyTask.task_type === "sales" ? "Sales" : "Volume" }}:
                      {{ Number(row.volume ?? 0).toLocaleString() }}
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-xs text-slate-500">Entered by</div>
                    <div class="text-sm text-slate-900">
                      {{ row.encoder_name || monthlyTask.encoder_name || "—" }}
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card
            v-if="
              (canReview && monthlyTask.has_entered_data) ||
              monthlyTask.review_status === 'returned' ||
              monthlyTask.review_status === 'approved'
            "
          >
            <CardHeader>
              <CardTitle class="text-base">Review</CardTitle>
              <CardDescription>
                {{
                  monthlyTask.review_status === "returned"
                    ? "These figures were returned. Correct them on the entry page and save — that resubmits for review."
                    : monthlyTask.review_status === "approved"
                    ? `Approved${
                        monthlyTask.reviewed_by_name
                          ? ` by ${monthlyTask.reviewed_by_name}`
                          : ""
                      }. This period counts as complete.`
                    : "Approve these figures, or send them back with what needs correcting."
                }}
              </CardDescription>
            </CardHeader>

            <CardContent class="flex flex-col gap-4">
              <p
                v-if="monthlyTask.review_status === 'returned'"
                class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                role="status"
              >
                <Icon
                  icon="lucide:undo-2"
                  class="mt-0.5 h-4 w-4 shrink-0"
                  aria-hidden="true"
                />
                <span>
                  <span class="font-medium">Returned for correction.</span>
                  {{ monthlyTask.return_reason || "No reason was recorded." }}
                </span>
              </p>

              <p
                v-if="monthlyTask.review_status === 'approved' && monthlyTask.review_note"
                class="flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                role="status"
              >
                <Icon
                  icon="lucide:message-square"
                  class="mt-0.5 h-4 w-4 shrink-0"
                  aria-hidden="true"
                />
                <span>
                  <span class="font-medium">Review note.</span>
                  {{ monthlyTask.review_note }}
                </span>
              </p>

              <template v-if="canReview && monthlyTask.is_awaiting_review">
                <div v-if="!reviewing" class="flex flex-wrap gap-2">
                  <Button
                    type="button"
                    class="w-full cursor-pointer bg-black text-white hover:bg-black/90 sm:w-auto"
                    @click="startReview('approve')"
                  >
                    <Icon icon="lucide:check" class="mr-2 h-4 w-4" />
                    Approve figures
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    class="w-full cursor-pointer sm:w-auto"
                    @click="startReview('return')"
                  >
                    <Icon icon="lucide:undo-2" class="mr-2 h-4 w-4" />
                    Return for correction
                  </Button>
                </div>

                <form v-else class="flex flex-col gap-4" @submit.prevent="approve">
                  <div class="space-y-2">
                    <Label for="monthly_task_review_note">
                      Note (optional, recorded with the approval)
                    </Label>
                    <Textarea
                      id="monthly_task_review_note"
                      v-model="reviewForm.note"
                      rows="2"
                      placeholder="e.g. Figures tie out against the center's delivery receipts."
                    />
                  </div>

                  <div class="space-y-2">
                    <Label for="monthly_task_return_reason">
                      What needs correcting <span class="text-red-500">*</span>
                    </Label>
                    <Textarea
                      id="monthly_task_return_reason"
                      v-model="reviewForm.return_reason"
                      rows="3"
                      placeholder="e.g. June volume looks like a daily figure entered as monthly — please re-check against the coop receipts."
                    />
                    <p class="text-xs text-slate-500">
                      Required only when returning the figures. The center sees this text,
                      so it should say what to fix.
                    </p>
                    <p
                      v-if="reviewForm.errors.return_reason"
                      class="text-sm text-red-500"
                    >
                      {{ reviewForm.errors.return_reason }}
                    </p>
                  </div>

                  <div class="flex flex-wrap justify-end gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      class="cursor-pointer"
                      @click="toggleReview"
                    >
                      Cancel
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      class="w-full cursor-pointer text-red-600 hover:text-red-700 sm:w-auto"
                      :disabled="reviewForm.processing"
                      @click="returnFigures"
                    >
                      Return for correction
                    </Button>
                    <Button
                      type="submit"
                      class="w-full cursor-pointer bg-black text-white hover:bg-black/90 sm:w-auto"
                      :disabled="reviewForm.processing"
                    >
                      {{ reviewForm.processing ? "Saving..." : "Approve" }}
                    </Button>
                  </div>
                </form>
              </template>
            </CardContent>
          </Card>

          <p
            v-if="isCreateMode && selectedCenter && form.product_type"
            class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
            role="status"
          >
            <span class="text-slate-500">Will create:</span>
            <Icon
              :icon="monthlyTaskProductIcon(form.product_type)"
              class="h-4 w-4 text-slate-500"
            />
            <span class="font-medium">
              {{ monthlyTaskTypeLabel(form.task_type) }} ·
              {{ monthlyTaskProductLabel(form.product_type) }}
            </span>
            <span>
              for {{ enums?.months?.[String(form.month)] || form.month }}
              {{ form.year }} at {{ displayCenterLabel(selectedCenter.code) }} under
              {{ monthlyTaskProgramLabel(form.program) }} — due {{ dueDate }}.
            </span>
          </p>

          <div v-if="!isViewMode && canUpdate" class="flex justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              @click="router.visit(route('monthly-tasks.index'))"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              class="w-full cursor-pointer bg-black text-white hover:bg-black/90 sm:w-auto"
              :disabled="form.processing"
            >
              {{
                form.processing
                  ? "Saving..."
                  : isEditMode
                  ? "Save Changes"
                  : "Create Task"
              }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
