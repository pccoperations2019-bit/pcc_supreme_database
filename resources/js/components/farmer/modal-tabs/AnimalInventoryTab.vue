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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { ANIMAL_INVENTORY_BREEDS } from '@/composable/useFarmerForm'

const form = defineModel('form', { type: Object, required: true })

const totalCount = computed(() =>
  form.value.animal_inventory.reduce((sum, row) => sum + (Number(row.count) || 0), 0)
)
</script>

<template>
  <div class="space-y-4 rounded-lg border p-4">
    <div class="overflow-x-auto rounded-lg border">
      <Table class="min-w-[720px]">
        <TableHeader>
          <TableRow class="bg-muted/50">
            <TableHead class="w-[240px]">Classification</TableHead>
            <TableHead class="w-[120px]">Count</TableHead>
            <TableHead class="w-[140px]">Breed</TableHead>
            <TableHead>Remarks</TableHead>
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
            <TableCell>
              <Select v-model="form.animal_inventory[index].breed">
                <SelectTrigger class="w-32"><SelectValue placeholder="Select" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="breed in ANIMAL_INVENTORY_BREEDS" :key="breed" :value="breed">
                    {{ breed }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </TableCell>
            <TableCell>
              <Input v-model="form.animal_inventory[index].remarks" />
            </TableCell>
          </TableRow>
        </TableBody>
        <TableFooter>
          <TableRow class="bg-muted/50">
            <TableCell class="font-semibold">TOTAL</TableCell>
            <TableCell class="font-semibold">{{ totalCount }}</TableCell>
            <TableCell />
            <TableCell />
          </TableRow>
        </TableFooter>
      </Table>
    </div>
  </div>
</template>
