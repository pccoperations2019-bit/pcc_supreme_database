<script setup>
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { ANIMAL_OWNERSHIP_TYPES } from '@/composable/useFarmerForm'

const form = defineModel('form', { type: Object, required: true })

defineProps({
  enums: { type: Object, default: () => ({}) },
  farmBarangays: { type: Array, default: () => [] },
  farmCities: { type: Array, default: () => [] },
  farmProvinces: { type: Array, default: () => [] },
  regionsData: { type: Array, default: () => [] },
  farmRegionHasProvinces: { type: Boolean, default: false },
  onSetGroupDetail: { type: Function, default: () => {} },
  onToggleGroupValue: { type: Function, default: () => {} },
  onHasGroupValue: { type: Function, default: () => {} },
})

const hasBusinessRegistration = key =>
  (form.value.type_of_farm_business_registration || []).includes(key)

const toggleBusinessRegistration = (key, checked) => {
  const current = form.value.type_of_farm_business_registration || []
  form.value.type_of_farm_business_registration = checked
    ? [...new Set([...current, key])]
    : current.filter(v => v !== key)
}

const forageOptions = {
  foragesGrown: ['Napier', 'Sorghum', 'Guinea grass', 'Paragrass', 'Mombasa', 'Legumes', 'Mulato'],
  ownership: ['Registered Owner', 'Land Rights only', 'Tenant', 'Communal', 'Lessee'],
  farmType: ['Irrigated', 'Rain fed Upland', 'Rain fed Lowland'],
  waterSource: [
    'Deep/Shallow well',
    'Irrigation',
    'Rainfall',
    'Water district',
    'Water Impounding Pond',
    'Natural water body',
  ],
}

const cropOptions = {
  cropsGrown: [
    'Palay',
    'Corn',
    'Fruits',
    'Onion',
    'Lowland Vegetables',
    'Sugar Cane',
    'Upland Vegetables',
  ],
  ownership: ['Registered Owner', 'Lessee', 'Land Rights only', 'Tenant'],
  farmType: ['Irrigated', 'Rain fed Upland', 'Rain fed Lowland'],
  waterSource: [
    'Deep/Shallow well',
    'Irrigation',
    'Rainfall',
    'Water district',
    'Water Impounding Pond',
    'Natural water body',
  ],
}

const shedOptions = {
  roof: ['Nipa', 'Rubber', 'Galvanized iron', 'Cement', 'Other'],
  posts: ['Bamboo', 'Wood', 'Steel', 'Cement', 'Other'],
  flooring: ['Cement', 'Gravel', 'Soil', 'Other'],
  ownership: ['Registered Owner', 'Land Rights only', 'Tenant', 'Lessee'],
  waterSource: [
    'Deep/Shallow well',
    'Irrigation',
    'Rainfall',
    'Water district',
    'Water Impounding Pond',
    'Natural water body',
  ],
}

const SELLING_LOCATION_OPTIONS = [
  { key: 'neighborhood', label: 'Within the neighborhood' },
  { key: 'we_process', label: 'We process it' },
  { key: 'cooperative', label: 'Cooperative' },
  { key: 'indian_nationality', label: 'Indian Nationality' },
  { key: 'restaurant', label: 'Known restaurant/establishment' },
  { key: 'private_processor', label: 'Private processor' },
  { key: 'independent_collector', label: 'Independent Milk Collector' },
  { key: 'others', label: 'Others' },
]

const hasSellingLocation = key => (form.value.milk_selling_locations || []).includes(key)

