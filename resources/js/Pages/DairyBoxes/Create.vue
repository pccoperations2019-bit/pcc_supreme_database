<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import DairyBoxForm from './Partials/Form.vue'

defineProps({
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  projectInvolvements: { type: Array, default: () => [] },
  enums: { type: Object, default: () => ({}) },
  dairyBox: { type: Object, default: () => ({}) },
  center: { type: Object, default: null },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
})
</script>

<template>
  <Head title="Create Dairy Box" />
  <DashboardLayout>
    <div class="mb-3">
      <h1 class="text-3xl font-bold">Create Dairy Box</h1>
      <p class="text-slate-600">
        Create a new dairy box record<span v-if="center"> for {{ center.name }}</span
        >.
      </p>
    </div>
    <DairyBoxForm
      mode="create"
      :dairy-box="dairyBox"
      :centers="centers"
      :partners="partners"
      :project-involvements="projectInvolvements"
      :enums="enums"
      :center="center"
      :default-center-id="defaultCenterId"
      :disable-center-field="disableCenterField"
    />
  </DashboardLayout>
</template>
