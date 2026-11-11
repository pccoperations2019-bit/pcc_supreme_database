<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import FormField from '@/components/form/FormField.vue'
import FieldInput from '@/components/form/FieldInput.vue'

const form = defineModel('form', { type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  regions: { type: Array, default: () => [] },
  provinces: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  barangays: { type: Array, default: () => [] },
  districts: { type: Array, default: () => [] },
  provinceDisabled: { type: Boolean, default: false },
  cityDisabled: { type: Boolean, default: false },
  districtDisabled: { type: Boolean, default: false },
})

defineEmits(['clear-error'])
</script>

<template>
  <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3">
    <div class="space-y-2">
      <FieldInput
        id="street"
        v-model="form.street"
        label="Street"
        placeholder="Street"
        :error="errors.street"
        @input="$emit('clear-error', 'street')"
      />
    </div>

    <div class="space-y-2">
      <FormField id="region" label="Region" :error="errors.region">
        <Select v-model="form.region" @update:model-value="$emit('clear-error', 'region')">
          <SelectTrigger class="h-8 w-full">
            <SelectValue placeholder="Select region" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="r in regions" :key="r.code" :value="r.code">
              {{ r.name }}
            </SelectItem>
          </SelectContent>
        </Select>
      </FormField>
    </div>

    <div class="space-y-2">
      <FormField id="province" label="Province" :error="errors.province">
        <Select
          v-model="form.province"
          :disabled="provinceDisabled"
          @update:model-value="$emit('clear-error', 'province')"
        >
          <SelectTrigger class="h-8 w-full">
            <SelectValue placeholder="Select province" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="p in provinces" :key="p.code" :value="p.code">
              {{ p.name }}
            </SelectItem>
          </SelectContent>
        </Select>
      </FormField>
    </div>

    <div class="space-y-2">
      <FormField id="municipality" label="Municipality" :error="errors.municipality">
        <Select
          v-model="form.municipality"
          :disabled="cityDisabled"
          @update:model-value="$emit('clear-error', 'municipality')"
        >
          <SelectTrigger class="h-8 w-full">
            <SelectValue placeholder="Select municipality" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="c in cities" :key="c.code" :value="c.code">
              {{ c.name }}
            </SelectItem>
          </SelectContent>
        </Select>
      </FormField>
    </div>

    <div class="space-y-2">
      <FormField id="barangay" label="Barangay" :error="errors.barangay">
        <Select
          v-model="form.barangay"
          :disabled="!form.municipality"
          @update:model-value="$emit('clear-error', 'barangay')"
        >
          <SelectTrigger class="h-8 w-full">
            <SelectValue placeholder="Select barangay" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="b in barangays" :key="b.code" :value="b.code">
              {{ b.name }}
            </SelectItem>
          </SelectContent>
        </Select>
      </FormField>
    </div>

    <div class="space-y-2">
      <FormField id="district" label="District" :error="errors.district">
        <Select
          v-model="form.district"
          :disabled="districtDisabled"
          @update:model-value="$emit('clear-error', 'district')"
        >
          <SelectTrigger class="h-8 w-full">
            <SelectValue placeholder="Select district" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="d in districts" :key="d.city_code" :value="d.name">
              {{ d.name }}
            </SelectItem>
          </SelectContent>
        </Select>
      </FormField>
    </div>
  </div>
</template>
