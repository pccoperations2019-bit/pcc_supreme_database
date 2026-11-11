<script setup>
import { computed, defineProps, provide, ref } from 'vue'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { cn, label, productNameLabel } from '@/lib/utils'
import { monthsArray } from '@/enums/MonthEnum'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { ButtonGroup } from '@/components/ui/button-group'
import { Download as DownloadIcon } from 'lucide-vue-next'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import PartnerModalForm from './PartnerModalForm.vue'

const { partner, products, enums, documents, farmers, dairyBoxes } = defineProps({
  partner: { type: Object, required: true },
  products: {
    type: Array,
  },
  enums: {
    type: Object,
    required: true,
  },
  documents: {
    type: Array,
    required: true,
  },
  farmers: {
    type: Object,
    required: true,
  },
  dairyBoxes: {
    type: Array,
    default: () => [],
  },
})

const formatDate = date => {
  if (!date) return 'N/A'
  const d = new Date(date)
  const month = monthsArray[d.getMonth()]
  return `${month.label} ${d.getDate()}, ${d.getFullYear()}`
}

const metadataSections = [
  {
    title: 'Registration & Business',
    fields: [
      { id: 'date_of_enrollment', label: 'Date of Enrollment', type: 'date' },
      { id: 'incharge', label: 'In Charge', type: 'text' },
      { id: 'business_name', label: 'Business Name', type: 'text' },
      { id: 'business_shortname', label: 'Business Short Name', type: 'text' },
      {
        id: 'assistance_status',
        label: 'Assistance Status',
        type: 'select',
        options: enums?.assistanceStatuses || {},
      },
      { id: 'date_assisted', label: 'Date Assisted', type: 'date' },
      {
        id: 'partner_businesstype',
        label: 'Business Type',
        type: 'select',
        options: enums?.categories || {},
      },
      {
        id: 'registrationtype',
        label: 'Registration Type',
        type: 'select',
        options: enums?.registrationTypes || {},
      },
      { id: 'registration_no', label: 'Registration No.', type: 'text' },
      { id: 'registration_date', label: 'Registration Date', type: 'date' },
    ],
  },
  {
    title: 'Management & Membership',
    fields: [
      { id: 'name_of_manager', label: 'Name of Manager', type: 'text' },
      { id: 'manager_contact_no', label: 'Manager Contact No.', type: 'tel' },
      { id: 'manager_email', label: 'Manager Email', type: 'email' },
      {
        id: 'contact_person_contact_no',
        label: 'Contact Person Contact No.',
        type: 'tel',
      },
      { id: 'contact_person_email', label: 'Contact Person Email', type: 'email' },
      { id: 'bodcount', label: 'BOD Count', type: 'number' },
      { id: 'bodschedule', label: 'BOD Schedule', type: 'text' },
      { id: 'active_count', label: 'Active Count', type: 'number' },
    ],
  },
  {
    title: 'Business Details & Maps',
    fields: [
      { id: 'main_business', label: 'Main Business', type: 'text' },
      { id: 'other_line_of_business', label: 'Other Line of Business', type: 'text' },
      { id: 'businesscount', label: 'Business Count', type: 'number' },
      { id: 'purokzone', label: 'Purok / Zone', type: 'text' },
      { id: 'status_google_map', label: 'Google Map Status', type: 'text' },
      { id: 'geo_tag_image', label: 'Geo Tag Image', type: 'text' },
      { id: 'google_map_link', label: 'Google Map Link', type: 'url' },
    ],
  },
  {
    title: 'Financials',
    fields: [
      { id: 'assets', label: 'Assets', type: 'text' },
      { id: 'asset_php', label: 'Asset (PHP)', type: 'number', step: '0.01' },
      { id: 'asset_year', label: 'Asset (Year)', type: 'text' },
      { id: 'netincome_php', label: 'Net Income (PHP)', type: 'number', step: '0.01' },
      { id: 'income_year', label: 'Income (Year)', type: 'text' },
      {
        id: 'function_in_the_value_chain',
        label: 'Function in the Value Chain',
        type: 'text',
      },
      {
        id: 'production',
        label: 'Production',
        type: 'select',
        options: { yes: 'Yes', no: 'No' },
      },
      {
        id: 'processing',
        label: 'Processing',
        type: 'select',
        options: { yes: 'Yes', no: 'No' },
      },
      {
        id: 'marketing',
        label: 'Marketing',
        type: 'select',
        options: { yes: 'Yes', no: 'No' },
      },
      {
        id: 'engagedmilkfeeding',
        label: 'Engaged in Milk Feeding',
        type: 'select',
        options: { yes: 'Yes', no: 'No' },
      },
    ],
  },
]

