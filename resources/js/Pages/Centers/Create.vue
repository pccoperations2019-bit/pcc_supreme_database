<script setup>
import { reactive, ref, provide } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head } from '@inertiajs/vue3'
import CenterForm from './Partials/Form.vue'
import CenterModalForm from './Partials/CenterModalForm.vue'

defineProps({
  center: { type: Object, required: true },
  enums: { type: Object, required: true },
  products: { type: Array, default: () => [] },
  documents: { type: Array, default: () => [] },
  projects: { type: Object, default: () => ({}) },
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
  <Head title="Create Center" />
  <DashboardLayout>
    <div class="mb-3">
      <h1 class="text-3xl font-bold">New Center</h1>
      <p class="text-slate-600">Create a new center profile.</p>
    </div>
    <CenterForm
      :center="center"
      :products="products"
      :enums="enums"
      :documents="documents"
      :projects="projects"
      mode="create"
    />
    <CenterModalForm :center="center" :products="products" :enums="enums" />
  </DashboardLayout>
</template>
