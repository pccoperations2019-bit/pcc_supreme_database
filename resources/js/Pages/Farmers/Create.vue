<script setup>
import { reactive, ref, provide } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import FarmerForm from './Partials/Form.vue'
import FarmerModalForm from './Partials/FarmerModalForm.vue'

defineProps({
  farmer: { type: Object, required: true },
  enums: { type: Object, required: true },
  documents: { type: Array, default: () => [] },
  centers: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  projects: { type: Object, default: () => ({}) },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
})

let draftSequence = 0
const nextDraftId = () => `draft-${++draftSequence}`

const modalState = ref({ open: false, type: null, id: null, lastClosedAt: 0 })
provide('modalState', modalState)

const createDrafts = reactive({
  documents: [],
  'farming-info': null,
  'animal-inventory': null,
  'income-info': null,
  'feed-management': null,
  'livestock-info': null,
  'waste-management': null,
  geotag: null,
  'poverty-index': null,
  'socio-economic': null,
})
provide('createDrafts', createDrafts)
provide('nextDraftId', nextDraftId)
</script>

<template>
  <Head title="Create Farmer" />
  <DashboardLayout>
    <div class="mb-3">
      <h1 class="text-3xl font-bold">New Farmer</h1>
      <p class="text-slate-600">Create a new farmer profile.</p>
    </div>
    <FarmerForm
      :farmer="farmer"
      :enums="enums"
      :documents="documents"
      :centers="centers"
      :partners="partners"
      :projects="projects"
      :default-center-id="defaultCenterId"
      :disable-center-field="disableCenterField"
      mode="create"
    />
    <FarmerModalForm :farmer="farmer" :enums="enums" />
  </DashboardLayout>
</template>
