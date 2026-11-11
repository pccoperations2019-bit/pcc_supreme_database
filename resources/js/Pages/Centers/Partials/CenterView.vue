<script setup>
import { computed, defineProps, ref } from 'vue'
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { cn, label, productNameLabel } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { router, usePage } from '@inertiajs/vue3'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogClose,
} from '@/components/ui/dialog'
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
import { toast } from 'vue-sonner'
import { normalizeAnimalInventory } from '@/composable/useFarmerForm'

const { center, products, enums, documents, partners, user_center_id, projects } = defineProps({
  center: { type: Object, required: true },
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
  partners: {
    type: Object,
    required: true,
  },
  user_center_id: {
    type: Number,
    default: null,
  },
  projects: {
    type: Object,
    default: () => ({}),
  },
})

const page = usePage()
const csrfToken = page.props.csrf_token
const queueingExport = ref(false)
const centerLabel = computed(() => {
  if (!center.code) return 'N/A'
  return enums?.center?.[center.code] || center.name
})

const normalizedAddress = address => {
  if (!address) return 'N/A'
  const { street, barangay, city, province, region_label, region, zip_code, country } = address
  return [street, barangay, city, province, region_label || region, zip_code, country]
    .filter(Boolean)
    .join(', ')
}

// Modal functionality
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

const viewDocument = doc => {
  window.open(`/${doc.file.path}`, '_blank')
}

