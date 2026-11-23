<script setup>
import { computed } from 'vue'
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

const totalCount = computed(() =>
  form.value.animal_inventory.reduce((sum, row) => sum + (Number(row.count) || 0), 0)
)
</script>

<template>
  <div class="space-y-4 rounded-lg border p-4">
    <div class="overflow-x-auto rounded-lg border">
      <Table class="min-w-[360px]">
        <TableHeader>
          <TableRow class="bg-muted/50">
            <TableHead class="w-[240px]">Classification</TableHead>
            <TableHead class="w-[120px]">Count</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="(row, index) in form.animal_inventory" :key="index">
            <TableCell class="font-medium">{{ row.classification }}</TableCell>
            <TableCell>
              <Input
                v-model.number="form.animal_inventory[index].count"
                type="number"
                min="0"
                class="w-24"
              />
            </TableCell>
          </TableRow>
        </TableBody>
        <TableFooter>
          <TableRow class="bg-muted/50">
            <TableCell class="font-semibold">TOTAL</TableCell>
            <TableCell class="font-semibold">{{ totalCount }}</TableCell>
          </TableRow>
        </TableFooter>
      </Table>
    </div>
  </div>
</template>
