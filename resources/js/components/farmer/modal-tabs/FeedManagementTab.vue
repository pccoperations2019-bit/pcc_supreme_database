<script setup>
const form = defineModel('form', { type: Object, required: true })

const FEED_CATEGORIES = [
  {
    name: 'Roughage',
    items: [
      { key: 'napier', label: 'Napier' },
      { key: 'guine', label: 'Guinea Grass' },
      { key: 'paragrass', label: 'Paragrass' },
      { key: 'mombasa', label: 'Mombasa' },
      { key: 'sorghum', label: 'Sorghum' },
      { key: 'ricestraw', label: 'Rice Straw' },
      { key: 'cornstover', label: 'Corn Stover' },
      { key: 'legumes', label: 'Legumes' },
      { key: 'utrs', label: 'UTRS' },
      { key: 'silage', label: 'Silage' },
      { key: 'sakate', label: 'Sakate' },
      { key: 'forage', label: 'Forage' },
      { key: 'kamote', label: 'Kamote Leaves' },
      { key: 'ipil', label: 'Ipil-ipil' },
      { key: 'banana', label: 'Banana' },
      { key: 'acacia', label: 'Acacia' },
      { key: 'kakawate', label: 'Kakawate' },
    ],
  },
  {
    name: 'Concentrates',
    items: [
      { key: 'commercial', label: 'Commercial Feeds' },
      { key: 'ricebran', label: 'Rice Bran' },
      { key: 'grain', label: 'Corn Grain' },
      { key: 'bran', label: 'Corn Bran' },
    ],
  },
  {
    name: 'Supplements',
    items: [
      { key: 'dicalcium', label: 'Dicalcium Phosphate' },
      { key: 'salt', label: 'Salt' },
      { key: 'molasses', label: 'Molasses' },
      { key: 'atovi', label: 'Atowi' },
      { key: 'ummb', label: 'UMMB (Mineral Block)' },
    ],
  },
]

const setAdditionalDetail = (key, value) => {
  const k = String(key || '').toLowerCase()
  form.value.additional_details = {
    ...(form.value.additional_details || {}),
    [k]: value,
  }
}
</script>

<template>
  <div class="col-span-1 space-y-6 md:col-span-2">
    <div class="space-y-3 rounded-lg border p-4">
      <h4 class="text-base font-semibold">Feed Resources</h4>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left">
              <th class="py-2 pr-4 font-medium text-slate-500">Feed Resources</th>
              <th class="py-2 pr-4 text-center font-medium text-slate-500">Rainy Season</th>
              <th class="py-2 text-center font-medium text-slate-500">Dry Season</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="category in FEED_CATEGORIES" :key="category.name">
              <tr class="border-b bg-slate-50">
                <td colspan="3" class="py-2 font-semibold text-slate-700">
                  {{ category.name }}
                </td>
              </tr>
              <tr v-for="item in category.items" :key="item.key" class="border-b last:border-0">
                <td class="py-2 pr-4">{{ item.label }}</td>
                <td class="py-2 pr-4 text-center">
                  <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-400"
                    :checked="!!form.additional_details?.[`israiny${item.key}`]"
                    @change="setAdditionalDetail(`israiny${item.key}`, $event.target.checked)"
                  />
                </td>
                <td class="py-2 text-center">
                  <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-400"
                    :checked="!!form.additional_details?.[`isdry${item.key}`]"
                    @change="setAdditionalDetail(`isdry${item.key}`, $event.target.checked)"
                  />
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
