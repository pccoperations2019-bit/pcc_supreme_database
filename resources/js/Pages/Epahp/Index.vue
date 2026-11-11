<script setup>
import { ref } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import EpahpTable from './Partials/EpahpTable.vue'
import EpahpRecordFormModal from './Partials/EpahpRecordFormModal.vue'

defineProps({
  centers: { type: Array, required: true },
  gatasangKalabaws: { type: Object, required: true },
  capacityBuildings: { type: Object, required: true },
})

const gatasangColumns = [
  { key: 'center', label: 'Center', type: 'center' },
  { key: 'year', label: 'Year', type: 'year' },
  { key: 'number_of_cooperatives', label: 'Number of Cooperatives', type: 'number' },
  {
    key: 'number_of_farmer_recipients',
    label: 'Number of Farmer Recipients',
    type: 'number',
  },
  {
    key: 'number_of_animals_entrusted',
    label: 'Number of Animals Entrusted',
    type: 'number',
  },
  { key: 'dswd_counterpart', label: 'DSWD Counterpart', type: 'currency' },
]

const capacityBuildingColumns = [
  { key: 'center', label: 'Center', type: 'center' },
  { key: 'year', label: 'Year', type: 'year' },
  { key: 'number_of_cooperatives', label: 'Number of Cooperatives', type: 'number' },
  {
    key: 'number_of_farmers_trained',
    label: 'Number of Farmers Trained',
    type: 'number',
  },
  { key: 'training_conducted', label: 'Training Conducted', type: 'number' },
  { key: 'pcc_budget_allocated', label: 'PCC Budget Allocated', type: 'currency' },
]

const modal = ref({ open: false, table: null, record: null })

const openCreate = table => {
  modal.value = { open: true, table, record: null }
}

const openEdit = ({ table, record }) => {
  modal.value = { open: true, table, record }
}

const handleOpenChange = value => {
  if (!value) {
    modal.value = { open: false, table: null, record: null }
  }
}
</script>

<template>
  <Head title="Enhanced Partnership Against Hunger and Poverty" />

  <DashboardLayout>
    <div class="space-y-8">
      <EpahpTable
        title="Expanded Gatasang Kalabaw"
        :rows="gatasangKalabaws"
        :centers="centers"
        table-key="gatasang_kalabaw"
        :columns="gatasangColumns"
        destroy-route="epahp.gatasang-kalabaw.destroy"
        route-param="epahpGatasangKalabaw"
        @create="openCreate('gatasang_kalabaw')"
        @edit="openEdit({ table: 'gatasang_kalabaw', record: $event })"
      />

      <EpahpTable
        title="Capacity Building"
        :rows="capacityBuildings"
        :centers="centers"
        table-key="capacity_building"
        :columns="capacityBuildingColumns"
        destroy-route="epahp.capacity-building.destroy"
        route-param="epahpCapacityBuilding"
        @create="openCreate('capacity_building')"
        @edit="openEdit({ table: 'capacity_building', record: $event })"
      />
    </div>

    <EpahpRecordFormModal
      :open="modal.open"
      :table="modal.table"
      :record="modal.record"
      :centers="centers"
      @update:open="handleOpenChange"
    />
  </DashboardLayout>
</template>