const paginationPages = computed(() => {
  if (!partners.links || partners.links.length <= 3) return []

  const pageLinks = partners.links.filter(l => {
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

  const prev = partners.links[0]
  const next = partners.links[partners.links.length - 1]
  return { prev, next, pages: result }
})

const handleExportPartners = async format => {
  if (queueingExport.value) return
  queueingExport.value = true

  try {
    const response = await axios.post(`/centers/${center.id}/partners/export`, {
      format: format,
      queue_export: true,
    })

    window.dispatchEvent(
      new window.CustomEvent('report-export-queued', {
        detail: {
          documentId: response.data.document_id,
          reportType: 'center-partners',
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
    toast.error('Unable to queue export right now.')
  } finally {
    queueingExport.value = false
  }
}

const animalInventoryRows = computed(() => normalizeAnimalInventory(center.animal_inventory))
const animalInventoryTotal = computed(() =>
  animalInventoryRows.value.reduce((sum, row) => sum + (Number(row.count) || 0), 0)
)
</script>

<template>
  <div
    class="sticky top-0 z-20 mb-3 flex flex-wrap items-start justify-between gap-3 rounded-lg border-b bg-white px-4 py-4"
  >
    <div class="min-w-0 flex-1">
      <h1 class="truncate text-2xl font-bold text-gray-700 sm:text-3xl">{{ centerLabel }}</h1>
      <div class="mt-1 flex flex-wrap gap-1">
        <Badge v-if="center.category">{{ center.category }}</Badge>
        <Badge v-if="center.type">{{ center.type }}</Badge>
      </div>
    </div>
    <Button
      v-if="!user_center_id || center.id === user_center_id"
      variant="outline"
      class="shrink-0 cursor-pointer text-slate-900 hover:text-slate-900"
      @click="router.visit(route('centers.edit', center.id))"
    >
      Edit
    </Button>
  </div>

  <div class="flex flex-col space-y-4">
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
      <div class="flex flex-col rounded-lg bg-gray-100 p-4 lg:col-span-1">
        <Avatar :class="cn('m-auto aspect-square h-auto w-full max-w-[280px] md:max-w-[18rem]')">
          <AvatarImage
            :src="center.logo_url || center.logo"
            alt="Center Logo"
            class="object-cover"
          />
          <AvatarFallback>
            <img
              src="/images/centers.png"
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
            <dd class="sm:text-right">{{ center.code }}</dd>
            <dt class="text-muted-foreground font-medium">Address</dt>
            <dd class="break-words sm:text-right">{{ normalizedAddress(center.address) }}</dd>
            <dt class="text-muted-foreground font-medium">Website</dt>
            <dd class="break-all sm:text-right">{{ center.website || 'N/A' }}</dd>
          </dl>
          <!-- Contact grid — 2 cols on sm, 4 on lg -->
          <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
            <dt class="text-muted-foreground font-medium">Date of Establishment</dt>
            <dd class="sm:text-right">
              {{ center.metadata?.date_of_establishment || center.date_of_establishment || 'N/A' }}
            </dd>
            <dt class="text-muted-foreground font-medium">Date Assisted</dt>
            <dd class="sm:text-right">
              {{
                (center.date_assisted
                  ? String(center.date_assisted).substring(0, 10)
                  : center.metadata?.date_assisted) || 'N/A'
              }}
            </dd>
            <dt class="text-muted-foreground font-medium">Contact Person</dt>
            <dd class="sm:text-right">{{ center.contact_person || 'N/A' }}</dd>
            <dt class="text-muted-foreground font-medium">Position</dt>
            <dd class="sm:text-right">{{ center.position || 'N/A' }}</dd>
            <dt class="text-muted-foreground font-medium">Contact Number</dt>
            <dd class="sm:text-right">{{ center.contact_number || 'N/A' }}</dd>
            <dt class="text-muted-foreground font-medium">Telephone Number</dt>
            <dd class="sm:text-right">{{ center.telephone_number || 'N/A' }}</dd>
            <dt class="text-muted-foreground font-medium">Email Address</dt>
            <dd class="break-all sm:text-right">{{ center.email_address || 'N/A' }}</dd>
            <dt class="text-muted-foreground font-medium">Projects</dt>
            <dd class="sm:text-right">
              <span
                v-if="center.projects && center.projects.length"
                class="flex flex-wrap justify-end gap-1"
              >
                <span
                  v-for="p in center.projects"
                  :key="p.id"
                  class="bg-muted rounded px-2 py-0.5 text-xs"
                  >{{ projects[p.id] || p.program }}</span
                >
              </span>
              <span v-else>N/A</span>
            </dd>
          </dl>
        </CardContent>
      </Card>
    </div>

    <Card>
      <CardHeader>
        <CardTitle>Value Chain Units</CardTitle>
        <CardDescription>Contributors in the chain</CardDescription>
      </CardHeader>
      <CardContent>
        <Tabs default-value="production" :class="cn('bordered')">
          <TabsList class="grid h-auto w-full grid-cols-2 gap-1 sm:grid-cols-4">
            <TabsTrigger value="production" class="text-xs sm:text-sm">Production</TabsTrigger>
            <TabsTrigger value="consolidation" class="text-xs sm:text-sm"
              >Consolidation</TabsTrigger
            >
            <TabsTrigger value="processing" class="text-xs sm:text-sm">Processing</TabsTrigger>
            <TabsTrigger value="marketing" class="text-xs sm:text-sm">Marketing</TabsTrigger>
          </TabsList>

          <TabsContent value="production">
            <div class="space-y-4">
              <div
                v-for="production in center.production_units"
                :key="production.id"
                class="rounded-lg border p-4"
              >
                <div class="mb-4">
                  <h4 class="text-lg font-semibold">{{ production.name }}</h4>
                  <p class="text-muted-foreground text-sm">
                    {{ label(enums.productionCategory, production.category) }} •
                    {{ label(enums.herdType, production.herd_type) }} • Established:
                    {{ production.date_of_establishment }}
                  </p>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <dt class="text-muted-foreground font-medium">Contact Person</dt>
                  <dd class="sm:text-right">{{ production.contact_person || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Position</dt>
                  <dd class="sm:text-right">{{ production.position || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="sm:text-right">{{ production.contact_number || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Telephone Number</dt>
                  <dd class="sm:text-right">{{ production.telephone_number || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Email Address</dt>
                  <dd class="break-all sm:text-right">{{ production.email_address || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Website</dt>
                  <dd class="break-all sm:text-right">{{ production.website || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>
                  <dd class="sm:text-right">{{ production.number_of_staff_male || '0' }}</dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>
                  <dd class="sm:text-right">{{ production.number_of_staff_female || '0' }}</dd>
                  <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>
                  <dd class="sm:text-right">{{ production.avg_salary_of_staff || '0.00' }}</dd>
                </dl>
              </div>
              <div
                v-if="!center.production_units?.length"
                class="text-muted-foreground py-8 text-center"
              >
                No production units found.
              </div>
            </div>
          </TabsContent>

          <TabsContent value="consolidation">
            <div class="space-y-4">
              <div
                v-for="consolidation in center.consolidation_units"
                :key="consolidation.id"
                class="rounded-lg border p-4"
              >
                <div class="mb-4">
                  <h4 class="text-lg font-semibold">{{ consolidation.name }}</h4>
                  <p class="text-muted-foreground text-sm">
                    {{ label(enums.consolidationCategory, consolidation.category) }} • Established:
                    {{ consolidation.date_of_establishment }}
                  </p>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <dt class="text-muted-foreground font-medium">Contact Person</dt>
                  <dd class="sm:text-right">{{ consolidation.contact_person || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Position</dt>
                  <dd class="sm:text-right">{{ consolidation.position || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="sm:text-right">{{ consolidation.contact_number || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Telephone Number</dt>
                  <dd class="sm:text-right">{{ consolidation.telephone_number || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Email Address</dt>
                  <dd class="break-all sm:text-right">
                    {{ consolidation.email_address || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Website</dt>
                  <dd class="break-all sm:text-right">{{ consolidation.website || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>
                  <dd class="sm:text-right">{{ consolidation.number_of_staff_male || '0' }}</dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>
                  <dd class="sm:text-right">{{ consolidation.number_of_staff_female || '0' }}</dd>
                  <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>
                  <dd class="sm:text-right">{{ consolidation.avg_salary_of_staff || '0.00' }}</dd>
                </dl>
              </div>
              <div
                v-if="!center.consolidation_units?.length"
                class="text-muted-foreground py-8 text-center"
              >
                No consolidation units found.
              </div>
            </div>
          </TabsContent>

          <TabsContent value="processing">
            <div class="space-y-4">
              <div
                v-for="processing in center.processing_units"
                :key="processing.id"
                class="rounded-lg border p-4"
              >
                <div class="mb-4">
                  <h4 class="text-lg font-semibold">{{ processing.name }}</h4>
                  <p class="text-muted-foreground text-sm">
                    {{ label(enums.processingCategory, processing.category) }} • Established:
                    {{ processing.date_of_establishment }}
                  </p>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <dt class="text-muted-foreground font-medium">Contact Person</dt>
                  <dd class="sm:text-right">{{ processing.contact_person || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Position</dt>
                  <dd class="sm:text-right">{{ processing.position || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="sm:text-right">{{ processing.contact_number || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Telephone Number</dt>
                  <dd class="sm:text-right">{{ processing.telephone_number || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Email Address</dt>
                  <dd class="break-all sm:text-right">{{ processing.email_address || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Website</dt>
                  <dd class="break-all sm:text-right">{{ processing.website || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>
                  <dd class="sm:text-right">{{ processing.number_of_staff_male || '0' }}</dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>
                  <dd class="sm:text-right">{{ processing.number_of_staff_female || '0' }}</dd>
                  <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>
                  <dd class="sm:text-right">{{ processing.avg_salary_of_staff || '0.00' }}</dd>
                </dl>
              </div>
              <div
                v-if="!center.processing_units?.length"
                class="text-muted-foreground py-8 text-center"
              >
                No processing units found.
              </div>
            </div>
          </TabsContent>

          <!-- marketing -->
          <TabsContent value="marketing">
            <div class="space-y-4">
              <div
                v-for="marketing in center.marketing_units"
                :key="marketing.id"
                class="rounded-lg border p-4"
              >
                <div class="mb-4">
                  <h4 class="text-lg font-semibold">{{ marketing.name }}</h4>
                  <p class="text-muted-foreground text-sm">
                    Established: {{ marketing.date_of_establishment }}
                  </p>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <dt class="text-muted-foreground font-medium">Contact Person</dt>
                  <dd class="sm:text-right">{{ marketing.contact_person || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Position</dt>
                  <dd class="sm:text-right">{{ marketing.position || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="sm:text-right">{{ marketing.contact_number || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Telephone Number</dt>
                  <dd class="sm:text-right">{{ marketing.telephone_number || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Email Address</dt>
                  <dd class="break-all sm:text-right">{{ marketing.email_address || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Website</dt>
                  <dd class="break-all sm:text-right">{{ marketing.website || 'N/A' }}</dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>
                  <dd class="sm:text-right">{{ marketing.number_of_staff_male || '0' }}</dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>
                  <dd class="sm:text-right">{{ marketing.number_of_staff_female || '0' }}</dd>
                  <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>
                  <dd class="sm:text-right">{{ marketing.avg_salary_of_staff || '0.00' }}</dd>
                </dl>
              </div>
              <div
                v-if="!center.marketing_units?.length"
                class="text-muted-foreground py-8 text-center"
              >
                No marketing units found.
              </div>
            </div>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <div>
            <CardTitle>Partners</CardTitle>
            <CardDescription>Partners associated with this center</CardDescription>
          </div>
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
                <DropdownMenuItem @click="handleExportPartners('xlsx')">
                  Download as xlsx
                </DropdownMenuItem>
                <DropdownMenuItem @click="handleExportPartners('csv')">
                  Download as csv
                </DropdownMenuItem>
                <DropdownMenuItem @click="handleExportPartners('pdf')">
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
            v-for="partner in partners.data"
            :key="partner.id"
            class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="flex items-center space-x-4">
              <Avatar
                class="shrink-0 cursor-pointer"
                @click="router.visit(route('partners.show', partner.id))"
              >
                <AvatarImage
                  :src="partner.logo_url || partner.logo"
                  :alt="partner.name"
                  class="object-cover"
                />
                <AvatarFallback>
                  <img
                    src="/images/partners.png"
                    alt="Partner Placeholder Image"
                    class="h-full w-full object-cover"
                  />
                </AvatarFallback>
              </Avatar>
              <div class="min-w-0">
                <button
                  class="text-sm font-semibold text-blue-600 hover:text-blue-800 hover:underline"
                  @click="router.visit(route('partners.show', partner.id))"
                >
                  {{ partner.name }}
                </button>
                <p class="text-muted-foreground truncate text-sm">
                  {{ normalizedAddress(partner.address) }}
                </p>
              </div>
            </div>
            <!-- Contact -->
            <div class="pl-14 sm:pl-0 sm:text-right">
              <p class="text-muted-foreground text-sm">Contact</p>
              <p class="text-sm">{{ partner.contact_number || 'N/A' }}</p>
            </div>
          </div>

          <div v-if="!partners.data?.length" class="text-muted-foreground py-8 text-center">
            No partners found.
          </div>

          <!-- Smart pagination with ellipsis -->
          <div
            v-if="paginationPages && paginationPages.pages"
            class="flex flex-wrap items-center justify-between gap-2"
          >
            <div class="text-muted-foreground text-sm">
              Showing {{ partners.from || 0 }} to {{ partners.to || 0 }} of
              {{ partners.total || 0 }} results
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

    <!-- ── Animal Inventory ── -->
    <Card>
      <CardHeader>
        <CardTitle>Animal Inventory</CardTitle>
        <CardDescription>Count of animals per classification</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="overflow-x-auto rounded-lg border">
          <Table class="min-w-[720px]">
            <TableHeader>
              <TableRow class="bg-muted/50">
                <TableHead class="w-[240px]">Classification</TableHead>
                <TableHead class="w-[120px]">Count</TableHead>
                <TableHead class="w-[140px]">Breed</TableHead>
                <TableHead>Remarks</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="row in animalInventoryRows" :key="row.classification">
                <TableCell class="font-medium">{{ row.classification }}</TableCell>
                <TableCell>{{
                  row.count === '' || row.count === null ? '-' : row.count
                }}</TableCell>
                <TableCell>{{ row.breed || '-' }}</TableCell>
                <TableCell>{{ row.remarks || '-' }}</TableCell>
              </TableRow>
            </TableBody>
            <TableFooter>
              <TableRow class="bg-muted/50">
                <TableCell class="font-semibold">TOTAL</TableCell>
                <TableCell class="font-semibold">{{ animalInventoryTotal }}</TableCell>
                <TableCell />
                <TableCell />
              </TableRow>
            </TableFooter>
          </Table>
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
          <!-- 3 cols on mobile → 5 on sm+ so all product tabs are always readable -->
          <TabsList class="grid h-auto w-full grid-cols-3 gap-1 sm:grid-cols-5">
            <TabsTrigger value="milk" class="text-xs sm:text-sm">Milk</TabsTrigger>
            <TabsTrigger value="meat" class="text-xs sm:text-sm">Meat</TabsTrigger>
            <TabsTrigger value="manure" class="text-xs sm:text-sm">Manure</TabsTrigger>
            <TabsTrigger value="hide" class="text-xs sm:text-sm">Hide</TabsTrigger>
            <TabsTrigger value="others" class="text-xs sm:text-sm">Others</TabsTrigger>
          </TabsList>

          <!-- Shared product table — wrapped for horizontal scroll on mobile -->
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
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-for="product in products.filter(tab.filter)" :key="product.id">
                      <TableCell>{{ productNameLabel(product, enums) }}</TableCell>
                      <TableCell>{{ product.registration_number || 'N/A' }}</TableCell>
                      <TableCell>{{ product.size }}</TableCell>
                      <TableCell>{{ product.cost_per_qty }}</TableCell>
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
            <CardDescription>Uploaded files related to the center</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <!-- Horizontal scroll wrapper so table never breaks layout -->
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
                <TableCell>{{ document.file.type }}</TableCell>
                <TableCell>{{ document.file.uploaded_by.name }}</TableCell>
                <TableCell>{{ new Date(document.file.created_at).toLocaleDateString() }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
  </div>

  <!-- ── Document Modal ── -->
  <Dialog v-model:open="showDocumentModal">
    <!--
      On mobile: full-width, nearly full-height.
      On larger screens: original max-w-4xl behaviour.
    -->
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

        <!-- File Info — stacks on mobile, 2-col on sm+ -->
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

      <!-- Footer buttons — full-width on mobile, auto on sm+ -->
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
</template>
