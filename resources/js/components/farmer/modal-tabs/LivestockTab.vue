<script setup>
import { Icon } from '@iconify/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const form = defineModel('form', { type: Object, required: true })
</script>

<template>
  <div class="col-span-1 space-y-6 md:col-span-2">
    <!-- Cattle Section -->
    <div class="space-y-4 rounded-lg border p-4">
      <h4 class="text-foreground text-base font-semibold">Cattle</h4>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Dairy Cattle</Label>
          <Input v-model.number="form.dairy_cattle" type="number" min="0" />
        </div>
        <div class="space-y-2">
          <Label>Beef Cattle</Label>
          <Input v-model.number="form.beef_cattle" type="number" min="0" />
        </div>
      </div>
    </div>

    <!-- Poultry Section -->
    <div class="space-y-4 rounded-lg border p-4">
      <h4 class="text-foreground text-base font-semibold">Poultry</h4>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Chicken</Label>
          <Input v-model.number="form.chicken" type="number" min="0" />
        </div>
        <div class="space-y-2">
          <Label>Duck</Label>
          <Input v-model.number="form.duck" type="number" min="0" />
        </div>
        <div class="space-y-2">
          <Label>Geese</Label>
          <Input v-model.number="form.geese" type="number" min="0" />
        </div>
      </div>
    </div>

    <!-- Other Livestock Section -->
    <div class="space-y-4 rounded-lg border p-4">
      <h4 class="text-foreground text-base font-semibold">Other Livestock</h4>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Goat</Label>
          <Input v-model.number="form.goat" type="number" min="0" />
        </div>
        <div class="space-y-2">
          <Label>Swine</Label>
          <Input v-model.number="form.swine" type="number" min="0" />
        </div>
        <div class="space-y-2">
          <Label>Horse</Label>
          <Input v-model.number="form.horse" type="number" min="0" />
        </div>
      </div>
    </div>

    <!-- Custom Livestock Section -->
    <div class="space-y-4 rounded-lg border p-4">
      <div class="flex items-center justify-between">
        <h4 class="text-foreground text-base font-semibold">Other Livestock</h4>
        <Button
          type="button"
          variant="outline"
          size="sm"
          @click="form.other_livestock_details.push({ label: '', count: 0 })"
        >
          <Icon icon="lucide:plus" class="mr-1 h-4 w-4" />
          Add
        </Button>
      </div>
      <div
        v-for="(item, index) in form.other_livestock_details"
        :key="index"
        class="grid grid-cols-1 gap-4 md:grid-cols-2"
      >
        <div class="space-y-2">
          <Label>Other Livestock Type</Label>
          <Input v-model="form.other_livestock_details[index].label" />
        </div>
        <div class="flex items-end gap-2">
          <div class="flex-1 space-y-2">
            <Label>Other Livestock Count</Label>
            <Input
              v-model.number="form.other_livestock_details[index].count"
              type="number"
              min="0"
            />
          </div>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="mb-0.5 text-red-500"
            @click="form.other_livestock_details.splice(index, 1)"
          >
            <Icon icon="lucide:trash-2" class="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
