<script setup>
import { computed } from 'vue'
import { Pencil } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { normalizeAnimalInventory } from '@/composable/useFarmerForm'

const props = defineProps({
  farmer: { type: Object, required: true },
  isEditMode: { type: Boolean, default: false },
  actionLabel: { type: String, default: 'Edit' },
})

const emit = defineEmits(['edit'])

const rows = computed(() => normalizeAnimalInventory(props.farmer.animal_inventory))

const totalCount = computed(() =>
  rows.value.reduce((sum, row) => sum + (Number(row.count) || 0), 0)
)
</script>

<template>
  <div>
    <div class="overflow-x-auto rounded-lg border">
      <Table class="min-w-[360px]">
        <TableHeader>
          <TableRow class="bg-muted/50">
            <TableHead class="w-[240px]">Classification</TableHead>
            <TableHead class="w-[120px]">Count</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="(row, index) in rows" :key="index">
            <TableCell class="font-medium">{{ row.classification }}</TableCell>
            <TableCell>{{ row.count === '' || row.count === null ? '-' : row.count }}</TableCell>
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
    <div v-if="props.isEditMode" class="mt-4">
      <Button size="sm" variant="outline" @click="emit('edit', 'animal-inventory')">
        <Pencil class="mr-2 h-4 w-4" />{{ actionLabel }} Animal Inventory
      </Button>
    </div>
  </div>
</template>