const toggleSellingLocation = (key, checked) => {
  const current = form.value.milk_selling_locations || []
  form.value.milk_selling_locations = checked
    ? [...new Set([...current, key])]
    : current.filter(v => v !== key)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Farm Details -->
    <div class="space-y-4 rounded-lg border p-4">
      <h4 class="text-foreground text-base font-semibold">Farm Details</h4>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Farm Name</Label>
          <Input v-model="form.farm_name" />
        </div>
        <div v-if="!form.farm_name?.trim()" class="space-y-2">
          <Label>Proposed Farm Name</Label>
          <Input v-model="form.proposed_farm_name" placeholder="If farm name is none" />
        </div>
      </div>

      <div class="col-span-1 space-y-2 md:col-span-2">
        <Label class="text-muted-foreground">Farm Address</Label>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label>Farm Region</Label>
            <Select v-model="form.farm_region">
              <SelectTrigger><SelectValue placeholder="Select region" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="region in regionsData" :key="region.code" :value="region.code">
                  {{ region.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label>Farm Province</Label>
            <Select v-model="form.farm_province" :disabled="!form.farm_region">
              <SelectTrigger><SelectValue placeholder="Select province" /></SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="province in farmProvinces"
                  :key="province.code"
                  :value="province.code"
                >
                  {{ province.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label>Farm Municipality/City</Label>
            <Select
              v-model="form.farm_municipality_city"
              :disabled="!form.farm_province && farmRegionHasProvinces"
            >
              <SelectTrigger><SelectValue placeholder="Select municipality/city" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="city in farmCities" :key="city.code" :value="city.code">
                  {{ city.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label>Farm Barangay</Label>
            <Select v-model="form.farm_barangay" :disabled="!form.farm_municipality_city">
              <SelectTrigger><SelectValue placeholder="Select barangay" /></SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="barangay in farmBarangays"
                  :key="barangay.code"
                  :value="barangay.code"
                >
                  {{ barangay.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label>Farm District</Label>
            <Select v-model="form.farm_district">
              <SelectTrigger><SelectValue placeholder="Select district" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="(label, key) in enums.addressDistrict" :key="key" :value="key">
                  {{ label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label>Zone/Street/Sitio/Subdivision</Label>
            <Input v-model="form.farm_street" />
          </div>
          <div class="space-y-2">
            <Label>Farm Purok/Zone</Label>
            <Input v-model="form.farm_purok_zone" />
          </div>
        </div>
      </div>

      <div class="col-span-1 space-y-2 md:col-span-2">
        <Label>Type of Farm Business Registration</Label>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
          <label
            v-for="(label, key) in enums.farmBusinessRegistration"
            :key="key"
            class="flex items-center space-x-2 text-sm"
          >
            <Checkbox
              :id="`farm-biz-reg-${key}`"
              :model-value="hasBusinessRegistration(key)"
              @update:model-value="checked => toggleBusinessRegistration(key, checked)"
            />
            <span>{{ label }}</span>
          </label>
        </div>
      </div>
      <div class="space-y-2">
        <Label>Status of Registration</Label>
        <Select v-model="form.status_of_registration">
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="(label, key) in enums.farmRegistrationStatus"
              :key="key"
              :value="key"
            >
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="space-y-2">
        <Label>Registration/Serial/Certification No.</Label>
        <Input v-model="form.farm_registration_number" />
      </div>
      <div class="space-y-2">
        <Label>RSBSA Registered</Label>
        <Select v-model="form.has_rsbsa">
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem :value="true">Yes</SelectItem>
            <SelectItem :value="false">No</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div v-if="form.has_rsbsa" class="space-y-2">
        <Label>RSBSA Registration No.</Label>
        <Input v-model="form.rsbsa_certificate_of_registration" />
      </div>
      <div class="space-y-2">
        <Label>Year Dairy Carabao Farm Was Established</Label>
        <Input v-model="form.year_farm_established" type="number" placeholder="YYYY" />
      </div>
    </div>

    <!-- Farm Operations -->
    <div class="space-y-4 rounded-lg border p-4">
      <h4 class="text-foreground text-base font-semibold">Farm Operations</h4>
      <div class="space-y-2">
        <Label>Management System</Label>
        <Select v-model="form.management_system">
          <SelectTrigger><SelectValue placeholder="Select management system" /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="(label, key) in enums.managementSystem" :key="key" :value="key">
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="space-y-2">
        <Label>Feeding Management</Label>
        <Select v-model="form.feeding_management">
          <SelectTrigger><SelectValue placeholder="Select feeding management" /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="(label, key) in enums.feedingManagement" :key="key" :value="key">
              {{ label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="space-y-3">
        <Label class="text-foreground font-semibold"
          >Number of Animals According to Ownership</Label
        >
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <div
            v-for="type in ANIMAL_OWNERSHIP_TYPES"
            :key="type.key"
            class="flex flex-wrap items-center gap-3 rounded-lg border p-3"
            :class="type.hasSpecify && 'md:col-span-2'"
          >
            <label class="flex cursor-pointer items-center gap-2">
              <input
                v-model="form.animal_ownership[type.key].checked"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-400"
              />
              <span class="text-sm font-medium">{{ type.label }}</span>
            </label>
            <div class="flex items-center gap-2">
              <Label class="text-muted-foreground text-xs">Count</Label>
              <Input
                v-model.number="form.animal_ownership[type.key].count"
                type="number"
                min="0"
                class="w-24"
                :disabled="!form.animal_ownership[type.key].checked"
              />
            </div>
            <div v-if="type.hasSpecify" class="flex flex-1 items-center gap-2">
              <Label class="text-muted-foreground text-xs">Specify</Label>
              <Input
                v-model="form.animal_ownership[type.key].specify"
                class="flex-1"
                :disabled="!form.animal_ownership[type.key].checked"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Forage Information -->
    <div class="space-y-4 rounded-lg border p-4">
      <h4 class="text-foreground text-base font-semibold">Forage Information</h4>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Total Forage Area (ha)</Label>
          <Input
            :model-value="form.additional_details?.forage?.total_area"
            type="number"
            @update:model-value="val => onSetGroupDetail('forage', 'total_area', val)"
          />
        </div>
        <div class="space-y-2">
          <Label>Distance from House</Label>
          <Input
            :model-value="form.additional_details?.forage?.distance_from_house"
            @update:model-value="val => onSetGroupDetail('forage', 'distance_from_house', val)"
          />
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Forages Grown</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in forageOptions.foragesGrown"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`forage-grown-${opt}`"
                :model-value="onHasGroupValue('forage', 'forages_grown', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('forage', 'forages_grown', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Others (Forages Grown)</Label>
          <Input
            :model-value="form.additional_details?.forage?.forages_grown_others"
            placeholder="Specify other forages"
            @update:model-value="val => onSetGroupDetail('forage', 'forages_grown_others', val)"
          />
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Type of Ownership</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in forageOptions.ownership"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`forage-ownership-${opt}`"
                :model-value="onHasGroupValue('forage', 'ownership', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('forage', 'ownership', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Farm Type</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in forageOptions.farmType"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`forage-farmtype-${opt}`"
                :model-value="onHasGroupValue('forage', 'farm_type', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('forage', 'farm_type', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Water Source</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in forageOptions.waterSource"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`forage-water-${opt}`"
                :model-value="onHasGroupValue('forage', 'water_source', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('forage', 'water_source', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Crop Information -->
    <div class="space-y-4 rounded-lg border p-4">
      <h4 class="text-foreground text-base font-semibold">Crop Information</h4>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Total Crop Area (ha)</Label>
          <Input
            :model-value="form.additional_details?.crop?.total_area"
            type="number"
            @update:model-value="val => onSetGroupDetail('crop', 'total_area', val)"
          />
        </div>
        <div class="space-y-2">
          <Label>Distance from House</Label>
          <Input
            :model-value="form.additional_details?.crop?.distance_from_house"
            @update:model-value="val => onSetGroupDetail('crop', 'distance_from_house', val)"
          />
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Crops Grown</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in cropOptions.cropsGrown"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`crop-grown-${opt}`"
                :model-value="onHasGroupValue('crop', 'crops_grown', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('crop', 'crops_grown', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Others (Crops Grown)</Label>
          <Input
            :model-value="form.additional_details?.crop?.crops_grown_others"
            placeholder="Specify other crops"
            @update:model-value="val => onSetGroupDetail('crop', 'crops_grown_others', val)"
          />
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Type of Ownership</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in cropOptions.ownership"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`crop-ownership-${opt}`"
                :model-value="onHasGroupValue('crop', 'ownership', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('crop', 'ownership', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Others (Type of Ownership)</Label>
          <Input
            :model-value="form.additional_details?.crop?.ownership_others"
            placeholder="Specify other ownership type"
            @update:model-value="val => onSetGroupDetail('crop', 'ownership_others', val)"
          />
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Farm Type</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in cropOptions.farmType"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`crop-farmtype-${opt}`"
                :model-value="onHasGroupValue('crop', 'farm_type', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('crop', 'farm_type', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Water Source</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in cropOptions.waterSource"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`crop-water-${opt}`"
                :model-value="onHasGroupValue('crop', 'water_source', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('crop', 'water_source', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Animal Shed -->
    <div class="space-y-4 rounded-lg border p-4">
      <h4 class="text-foreground text-base font-semibold">Animal Shed</h4>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Availability</Label>
          <div class="flex items-center space-x-4">
            <label
              v-for="opt in ['Available', 'Not available']"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`shed-availability-${opt}`"
                :model-value="onHasGroupValue('animal_shed', 'available', opt)"
                @update:model-value="
                  checked => onSetGroupDetail('animal_shed', 'available', checked ? [opt] : [])
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Type of Roof</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in shedOptions.roof"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`shed-roof-${opt}`"
                :model-value="onHasGroupValue('animal_shed', 'roof', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('animal_shed', 'roof', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div v-if="onHasGroupValue('animal_shed', 'roof', 'Other')" class="space-y-2">
          <Label>Specify roof type</Label>
          <Input
            :model-value="form.additional_details?.animal_shed?.roof_other"
            placeholder="Specify other roof type"
            @update:model-value="val => onSetGroupDetail('animal_shed', 'roof_other', val)"
          />
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Type of Posts</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in shedOptions.posts"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`shed-posts-${opt}`"
                :model-value="onHasGroupValue('animal_shed', 'posts', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('animal_shed', 'posts', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div v-if="onHasGroupValue('animal_shed', 'posts', 'Other')" class="space-y-2">
          <Label>Specify posts type</Label>
          <Input
            :model-value="form.additional_details?.animal_shed?.posts_other"
            placeholder="Specify other posts type"
            @update:model-value="val => onSetGroupDetail('animal_shed', 'posts_other', val)"
          />
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Type of Flooring</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in shedOptions.flooring"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`shed-flooring-${opt}`"
                :model-value="onHasGroupValue('animal_shed', 'flooring', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('animal_shed', 'flooring', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div v-if="onHasGroupValue('animal_shed', 'flooring', 'Other')" class="space-y-2">
          <Label>Specify flooring type</Label>
          <Input
            :model-value="form.additional_details?.animal_shed?.flooring_other"
            placeholder="Specify other flooring type"
            @update:model-value="val => onSetGroupDetail('animal_shed', 'flooring_other', val)"
          />
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Type of Ownership</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in shedOptions.ownership"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`shed-ownership-${opt}`"
                :model-value="onHasGroupValue('animal_shed', 'ownership', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('animal_shed', 'ownership', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label>Water Source</Label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <label
              v-for="opt in shedOptions.waterSource"
              :key="opt"
              class="flex items-center space-x-2 text-sm"
            >
              <Checkbox
                :id="`shed-water-${opt}`"
                :model-value="onHasGroupValue('animal_shed', 'water_source', opt)"
                @update:model-value="
                  checked => onToggleGroupValue('animal_shed', 'water_source', opt, checked)
                "
              />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
        <div class="space-y-2">
          <Label>Total Area of Animal Housing (sqm)</Label>
          <Input
            :model-value="form.additional_details?.animal_shed?.total_area"
            type="number"
            @update:model-value="val => onSetGroupDetail('animal_shed', 'total_area', val)"
          />
        </div>
        <div class="space-y-2">
          <Label>Distance from House</Label>
          <Input
            :model-value="form.additional_details?.animal_shed?.distance_from_house"
            @update:model-value="val => onSetGroupDetail('animal_shed', 'distance_from_house', val)"
          />
        </div>
        <div class="col-span-1 space-y-2 md:col-span-2">
          <Label class="text-muted-foreground">GPS Coordinates</Label>
          <div class="bg-muted/40 rounded-md border p-3">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>Latitude</Label>
                <Input v-model="form.gps_latitude" type="text" />
              </div>
              <div class="space-y-2">
                <Label>Longitude</Label>
                <Input v-model="form.gps_longitude" type="text" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Milk Production & Price -->
    <div class="space-y-4 rounded-lg border p-4">
      <h4 class="text-foreground text-base font-semibold">Milk Production & Price</h4>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Twice-a-day Milking</Label>
          <Select v-model="form.is_twice_a_day_milking">
            <SelectTrigger><SelectValue placeholder="Select" /></SelectTrigger>
            <SelectContent>
              <SelectItem :value="true">Yes</SelectItem>
              <SelectItem :value="false">No</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="space-y-2">
          <Label>Daily Milk Production (liters) - Minimum</Label>
          <Input v-model="form.daily_milk_production_min" type="number" min="0" step="0.01" />
        </div>
        <div class="space-y-2">
          <Label>Daily Milk Production (liters) - Maximum</Label>
          <Input v-model="form.daily_milk_production_max" type="number" min="0" step="0.01" />
        </div>
        <div class="space-y-2">
          <Label>Milk Price per Liter - Minimum</Label>
          <Input v-model="form.milk_price_per_liter_min" type="number" min="0" step="0.01" />
        </div>
        <div class="space-y-2">
          <Label>Milk Price per Liter - Maximum</Label>
          <Input v-model="form.milk_price_per_liter_max" type="number" min="0" step="0.01" />
        </div>
      </div>
    </div>

    <!-- Where Do You Sell Your Milk Produce? -->
    <div class="space-y-4 rounded-lg border p-4">
      <h4 class="text-foreground text-base font-semibold">Where Do You Sell Your Milk Produce?</h4>
      <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
        <label
          v-for="option in SELLING_LOCATION_OPTIONS"
          :key="option.key"
          class="flex items-center space-x-2 text-sm"
        >
          <Checkbox
            :id="`milk-sell-${option.key}`"
            :model-value="hasSellingLocation(option.key)"
            @update:model-value="checked => toggleSellingLocation(option.key, checked)"
          />
          <span>{{ option.label }}</span>
        </label>
      </div>
      <div v-if="hasSellingLocation('restaurant')" class="space-y-2">
        <Label>Known restaurant/establishment (specify)</Label>
        <Input v-model="form.milk_selling_restaurant_specify" />
      </div>
      <div v-if="hasSellingLocation('private_processor')" class="space-y-2">
        <Label>Private processor (specify)</Label>
        <Input v-model="form.milk_selling_processor_specify" />
      </div>
      <div v-if="hasSellingLocation('independent_collector')" class="space-y-2">
        <Label>Independent Milk Collector (please provide name)</Label>
        <Input v-model="form.milk_selling_collector_name" />
      </div>
      <div v-if="hasSellingLocation('others')" class="space-y-2">
        <Label>Others (specify)</Label>
        <Input v-model="form.milk_selling_others_specify" />
      </div>
    </div>

    <!-- Assistance Needed from Government -->
    <div class="space-y-4 rounded-lg border p-4">
      <h4 class="text-foreground text-base font-semibold">Assistance Needed from Government</h4>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div v-for="index in 4" :key="index" class="space-y-2">
          <Label>{{ index }}.</Label>
          <Input v-model="form.assistance_needed[index - 1]" placeholder="Type here" />
        </div>
      </div>
    </div>
  </div>
</template>
