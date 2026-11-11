<script setup>
/* global route, confirm */
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Icon } from '@iconify/vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { useAuth } from '@/composable/useAuth'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuLabel,
} from '@/components/ui/dropdown-menu'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

const props = defineProps({
  sources: { type: Object, required: true },
  availableYears: { type: Array, default: () => [] },
  sourceLabels: { type: Object, default: () => ({}) },
  sourceNames: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
})
const { hasPermission } = useAuth()
const selectedYear = computed(() => (props.filters.year ? String(props.filters.year) : null))
const openFilterSection = ref(null)
const filterYear = year =>
  router.get(route('beneficiary-sources.index'), year ? { 'filter[year]': year } : {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
const paginate = url => {
  if (url) router.get(url, {}, { preserveState: true, preserveScroll: true })
}
const deleteSource = source => {
  if (confirm(`Are you sure you want to delete ${source.name} for ${source.year}?`)) {
    router.delete(route('beneficiary-sources.destroy', source.id), { preserveScroll: true })
  }
}
</script>

<template>
  <Head title="Beneficiary Sources" />
  <DashboardLayout>
    <div class="w-full space-y-6 rounded-lg bg-white px-6 py-12 text-black shadow-sm">
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Beneficiary Sources</h1>
          <p class="text-sm text-slate-600 sm:text-base">
            Manage beneficiary counts for each funding source and year.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-4">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="outline" class="w-full cursor-pointer sm:w-auto">
                <Icon icon="lucide:filter" class="mr-2 h-4 w-4" />
                <span class="flex-1 text-left">Filters</span>
                <span
                  v-if="selectedYear"
                  class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800"
                  >1</span
                >
                <Icon icon="lucide:chevron-down" class="ml-2 h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
              align="end"
              class="max-h-[50vh] w-72 overflow-y-auto bg-white sm:max-h-[70vh] sm:w-80"
            >
              <DropdownMenuLabel>Filters</DropdownMenuLabel>
              <DropdownMenuSeparator class="border-b border-slate-100" />
              <DropdownMenuItem
                class="cursor-pointer"
                @select.prevent
                @click="openFilterSection = openFilterSection === 'year' ? null : 'year'"
              >
                <div class="flex w-full items-center justify-between">
                  <span>Year</span>
                  <Icon
                    :icon="
                      openFilterSection === 'year' ? 'lucide:chevron-up' : 'lucide:chevron-down'
                    "
                    class="h-4 w-4 text-slate-500"
                  />
                </div>
              </DropdownMenuItem>
              <div v-if="openFilterSection === 'year'" class="max-h-56 overflow-y-auto">
                <DropdownMenuItem
                  v-for="year in availableYears"
                  :key="year"
                  class="cursor-pointer pl-8"
                  :class="{ 'bg-indigo-50 text-indigo-700': selectedYear === String(year) }"
                  @select.prevent
                  @click="filterYear(selectedYear === String(year) ? null : String(year))"
                >
                  {{ year }}
                </DropdownMenuItem>
                <p v-if="!availableYears.length" class="px-8 py-2 text-sm text-slate-500">
                  No years available.
                </p>
              </div>
            </DropdownMenuContent>
          </DropdownMenu>
          <Link
            v-if="hasPermission('manage-beneficiary-sources')"
            :href="route('beneficiary-sources.create')"
            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
          >
            <Icon icon="lucide:plus" class="mr-2 h-4 w-4" />New Source
          </Link>
        </div>
      </div>
      <div class="w-full overflow-x-auto rounded-lg border border-slate-200">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="bg-slate-50">Funded Type</TableHead>
              <TableHead class="bg-slate-50">Funded Name</TableHead>
              <TableHead class="bg-slate-50">Year</TableHead>
              <TableHead class="bg-slate-50 text-right">Amount</TableHead>
              <TableHead class="bg-slate-50"><span class="sr-only">Actions</span></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="source in sources.data" :key="source.id">
              <TableCell>{{ sourceLabels[source.code] ?? source.code }}</TableCell>
              <TableCell
                ><Link
                  :href="route('beneficiary-sources.show', source.id)"
                  class="font-medium hover:underline"
                  >{{ sourceNames[source.code] ?? source.name }}</Link
                ></TableCell
              >
              <TableCell>{{ source.year }}</TableCell>
              <TableCell class="text-right">{{
                Number(source.amount).toLocaleString('en-PH')
              }}</TableCell>
              <TableCell class="text-right">
                <DropdownMenu>
                  <DropdownMenuTrigger as-child
                    ><Button
                      variant="ghost"
                      size="icon"
                      :aria-label="`Actions for ${source.name} ${source.year}`"
                      ><Icon icon="lucide:more-horizontal" class="h-4 w-4" /></Button
                  ></DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuItem
                      @select="router.visit(route('beneficiary-sources.show', source.id))"
                      >View</DropdownMenuItem
                    >
                    <template v-if="hasPermission('manage-beneficiary-sources')">
                      <DropdownMenuItem
                        @select="router.visit(route('beneficiary-sources.edit', source.id))"
                        >Edit</DropdownMenuItem
                      >
                      <DropdownMenuSeparator />
                      <DropdownMenuItem class="text-red-600" @select="deleteSource(source)"
                        >Delete</DropdownMenuItem
                      >
                    </template>
                  </DropdownMenuContent>
                </DropdownMenu>
              </TableCell>
            </TableRow>
            <TableRow v-if="!sources.data.length"
              ><TableCell :colspan="5" class="h-32 text-center text-slate-500"
                >No beneficiary sources found.</TableCell
              ></TableRow
            >
          </TableBody>
        </Table>
      </div>
      <div class="flex items-center justify-between gap-4">
        <span class="text-sm text-zinc-600"
          >Showing {{ sources.from || 0 }}–{{ sources.to || 0 }} of {{ sources.total || 0 }}</span
        >
        <div class="flex gap-2">
          <Button
            variant="ghost"
            size="sm"
            :disabled="!sources.prev_page_url"
            @click="paginate(sources.prev_page_url)"
            >Prev</Button
          >
          <Button
            variant="ghost"
            size="sm"
            :disabled="!sources.next_page_url"
            @click="paginate(sources.next_page_url)"
            >Next</Button
          >
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
