<script setup>
import { displayCenterLabel } from "@/lib/center";
import { Icon } from "@iconify/vue";
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/composable/useAuth";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: "" },
  rows: { type: Object, required: true },
  centers: { type: Array, default: () => [] },
  tableKey: { type: String, required: true },
  columns: { type: Array, required: true },
  destroyRoute: { type: String, required: true },
  routeParam: { type: String, required: true },
});

const emit = defineEmits(["create", "edit"]);

const { hasPermission } = useAuth();
const canManage = computed(() => hasPermission("manage-epahp"));

const centerLabel = (center) => {
  if (!center) return "N/A";
  return center.code ? displayCenterLabel(center.code) : center.name;
};

const formatNumber = (value) => {
  const number = Number(value ?? 0);
  if (!Number.isFinite(number)) return "0";
  return number.toLocaleString(undefined, { maximumFractionDigits: 2 });
};

const formatCurrency = (value) => {
  const number = Number(value ?? 0);
  if (!Number.isFinite(number)) return "0.00";
  return number.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const cellValue = (row, column) => {
  if (column.type === "center") return centerLabel(row.center);
  if (column.type === "year") return row[column.key];
  if (column.type === "currency") return formatCurrency(row[column.key]);
  return formatNumber(row[column.key]);
};

const pagination = computed(() => props.rows || {});

const handlePagination = (url) => {
  if (url) {
    router.get(url, {}, { preserveState: true, preserveScroll: true });
  }
};

const deleteRecord = (record) => {
  if (confirm(`Are you sure you want to delete this ${props.title} record?`)) {
    router.delete(route(props.destroyRoute, { [props.routeParam]: record.id }), {
      preserveScroll: true,
    });
  }
};
</script>

<template>
  <div
    class="space-y-4 rounded-lg bg-white px-4 py-6 text-black shadow-sm sm:space-y-6 sm:px-6 sm:py-12"
  >
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 sm:text-2xl">{{ title }}</h2>
        <p v-if="subtitle" class="text-sm font-medium text-slate-500 sm:text-base">
          {{ subtitle }}
        </p>
        <p v-else class="text-sm text-slate-600 sm:text-base">
          Manage {{ title }} records by center and year.
        </p>
      </div>
      <Button
        v-if="canManage"
        class="w-full cursor-pointer items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden sm:w-auto"
        @click="emit('create')"
      >
        <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />
        <span class="flex-1 text-left">New Record</span>
      </Button>
    </div>

    <div class="w-full overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <div class="min-w-[800px] sm:min-w-full">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead
                v-for="column in columns"
                :key="column.key"
                class="bg-slate-50 font-semibold text-slate-700"
                :class="column.type === 'center' ? 'text-left' : 'text-center'"
              >
                {{ column.label }}
              </TableHead>
              <TableHead
                class="bg-slate-50 text-right font-semibold text-slate-700"
              ></TableHead>
            </TableRow>
          </TableHeader>

          <TableBody>
            <TableRow
              v-for="row in pagination.data || []"
              :key="row.id"
              class="border-none transition-colors hover:bg-slate-50"
            >
              <TableCell
                v-for="column in columns"
                :key="column.key"
                class="text-slate-700"
                :class="
                  column.type === 'center'
                    ? 'text-left font-medium text-slate-900'
                    : 'text-center'
                "
              >
                {{ cellValue(row, column) }}
              </TableCell>
              <TableCell v-if="canManage" class="text-right">
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="ghost" class="h-8 w-8 p-0">
                      <Icon icon="lucide:more-horizontal" class="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" class="w-48 bg-white">
                    <DropdownMenuItem class="cursor-pointer" @click="emit('edit', row)">
                      <Icon icon="lucide:pencil" class="mr-2 h-4 w-4" />
                      Edit
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      class="cursor-pointer text-red-600 focus:text-red-600"
                      @click="deleteRecord(row)"
                    >
                      <Icon icon="lucide:trash-2" class="mr-2 h-4 w-4" />
                      Delete
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </TableCell>
            </TableRow>

            <TableRow
              v-if="!pagination.data || pagination.data.length === 0"
              class="border-none hover:bg-transparent"
            >
              <TableCell
                :colspan="columns.length + 1"
                class="h-32 border-none text-center"
              >
                <div class="flex flex-col items-center justify-center space-y-3">
                  <Icon icon="lucide:database" class="h-12 w-12 text-slate-300" />
                  <div class="text-center">
                    <h3 class="mb-1 text-lg font-medium text-slate-900">
                      No {{ title }} records found
                    </h3>
                  </div>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>

    <div
      v-if="pagination.data && pagination.data.length > 0"
      class="flex items-center justify-between pt-4 text-xs font-semibold uppercase"
    >
      <div class="flex flex-wrap items-center gap-2 text-zinc-600">
        <span>
          Showing {{ pagination.from || 0 }}–{{ pagination.to || 0 }} of
          {{ pagination.total || 0 }}
        </span>
      </div>
      <div class="flex items-center space-x-2">
        <Button
          variant="ghost"
          size="xs"
          :disabled="!pagination.prev_page_url"
          @click="handlePagination(pagination.prev_page_url)"
        >
          <Icon icon="lucide:chevron-left" class="mr-1 h-3 w-4" />
          Prev
        </Button>
        <Button
          variant="ghost"
          size="xs"
          :disabled="!pagination.next_page_url"
          @click="handlePagination(pagination.next_page_url)"
        >
          Next
          <Icon icon="lucide:chevron-right" class="ml-1 h-3 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