const hasMetadataValue = id => {
  const value = partner.metadata?.[id]
  return value !== '' && value !== null && value !== undefined
}

const sectionHasData = section => section.fields.some(field => hasMetadataValue(field.id))

const metadataValueLabel = (field, value) => {
  if (value === '' || value === null || value === undefined) return ''
  if (field.type === 'select') return field.options?.[value] || value
  if (field.type === 'date') {
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString()
  }
  return value
}

const queueingExport = ref(false)

const modalState = ref({ open: false, type: null, id: null })
provide('modalState', modalState)

// Document modal functionality
const showDocumentModal = ref(false)
const selectedDocument = ref(null)

const openDocumentModal = document => {
  selectedDocument.value = document
  showDocumentModal.value = true
}

const downloadDocument = async doc => {
  try {
    const response = await fetch(`/${doc.file.path}`)
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = doc.title || 'document'
    link.style.display = 'none'
    document.body.appendChild(link)
    link.click()
    setTimeout(() => {
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    }, 100)
  } catch (error) {
    console.error('Download failed:', error)
    window.open(`/${doc.file.path}`, '_blank')
  }
}

const normalizedAddress = address => {
  if (!address) return 'N/A'
  const { street, barangay, city, province, region, zip_code, country } = address
  return [street, barangay, city, province, region, zip_code, country].filter(Boolean).join(', ')
}

const handleExportMembers = async format => {
  if (queueingExport.value) return
  queueingExport.value = true

  try {
    const response = await axios.post(`/partners/${partner.id}/members/export`, {
      format: format,
      queue_export: true,
    })

    window.dispatchEvent(
      new window.CustomEvent('report-export-queued', {
        detail: {
          documentId: response.data.document_id,
          reportType: 'partner-members',
          format,
          status: 'processing',
          fileName: null,
          fileSize: 0,
          downloadUrl: null,
        },
      })
    )
  } catch (error) {
    console.error('Export failed:', error)
  } finally {
    queueingExport.value = false
  }
}

const paginationPages = computed(() => {
  if (!farmers.links || farmers.links.length <= 3) return []

  const pageLinks = farmers.links.filter(l => {
    const stripped = l.label?.replace(/&laquo;|&raquo;/g, '').trim()
    return stripped && !isNaN(Number(stripped))
  })

  const total = pageLinks.length
  if (total <= 7) return pageLinks

  const currentIndex = pageLinks.findIndex(l => l.active)

  const result = []
  const addLink = l => result.push(l)
  const addEllipsis = () => result.push({ label: '...', url: null, active: false, ellipsis: true })

  addLink(pageLinks[0])

  if (currentIndex <= 3) {
    for (let i = 1; i <= Math.min(4, total - 2); i++) addLink(pageLinks[i])
    addEllipsis()
  } else if (currentIndex >= total - 4) {
    addEllipsis()
    for (let i = Math.max(1, total - 5); i <= total - 2; i++) addLink(pageLinks[i])
  } else {
    addEllipsis()
    addLink(pageLinks[currentIndex - 1])
    addLink(pageLinks[currentIndex])
    addLink(pageLinks[currentIndex + 1])
    addEllipsis()
  }

  addLink(pageLinks[total - 1])

  const prev = farmers.links[0]
  const next = farmers.links[farmers.links.length - 1]
  return { prev, next, pages: result }
})
</script>

