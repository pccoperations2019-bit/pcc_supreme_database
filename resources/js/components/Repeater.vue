<script setup>
import { ref, watch } from 'vue'
import { Input } from '@/components/ui/input'
import SearchableSelect from '@/components/SearchableSelect.vue'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  options: {
    type: Object,
    default: () => ({}),
  },
  keys: {
    type: Object,
    default: () => ({}),
  },
  keyPlaceholder: {
    type: String,
    default: 'Key',
  },
  valuePlaceholder: {
    type: String,
    default: 'Value',
  },
})

const items = ref([...props.modelValue])

const emit = defineEmits(['update:modelValue'])

// Watch for changes to modelValue and sync with items
watch(
  () => props.modelValue,
  newValue => {
    items.value = [...newValue]
  },
  { deep: true }
)
</script>
<template>
  <div class="space-y-4">
    <span>Tags</span>
    <div v-for="(item, index) in items" :key="index" class="flex items-center space-x-2">
      <div v-if="item.key === '' || !props.keys[item.key]" class="flex-1">
        <Select v-model="item.key" @value-change="emit('update:modelValue', items)">
          <SelectTrigger>
            <SelectValue placeholder="Select a key" />
          </SelectTrigger>
          <SelectContent>
            <SelectGroup>
              <SelectLabel>Keys</SelectLabel>
              <SelectItem
                v-for="(keyOption, keyIndex) in props.keys['']"
                :key="keyIndex"
                :value="keyOption.key || keyOption"
              >
                <span v-if="keyOption.label">
                  {{ keyOption.label }}
                </span>
                <span v-else>
                  {{ keyOption }}
                </span>
              </SelectItem>
            </SelectGroup>
          </SelectContent>
        </Select>
      </div>
      <div v-else class="flex-1">
        <Select v-model="item.key" @value-change="emit('update:modelValue', items)">
          <SelectTrigger>
            <SelectValue placeholder="Select a key" />
          </SelectTrigger>
          <SelectContent>
            <SelectGroup>
              <SelectLabel>Keys</SelectLabel>
              <SelectItem
                v-for="(keyOption, keyIndex) in props.keys[item.key]"
                :key="keyIndex"
                :value="keyOption.key || keyOption"
              >
                <span v-if="keyOption.label">
                  {{ keyOption.label }}
                </span>
                <span v-else>
                  {{ keyOption }}
                </span>
              </SelectItem>
            </SelectGroup>
          </SelectContent>
        </Select>
      </div>
      <div v-if="!props.options[item.key]" class="flex-1">
        <Input
          v-model="item.value"
          :placeholder="props.valuePlaceholder"
          @input="emit('update:modelValue', items)"
        />
      </div>
      <div v-else-if="item.key === 'partner'" class="flex-1">
        <SearchableSelect
          v-model="item.value"
          endpoint="/xhr/partners"
          placeholder="Select a partner"
          @update:model-value="emit('update:modelValue', items)"
        />
      </div>
      <div v-else-if="item.key === 'farmer'" class="flex-1">
        <SearchableSelect
          v-model="item.value"
          endpoint="/xhr/farmers"
          placeholder="Select a farmer"
          @update:model-value="emit('update:modelValue', items)"
        />
      </div>
      <div v-else class="flex-1">
        <Select v-model="item.value" @value-change="emit('update:modelValue', items)">
          <SelectTrigger>
            <SelectValue placeholder="Select an option" />
          </SelectTrigger>
          <SelectContent>
            <SelectGroup>
              <SelectLabel>Options</SelectLabel>
              <SelectItem
                v-for="(option, optIndex) in props.options[item.key]"
                :key="optIndex"
                :value="option.key || option"
              >
                <span v-if="option.label">
                  {{ option.label }}
                </span>
                <span v-else>
                  {{ option }}
                </span>
              </SelectItem>
            </SelectGroup>
          </SelectContent>
        </Select>
      </div>
    </div>
    <!-- <Button class="mx-auto flex" variant="outline" @click="addItem">
      <PlusCircle class="mr-2 h-4 w-4" /> Add Item
    </Button> -->
  </div>
</template>
