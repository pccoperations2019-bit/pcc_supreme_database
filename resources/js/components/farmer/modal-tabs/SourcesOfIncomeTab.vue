<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

const form = defineModel('form', { type: Object, required: true })

const formatCurrency = (amount, currency = 'PHP') => {
  if (!amount) return 'N/A'
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(amount)
}

const sourceOfIncomeTotal = row =>
  (Number(row?.net_income) || 0) * (Number(row?.frequency_per_year) || 0)

const sourceOfIncomeGrandTotal = computed(() =>
  form.value.source_of_income_details.reduce((sum, row) => sum + sourceOfIncomeTotal(row), 0)
)

const addSourceOfIncomeRow = () => {
  form.value.source_of_income_details.push({
    household_member: '',
    source_of_income: '',
    gross_income: 0,
    net_income: 0,
    frequency_per_year: 0,
  })
}
</script>

<template>
  <div class="col-span-1 space-y-6 md:col-span-2">
    <div class="space-y-4 rounded-lg border p-4">
      <div class="flex items-center justify-between">
        <h4 class="text-foreground text-base font-semibold">
          Sources of Income (per household member)
        </h4>
        <Button type="button" variant="outline" size="sm" @click="addSourceOfIncomeRow">
          <Icon icon="lucide:plus" class="mr-1 h-4 w-4" />
          Add
        </Button>
      </div>
      <div class="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Household Member</TableHead>
              <TableHead>Source of Income</TableHead>
              <TableHead>Gross Income/Month or per Cropping</TableHead>
              <TableHead>Net Income/Month or per Cropping</TableHead>
              <TableHead>Frequency/Year</TableHead>
              <TableHead class="text-right">Total</TableHead>
              <TableHead class="w-10"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="(row, index) in form.source_of_income_details" :key="index">
              <TableCell>
                <Input v-model="form.source_of_income_details[index].household_member" />
              </TableCell>
              <TableCell>
                <Input v-model="form.source_of_income_details[index].source_of_income" />
              </TableCell>
              <TableCell>
                <Input
                  v-model.number="form.source_of_income_details[index].gross_income"
                  type="number"
                  step="0.01"
                  min="0"
                />
              </TableCell>
              <TableCell>
                <Input
                  v-model.number="form.source_of_income_details[index].net_income"
                  type="number"
                  step="0.01"
                  min="0"
                />
              </TableCell>
              <TableCell>
                <Input
                  v-model.number="form.source_of_income_details[index].frequency_per_year"
                  type="number"
                  min="0"
                />
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatCurrency(sourceOfIncomeTotal(row)) }}
              </TableCell>
              <TableCell>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="text-red-500"
                  @click="form.source_of_income_details.splice(index, 1)"
                >
                  <Icon icon="lucide:trash-2" class="h-4 w-4" />
                </Button>
              </TableCell>
            </TableRow>
            <TableRow v-if="!form.source_of_income_details.length">
              <TableCell colspan="7">
                <div class="text-muted-foreground py-4 text-center text-sm">
                  No sources of income added yet.
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
          <TableFooter v-if="form.source_of_income_details.length">
            <TableRow>
              <TableCell class="font-medium">Total</TableCell>
              <TableCell></TableCell>
              <TableCell></TableCell>
              <TableCell></TableCell>
              <TableCell></TableCell>
              <TableCell class="text-right font-medium tabular-nums">
                {{ formatCurrency(sourceOfIncomeGrandTotal) }}
              </TableCell>
              <TableCell></TableCell>
            </TableRow>
          </TableFooter>
        </Table>
      </div>
    </div>
  </div>
</template>