<template>
  <!-- ── Sticky header ── -->
  <div
    class="sticky top-0 z-20 mb-3 flex flex-wrap items-start justify-between gap-3 rounded-lg border-b bg-white px-4 py-4"
  >
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2">
        <h1 class="truncate text-2xl font-bold text-gray-700 sm:text-3xl">
          {{ partner.name }}
        </h1>
        <Badge v-if="partner.category">{{ label(enums.categories, partner.category) }}</Badge>
        <!-- Partnership Type badge (hidden for now)
        <Badge v-if="partner.partnership_type">{{
          label(enums.partnershipTypes, partner.partnership_type)
        }}</Badge>
        -->
      </div>
    </div>
    <Button
      variant="outline"
      class="shrink-0 cursor-pointer text-slate-900 hover:text-slate-900"
      @click="router.visit(route('partners.edit', partner.id))"
    >
      Edit
    </Button>
  </div>

  <div class="flex flex-col space-y-4">
    <!-- ── Logo + General Info ── -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
      <!-- Logo -->
      <div class="flex flex-col rounded-lg bg-gray-100 p-4 lg:col-span-1">
        <Avatar :class="cn('m-auto aspect-square h-auto w-full max-w-[280px] md:max-w-[18rem]')">
          <AvatarImage
            :src="partner.logo_url || partner.logo"
            alt="Partner Logo"
            class="object-cover"
          />
          <AvatarFallback>
            <img
              src="/images/partners.png"
              alt="Placeholder Image"
              class="h-full w-full object-cover"
            />
          </AvatarFallback>
        </Avatar>
      </div>

      <Card class="lg:col-span-4">
        <CardHeader>
          <CardTitle>General Information</CardTitle>
          <CardDescription>Demographics and Geographic</CardDescription>
        </CardHeader>
        <CardContent>
          <dl class="mb-4 grid grid-cols-1 gap-x-8 gap-y-3 border-b py-2 sm:grid-cols-2">
            <dt class="text-muted-foreground font-medium">Code</dt>
            <dd class="sm:text-right">{{ partner.code || 'N/A' }}</dd>
            <dt class="text-muted-foreground font-medium">Address</dt>
            <dd class="break-words sm:text-right">
              {{ normalizedAddress(partner.address) }}
            </dd>
            <dt class="text-muted-foreground font-medium">Website</dt>
            <dd class="break-all sm:text-right">{{ partner.website || 'N/A' }}</dd>
          </dl>

          <!-- Contact fields -->
          <dl
            class="mb-4 grid grid-cols-1 gap-x-8 gap-y-3 border-b py-2 sm:grid-cols-2 lg:grid-cols-4"
          >
            <dt class="text-muted-foreground font-medium">Date of Establishment</dt>
            <dd class="sm:text-right">{{ partner.date_of_establishment || 'N/A' }}</dd>
            <dt class="text-muted-foreground font-medium">Contact Person</dt>
            <dd class="sm:text-right">{{ partner.contact_person || 'N/A' }}</dd>
            <dt class="text-muted-foreground font-medium">Position</dt>
            <dd class="sm:text-right">{{ partner.position || 'N/A' }}</dd>
            <dt class="text-muted-foreground font-medium">Contact Number</dt>
            <dd class="sm:text-right">{{ partner.contact_number || 'N/A' }}</dd>
            <dt class="text-muted-foreground font-medium">Telephone Number</dt>
            <dd class="sm:text-right">{{ partner.telephone_number || 'N/A' }}</dd>
            <dt class="text-muted-foreground font-medium">Email Address</dt>
            <dd class="break-all sm:text-right">{{ partner.email_address || 'N/A' }}</dd>
          </dl>

          <!-- Composition fields -->
          <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
            <dt class="text-muted-foreground font-medium">Total Composition</dt>
            <dd class="sm:text-right">{{ partner.total_composition || 0 }}</dd>
            <dt class="text-muted-foreground font-medium">Male Count</dt>
            <dd class="sm:text-right">{{ partner.male_count || 0 }}</dd>
            <dt class="text-muted-foreground font-medium">Female Count</dt>
            <dd class="sm:text-right">{{ partner.female_count || 0 }}</dd>
            <dt class="text-muted-foreground font-medium">Average Monthly Income</dt>
            <dd class="sm:text-right">
              {{ partner.avg_monthly_income ? `₱${partner.avg_monthly_income}` : '₱0.00' }}
            </dd>
          </dl>
        </CardContent>
      </Card>
    </div>

    <!-- ── Extended Profile ── -->
    <Card>
      <CardHeader>
        <CardTitle>Extended Profile</CardTitle>
        <CardDescription
          >Registration, management, business details, and financial information</CardDescription
        >
      </CardHeader>
      <CardContent>
        <Tabs default-value="Registration & Business" :class="cn('bordered')">
          <TabsList class="grid h-auto w-full grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-4">
            <TabsTrigger
              v-for="section in metadataSections"
              :key="section.title"
              :value="section.title"
              class="text-xs sm:text-sm"
            >
              {{ section.title }}
            </TabsTrigger>
          </TabsList>
          <TabsContent
            v-for="section in metadataSections"
            :key="section.title"
            :value="section.title"
          >
            <dl
              v-if="sectionHasData(section)"
              class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2"
            >
              <div
                v-for="field in section.fields"
                :key="field.id"
                class="flex items-baseline justify-between gap-2"
              >
                <dt class="text-muted-foreground font-medium">{{ field.label }}</dt>
                <dd class="min-w-0 font-medium break-words">
                  {{ metadataValueLabel(field, partner.metadata?.[field.id]) || 'N/A' }}
                </dd>
              </div>
            </dl>
            <div v-else class="text-muted-foreground py-8 text-center">
              No {{ section.title }} data found.
            </div>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Value Chain Units</CardTitle>
        <CardDescription>Contributors in the chain</CardDescription>
      </CardHeader>
      <CardContent>
        <Tabs default-value="production" :class="cn('bordered')">
          <!-- 2×2 on mobile → single row on sm+ -->
          <TabsList class="grid h-auto w-full grid-cols-2 gap-1 sm:grid-cols-4">
            <TabsTrigger value="production" class="text-xs sm:text-sm">Production</TabsTrigger>
            <TabsTrigger value="consolidation" class="text-xs sm:text-sm"
              >Consolidation</TabsTrigger
            >
            <TabsTrigger value="processing" class="text-xs sm:text-sm">Processing</TabsTrigger>
            <TabsTrigger value="marketing" class="text-xs sm:text-sm">Marketing</TabsTrigger>
          </TabsList>

          <!-- Production -->
          <TabsContent value="production">
            <div class="space-y-4">
              <div
                v-for="production in partner.production_units"
                :key="production.id"
                class="rounded-lg border p-4"
              >
                <div class="mb-4 flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <h4 class="text-lg font-semibold">{{ production.name }}</h4>
                    <p class="text-muted-foreground text-sm">
                      {{ label(enums.productionCategory, production.category) }} •
                      {{ label(enums.herdType, production.herd_type) }} • Established:
                      {{ production.date_of_establishment }}
                    </p>
                  </div>
                  <Button
                    size="sm"
                    variant="outline"
                    class="shrink-0"
                    @click="openEditModal('production-units', production.id)"
                  >
                    Edit
                  </Button>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <dt class="text-muted-foreground font-medium">Contact Person</dt>
                  <dd class="sm:text-right">{{ production.contact_person || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Position</dt>
                  <dd class="sm:text-right">{{ production.position || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="sm:text-right">{{ production.contact_number || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Telephone Number</dt>
                  <dd class="sm:text-right">
                    {{ production.telephone_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Email Address</dt>
                  <dd class="break-all sm:text-right">
                    {{ production.email_address || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Website</dt>
                  <dd class="break-all sm:text-right">
                    {{ production.website || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>
                  <dd class="sm:text-right">
                    {{ production.number_of_staff_male || '0' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>
                  <dd class="sm:text-right">
                    {{ production.number_of_staff_female || '0' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>
                  <dd class="sm:text-right">
                    {{ production.avg_salary_of_staff || '0.00' }}
                  </dd>
                </dl>
              </div>
              <div
                v-if="!partner.production_units?.length"
                class="text-muted-foreground py-8 text-center"
              >
                No production units found.
              </div>
            </div>
          </TabsContent>

          <!-- Consolidation -->
          <TabsContent value="consolidation">
            <div class="space-y-4">
              <div
                v-for="consolidation in partner.consolidation_units"
                :key="consolidation.id"
                class="rounded-lg border p-4"
              >
                <div class="mb-4 flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <h4 class="text-lg font-semibold">{{ consolidation.name }}</h4>
                    <p class="text-muted-foreground text-sm">
                      {{ label(enums.consolidationCategory, consolidation.category) }} •
                      Established: {{ consolidation.date_of_establishment }}
                    </p>
                  </div>
                  <Button
                    size="sm"
                    variant="outline"
                    class="shrink-0"
                    @click="openEditModal('consolidation-units', consolidation.id)"
                  >
                    Edit
                  </Button>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <dt class="text-muted-foreground font-medium">Contact Person</dt>
                  <dd class="sm:text-right">
                    {{ consolidation.contact_person || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Position</dt>
                  <dd class="sm:text-right">{{ consolidation.position || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="sm:text-right">
                    {{ consolidation.contact_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Telephone Number</dt>
                  <dd class="sm:text-right">
                    {{ consolidation.telephone_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Email Address</dt>
                  <dd class="break-all sm:text-right">
                    {{ consolidation.email_address || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Website</dt>
                  <dd class="break-all sm:text-right">
                    {{ consolidation.website || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>
                  <dd class="sm:text-right">
                    {{ consolidation.number_of_staff_male || '0' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>
                  <dd class="sm:text-right">
                    {{ consolidation.number_of_staff_female || '0' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>
                  <dd class="sm:text-right">
                    {{ consolidation.avg_salary_of_staff || '0.00' }}
                  </dd>
                </dl>
              </div>
              <div
                v-if="!partner.consolidation_units?.length"
                class="text-muted-foreground py-8 text-center"
              >
                No consolidation units found.
              </div>
            </div>
          </TabsContent>

          <!-- Processing -->
          <TabsContent value="processing">
            <div class="space-y-4">
              <div
                v-for="processing in partner.processing_units"
                :key="processing.id"
                class="rounded-lg border p-4"
              >
                <div class="mb-4 flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <h4 class="text-lg font-semibold">{{ processing.name }}</h4>
                    <p class="text-muted-foreground text-sm">
                      {{ label(enums.processingCategory, processing.category) }} • Established:
                      {{ processing.date_of_establishment }}
                    </p>
                  </div>
                  <Button
                    size="sm"
                    variant="outline"
                    class="shrink-0"
                    @click="openEditModal('processing-units', processing.id)"
                  >
                    Edit
                  </Button>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <dt class="text-muted-foreground font-medium">Contact Person</dt>
                  <dd class="sm:text-right">{{ processing.contact_person || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Position</dt>
                  <dd class="sm:text-right">{{ processing.position || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="sm:text-right">{{ processing.contact_number || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Telephone Number</dt>
                  <dd class="sm:text-right">
                    {{ processing.telephone_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Email Address</dt>
                  <dd class="break-all sm:text-right">
                    {{ processing.email_address || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Website</dt>
                  <dd class="break-all sm:text-right">
                    {{ processing.website || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>
                  <dd class="sm:text-right">
                    {{ processing.number_of_staff_male || '0' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>
                  <dd class="sm:text-right">
                    {{ processing.number_of_staff_female || '0' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>
                  <dd class="sm:text-right">
                    {{ processing.avg_salary_of_staff || '0.00' }}
                  </dd>
                </dl>
              </div>
              <div
                v-if="!partner.processing_units?.length"
                class="text-muted-foreground py-8 text-center"
              >
                No processing units found.
              </div>
            </div>
          </TabsContent>

          <!-- Marketing -->
          <TabsContent value="marketing">
            <div>
              <!-- Dairy Boxes -->
              <div v-if="dairyBoxes && dairyBoxes.length > 0">
                <div
                  v-for="dairyBox in dairyBoxes"
                  :key="dairyBox.id"
                  class="mb-6 rounded-lg border p-6"
                >
                  <div class="mb-4 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <button
                        class="text-lg font-semibold text-slate-900 hover:text-indigo-600"
                        @click="router.visit(route('dairy-boxes.show', dairyBox.id))"
                      >
                        {{ dairyBox.business_name || 'N/A' }}
                      </button>
                      <p class="text-muted-foreground text-sm">
                        ID: {{ dairyBox.dairy_box_id || 'N/A' }}
                      </p>
                    </div>
                    <Button
                      size="sm"
                      variant="outline"
                      class="shrink-0"
                      @click="router.visit(route('dairy-boxes.show', dairyBox.id))"
                    >
                      View
                    </Button>
                  </div>
                  <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                    <dt class="text-muted-foreground font-medium">Manager</dt>
                    <dd class="sm:text-right">{{ dairyBox.manager || 'N/A' }}</dd>
                    <dt class="text-muted-foreground font-medium">Dairy Box Focal</dt>
                    <dd class="sm:text-right">{{ dairyBox.dairy_box_focal || 'N/A' }}</dd>
                    <dt class="text-muted-foreground font-medium">Contact Number</dt>
                    <dd class="sm:text-right">{{ dairyBox.contact_number || 'N/A' }}</dd>
                    <dt class="text-muted-foreground font-medium">Status</dt>
                    <dd class="sm:text-right">
                      {{ enums.dairyBoxStatuses?.[dairyBox.status] || dairyBox.status || 'N/A' }}
                    </dd>
                    <dt class="text-muted-foreground font-medium">Launched Date</dt>
                    <dd class="sm:text-right">
                      {{ formatDate(dairyBox.launched_date) }}
                    </dd>
                  </dl>
                </div>
              </div>
              <div v-else class="text-muted-foreground py-8 text-center">No dairy boxes found.</div>
            </div>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>

    <!-- ── Members (Farmers) ── -->
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <div>
            <CardTitle>Members</CardTitle>
            <CardDescription>Farmers associated with this partner</CardDescription>
          </div>
          <!-- Export Dropdown -->
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <ButtonGroup :class="cn('text-black')" prevent-default>
                <Button
                  variant="secondary"
                  prevent-default
                  class="text-xs sm:text-sm"
                  :disabled="queueingExport"
                >
                  Export
                </Button>
                <Button variant="secondary" class="px-2" :disabled="queueingExport">
                  <DownloadIcon class="size-3.5 sm:size-4" />
                </Button>
              </ButtonGroup>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
              <DropdownMenuGroup>
                <DropdownMenuItem @click="handleExportMembers('xlsx')">
                  Download as xlsx
                </DropdownMenuItem>
                <DropdownMenuItem @click="handleExportMembers('csv')">
                  Download as csv
                </DropdownMenuItem>
                <DropdownMenuItem @click="handleExportMembers('pdf')">
                  Download as pdf
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </CardHeader>
      <CardContent>
        <div class="space-y-4">
          <div
            v-for="farmer in farmers.data"
            :key="farmer.id"
            class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
          >
            <!-- Avatar + name -->
            <div class="flex items-center space-x-4">
              <Avatar
                class="shrink-0 cursor-pointer"
                @click="router.visit(route('farmers.show', farmer.id))"
              >
                <AvatarImage
                  :src="farmer.photo_url || farmer.photo"
                  :alt="farmer.name"
                  class="object-cover"
                />
                <AvatarFallback>
                  <img
                    src="/images/farmer.png"
                    alt="Farmer Placeholder Image"
                    class="h-full w-full object-cover"
                  />
                </AvatarFallback>
              </Avatar>
              <div class="min-w-0">
                <button
                  class="text-sm font-semibold text-blue-600 hover:text-blue-800 hover:underline"
                  @click="router.visit(route('farmers.show', farmer.id))"
                >
                  {{
                    `${farmer.first_name} ${
                      farmer.middle_name ? farmer.middle_name + ' ' : ''
                    }${farmer.last_name || ''}`.trim()
                  }}
                </button>
                <p class="text-muted-foreground truncate text-sm">
                  {{ normalizedAddress(farmer.address) }}
                </p>
              </div>
            </div>
            <!-- Contact -->
            <div class="pl-14 sm:pl-0 sm:text-right">
              <p class="text-muted-foreground text-sm">Contact</p>
              <p class="text-sm">{{ farmer.contact_number || 'N/A' }}</p>
            </div>
          </div>

          <div v-if="!farmers.data?.length" class="text-muted-foreground py-8 text-center">
            No farmers found.
          </div>

          <!-- Smart pagination with ellipsis -->
          <div
            v-if="paginationPages && paginationPages.pages"
            class="flex flex-wrap items-center justify-between gap-2"
          >
            <div class="text-muted-foreground text-sm">
              Showing {{ farmers.from || 0 }} to {{ farmers.to || 0 }} of
              {{ farmers.total || 0 }} results
            </div>
            <div class="flex flex-wrap items-center gap-1">
              <!-- Prev -->
              <button
                :disabled="!paginationPages.prev?.url"
                :class="[
                  'rounded px-3 py-1 text-sm',
                  'bg-gray-100 text-gray-700 hover:bg-gray-200',
                  !paginationPages.prev?.url && 'cursor-not-allowed opacity-50',
                ]"
                @click="
                  paginationPages.prev?.url &&
                  router.visit(paginationPages.prev.url, { preserveScroll: true })
                "
              >
                &laquo;
              </button>

              <!-- Page numbers with ellipsis -->
              <template v-for="(link, index) in paginationPages.pages" :key="index">
                <span v-if="link.ellipsis" class="px-1 text-sm text-gray-400 select-none">…</span>
                <button
                  v-else
                  :disabled="!link.url"
                  :class="[
                    'min-w-[2rem] rounded px-2 py-1 text-sm',
                    link.active
                      ? 'bg-blue-600 text-white'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                    !link.url && 'cursor-not-allowed opacity-50',
                  ]"
                  @click="link.url && router.visit(link.url, { preserveScroll: true })"
                  v-html="link.label"
                />
              </template>

              <!-- Next -->
              <button
                :disabled="!paginationPages.next?.url"
                :class="[
                  'rounded px-3 py-1 text-sm',
                  'bg-gray-100 text-gray-700 hover:bg-gray-200',
                  !paginationPages.next?.url && 'cursor-not-allowed opacity-50',
                ]"
                @click="
                  paginationPages.next?.url &&
                  router.visit(paginationPages.next.url, { preserveScroll: true })
                "
              >
                &raquo;
              </button>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- ── Products ── -->
    <Card>
      <CardHeader>
        <CardTitle>Products</CardTitle>
        <CardDescription>Processed or market items</CardDescription>
      </CardHeader>
      <CardContent>
        <Tabs default-value="milk" :class="cn('bordered')">
          <!-- 3 cols on mobile → 5 on sm+ -->
          <TabsList class="grid h-auto w-full grid-cols-3 gap-1 sm:grid-cols-5">
            <TabsTrigger value="milk" class="text-xs sm:text-sm">Milk</TabsTrigger>
            <TabsTrigger value="meat" class="text-xs sm:text-sm">Meat</TabsTrigger>
            <TabsTrigger value="manure" class="text-xs sm:text-sm">Manure</TabsTrigger>
            <TabsTrigger value="hide" class="text-xs sm:text-sm">Hide</TabsTrigger>
            <TabsTrigger value="others" class="text-xs sm:text-sm">Others</TabsTrigger>
          </TabsList>

          <!-- Shared product tab template -->
          <template
            v-for="tab in [
              { value: 'milk', filter: p => p.category === 'milk' },
              { value: 'meat', filter: p => p.category === 'meat' },
              { value: 'manure', filter: p => p.category === 'manure' },
              { value: 'hide', filter: p => p.category === 'hide' },
              {
                value: 'others',
                filter: p => !['milk', 'meat', 'manure', 'hide'].includes(p.category),
              },
            ]"
            :key="tab.value"
          >
            <TabsContent :value="tab.value">
              <div class="overflow-x-auto">
                <Table class="min-w-[480px]">
                  <TableHeader>
                    <TableRow>
                      <TableHead>Product Name</TableHead>
                      <TableHead>Product Registration</TableHead>
                      <TableHead>Size</TableHead>
                      <TableHead>Selling Price</TableHead>
                      <TableHead>Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-for="product in products.filter(tab.filter)" :key="product.id">
                      <TableCell>{{ productNameLabel(product, enums) }}</TableCell>
                      <TableCell>{{ product.registration_number || 'N/A' }}</TableCell>
                      <TableCell>{{ product.size }}</TableCell>
                      <TableCell>{{ product.cost_per_qty }}</TableCell>
                      <TableCell>
                        <Button
                          size="sm"
                          variant="outline"
                          @click="openEditModal('products', product.id)"
                        >
                          Edit
                        </Button>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>
            </TabsContent>
          </template>
        </Tabs>
      </CardContent>
    </Card>

    <!-- ── Documents ── -->
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <div>
            <CardTitle>Documents</CardTitle>
            <CardDescription>Uploaded files related to the partner</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <div class="overflow-x-auto">
          <Table class="min-w-[560px]">
            <TableHeader>
              <TableRow>
                <TableHead>Document Type</TableHead>
                <TableHead>Registration Number</TableHead>
                <TableHead>File Type</TableHead>
                <TableHead>Uploaded By</TableHead>
                <TableHead>Uploaded At</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="document in documents" :key="document.id">
                <TableCell>
                  <span v-if="document.tags && document.tags.length > 0">
                    {{
                      enums.document[document.tags.find(t => t.key === 'document')?.value] ||
                      document.tags.find(t => t.key === 'document')?.value ||
                      '-'
                    }}
                  </span>
                  <span v-else>-</span>
                </TableCell>
                <TableCell>
                  <button
                    class="flex items-center space-x-1 text-blue-600 hover:underline"
                    @click="openDocumentModal(document)"
                  >
                    {{ document.registration_number || '-' }}
                  </button>
                </TableCell>
                <TableCell>{{ document.file?.type || '-' }}</TableCell>
                <TableCell>{{
                  document.file?.uploadedBy?.name || document.file?.uploaded_by?.name || '-'
                }}</TableCell>
                <TableCell>{{
                  document.file?.created_at
                    ? new Date(document.file.created_at).toLocaleDateString()
                    : '-'
                }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>

    <PartnerModalForm :partner="partner" :products="products" :enums="enums" />

    <!-- ── Document Modal ── -->
    <Dialog v-model:open="showDocumentModal">
      <DialogContent
        class="flex max-h-[95dvh] w-full flex-col overflow-hidden rounded-t-2xl sm:max-h-[90vh] sm:max-w-4xl sm:rounded-lg"
      >
        <DialogHeader class="shrink-0">
          <DialogTitle>{{ selectedDocument?.title }}</DialogTitle>
          <DialogDescription>File preview and download options</DialogDescription>
        </DialogHeader>

        <div v-if="selectedDocument" class="flex min-h-0 flex-1 flex-col space-y-4 overflow-y-auto">
          <!-- File Preview -->
          <div class="overflow-hidden rounded-lg border" style="height: 50dvh; min-height: 200px">
            <embed
              v-if="selectedDocument.file?.type?.includes('pdf')"
              :src="`/${selectedDocument.file.path}#toolbar=0&navpanes=0&scrollbar=0`"
              type="application/pdf"
              class="h-full w-full"
            />
            <div
              v-else-if="selectedDocument.file?.type?.includes('image')"
              class="flex h-full w-full items-center justify-center bg-gray-50"
            >
              <img
                :src="`/${selectedDocument.file.path}`"
                :alt="selectedDocument.title"
                class="max-h-full max-w-full object-contain"
              />
            </div>
            <div v-else class="flex h-full w-full items-center justify-center bg-gray-50">
              <div class="text-center">
                <p class="mb-2 text-gray-500">Preview not available for this file type</p>
                <p class="text-sm text-gray-400">{{ selectedDocument.file?.type }}</p>
              </div>
            </div>
          </div>

          <!-- File Info -->
          <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 sm:gap-4">
            <div>
              <span class="font-semibold">File Type:</span>
              <span class="ml-2">{{ selectedDocument.file?.type }}</span>
            </div>
            <div>
              <span class="font-semibold">File Size:</span>
              <span class="ml-2">
                {{
                  selectedDocument.file?.size
                    ? (selectedDocument.file.size / 1024 / 1024).toFixed(2) + ' MB'
                    : 'Unknown'
                }}
              </span>
            </div>
            <div>
              <span class="font-semibold">Document Type:</span>
              <span class="ml-2">
                {{
                  enums.document[selectedDocument.tags?.find(t => t.key === 'document')?.value] ||
                  selectedDocument.tags?.find(t => t.key === 'document')?.value ||
                  '-'
                }}
              </span>
            </div>
            <div>
              <span class="font-semibold">Uploaded By:</span>
              <span class="ml-2">{{ selectedDocument.file?.uploaded_by?.name }}</span>
            </div>
          </div>
        </div>

        <DialogFooter class="shrink-0 flex-col gap-2 sm:flex-row sm:gap-0">
          <Button
            v-if="selectedDocument"
            class="w-full bg-blue-600 hover:bg-blue-700 sm:w-auto"
            @click="downloadDocument(selectedDocument)"
          >
            Download Document
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
