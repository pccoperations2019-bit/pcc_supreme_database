<script setup>
import { computed, defineProps, defineEmits } from 'vue'
import { Pencil } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

const props = defineProps({
  farmer: { type: Object, required: true },
  isEditMode: { type: Boolean, default: false },
  actionLabel: { type: String, default: 'Edit' },
})

const emit = defineEmits(['edit'])

const formatCurrency = (amount, currency = 'PHP') => {
  if (!amount) return 'N/A'
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(amount)
}

const sourceOfIncomeTotal = row =>
  (Number(row?.net_income) || 0) * (Number(row?.frequency_per_year) || 0)

const sourceOfIncomeGrandTotal = computed(() =>
  (props.farmer.source_of_income_details || []).reduce(
    (sum, row) => sum + sourceOfIncomeTotal(row),
    0
  )
)
</script>

<template>
  <div>
    <Card>
      <CardHeader>
        <CardTitle class="text-base">Sources of Income (per household member)</CardTitle>
      </CardHeader>
      <CardContent>
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
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="(row, index) in farmer.source_of_income_details || []" :key="index">
                <TableCell>{{ row.household_member || 'N/A' }}</TableCell>
                <TableCell>{{ row.source_of_income || 'N/A' }}</TableCell>
                <TableCell>{{ formatCurrency(row.gross_income) }}</TableCell>
                <TableCell>{{ formatCurrency(row.net_income) }}</TableCell>
                <TableCell>{{ row.frequency_per_year ?? 'N/A' }}</TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ formatCurrency(sourceOfIncomeTotal(row)) }}
                </TableCell>
              </TableRow>
              <TableRow v-if="!(farmer.source_of_income_details || []).length">
                <TableCell colspan="6">
                  <div class="text-muted-foreground py-4 text-center text-sm">
                    No sources of income recorded.
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
            <TableFooter v-if="(farmer.source_of_income_details || []).length">
              <TableRow>
                <TableCell class="font-medium">Total</TableCell>
                <TableCell></TableCell>
                <TableCell></TableCell>
                <TableCell></TableCell>
                <TableCell></TableCell>
                <TableCell class="text-right font-medium tabular-nums">
                  {{ formatCurrency(sourceOfIncomeGrandTotal) }}
                </TableCell>
              </TableRow>
            </TableFooter>
          </Table>
        </div>
      </CardContent>
    </Card>
    <div v-if="isEditMode" class="mt-4">
      <Button size="sm" variant="outline" @click="emit('edit', 'income-info')">
        <Pencil class="mr-2 h-4 w-4" />{{ actionLabel }} Income Information
      </Button>
    </div>
  </div>
</template>
