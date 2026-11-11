<script setup>
import { reactive, ref, provide } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import PartnerForm from './Partials/Form.vue'
import PartnerModalForm from './Partials/PartnerModalForm.vue'

defineProps({
  partner: { type: Object, required: true },
  enums: { type: Object, required: true },
  products: { type: Array, default: () => [] },
  documents: { type: Array, default: () => [] },
  center: { type: Object, default: null },
  centers: { type: Array, default: () => [] },
  projects: { type: Object, default: () => ({}) },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
})

const modalState = ref({ open: false, type: null, id: null })
provide('modalState', modalState)

let draftSequence = 0
const nextDraftId = () => `draft-${++draftSequence}`

const createDrafts = reactive({
  productionUnits: [],
  consolidationUnits: [],
  processingUnits: [],
  marketingUnits: [],
  products: [],
  documents: [],
})
provide('createDrafts', createDrafts)
provide('nextDraftId', nextDraftId)
</script>

<template>
  <Head title="Create Partner" />
  <DashboardLayout>
    <div class="mb-3">
      <h1 class="text-3xl font-bold">New Partner</h1>
      <p class="text-slate-600">
        Create a new partner profile<span v-if="center"> for {{ center.name }}</span
        >.
      </p>
    </div>
    <PartnerForm
      :partner="partner"
      :products="products"
      :enums="enums"
      :documents="documents"
      :centers="centers"
      :projects="projects"
      :default-center-id="defaultCenterId"
      :disable-center-field="disableCenterField"
      mode="create"
    />
    <PartnerModalForm :partner="partner" :products="products" :enums="enums" />
  </DashboardLayout>
</template>
