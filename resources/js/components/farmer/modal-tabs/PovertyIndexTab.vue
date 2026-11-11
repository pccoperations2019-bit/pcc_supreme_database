<script setup>
import BoolSelect from '@/components/form/BoolSelect.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const { form, onSetAdditionalDetail } = defineProps({
  form: { type: Object, required: true },
  enums: { type: Object, default: () => ({}) },
  onSetAdditionalDetail: { type: Function, required: true },
  onSetTvType: { type: Function, required: true },
  onToBooleanLike: { type: Function, required: true },
})

const isFalseLike = value =>
  value === null ||
  value === undefined ||
  value === '' ||
  ['false', 'FALSE', 'False', 'no', 'No', 'NO'].includes(value)

const otherRemarksValue = isFalseLike(form.additional_details?.otherremarks)
  ? ''
  : form.additional_details?.otherremarks

const setOtherRemarks = value => onSetAdditionalDetail('otherremarks', value)

const roofMaterialOptions = [
  'Light materials (cogon, nipa, anahaw, salvaged/makeshift materials)',
  'Mixed but predominantly light materials',
  'Mixed but predominantly salvaged materials',
  'Mixed but predominantly strong materials',
  'Strong materials (galvanized iron, aluminum, tile, concrete, brick, stone, asbestos)',
]

const outerWallMaterialOptions = [
  'Light materials (bamboo, sawali, cogon, nipa, anahaw)',
  'Salvaged/makeshift materials',
  'Mixed but predominantly light materials',
  'Mixed but predominantly salvaged materials',
  'Mixed but predominantly strong materials',
  'Strong materials (galvanized iron, aluminum, tile, concrete, brick, stone, wood, plywood, asbestos)',
]
</script>

<template>
  <div class="col-span-1 space-y-6 md:col-span-2">
    <div class="space-y-3 rounded-lg border p-4">
      <h4 class="text-base font-semibold">Housing Conditions</h4>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Roof Material Type</Label>
          <Select
            :model-value="form.additional_details?.roofmadeof"
            @update:model-value="val => onSetAdditionalDetail('roofmadeof', val)"
          >
            <SelectTrigger>
              <SelectValue placeholder="Select roof material" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="opt in roofMaterialOptions" :key="opt" :value="opt">
                {{ opt }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="space-y-2">
          <Label>Outer Wall Material Type</Label>
          <Select
            :model-value="form.additional_details?.housemadeof"
            @update:model-value="val => onSetAdditionalDetail('housemadeof', val)"
          >
            <SelectTrigger>
              <SelectValue placeholder="Select outer wall material" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="opt in outerWallMaterialOptions" :key="opt" :value="opt">
                {{ opt }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
      <BoolSelect
        id="poverty-has-electricity"
        label="House has access to electricity?"
        :model-value="form.additional_details?.isaccesstoelectricity"
        @update:model-value="val => onSetAdditionalDetail('isaccesstoelectricity', val)"
      />
    </div>

    <div class="space-y-3 rounded-lg border p-4">
      <h4 class="text-base font-semibold">Household Appliances</h4>
      <div class="space-y-2">
        <BoolSelect
          id="poverty-has-refrigerator"
          label="Does your household own a refrigerator or freezer?"
          :model-value="form.additional_details?.isownrefrigerator"
          @update:model-value="val => onSetAdditionalDetail('isownrefrigerator', val)"
        />
        <BoolSelect
          id="poverty-has-washing-machine"
          label="Does your household own a washing machine?"
          :model-value="form.additional_details?.isownwashingmachine"
          @update:model-value="val => onSetAdditionalDetail('isownwashingmachine', val)"
        />
        <BoolSelect
          id="poverty-has-television"
          label="Does your household own a television or TV?"
          :model-value="form.additional_details?.isowntelevision"
          @update:model-value="val => onSetAdditionalDetail('isowntelevision', val)"
        />
        <div
          v-if="onToBooleanLike(form.additional_details?.isowntelevision)"
          class="space-y-2 pt-1 pl-7"
        >
          <Label>TV Type</Label>
          <Select :model-value="form.tv_type" @update:model-value="val => onSetTvType(val)">
            <SelectTrigger><SelectValue placeholder="Select TV type" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="(labelValue, key) in enums.tvType" :key="key" :value="key">
                {{ labelValue }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <BoolSelect
          id="poverty-has-radio"
          label="Does your household own a radio?"
          :model-value="form.additional_details?.isownradio"
          @update:model-value="val => onSetAdditionalDetail('isownradio', val)"
        />
      </div>
    </div>

    <div class="space-y-3 rounded-lg border p-4">
      <h4 class="text-base font-semibold">Water Source</h4>
      <div class="space-y-2">
        <BoolSelect
          id="poverty-dug-well"
          label="Dug well with or without pump?"
          :model-value="form.additional_details?.isdugwell"
          @update:model-value="val => onSetAdditionalDetail('isdugwell', val)"
        />
        <BoolSelect
          id="poverty-own-waterline"
          label="Own waterline like Maynilad or MWSS?"
          :model-value="form.additional_details?.isownwaterline"
          @update:model-value="val => onSetAdditionalDetail('isownwaterline', val)"
        />
        <BoolSelect
          id="poverty-shared-waterline"
          label="Shared water line like Maynilad or MWSS?"
          :model-value="form.additional_details?.issharedwaterline"
          @update:model-value="val => onSetAdditionalDetail('issharedwaterline', val)"
        />
        <BoolSelect
          id="poverty-own-deepwell"
          label="Own deep well with a hand or motor pump?"
          :model-value="form.additional_details?.isowndeepwell"
          @update:model-value="val => onSetAdditionalDetail('isowndeepwell', val)"
        />
        <BoolSelect
          id="poverty-shared-deepwell"
          label="Shared deep well with a hand or motor pump?"
          :model-value="form.additional_details?.isshareddeepwell"
          @update:model-value="val => onSetAdditionalDetail('isshareddeepwell', val)"
        />
        <BoolSelect
          id="poverty-others"
          label="Others?"
          :model-value="form.additional_details?.isothers"
          @update:model-value="val => onSetAdditionalDetail('isothers', val)"
        />
        <div class="space-y-2">
          <Label>Other Remarks</Label>
          <Input :model-value="otherRemarksValue" @update:model-value="setOtherRemarks" />
        </div>
      </div>
    </div>
  </div>
</template>
