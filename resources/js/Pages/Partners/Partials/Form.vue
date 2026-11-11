<script setup>
import { computed, ref, inject, watch, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import { Pencil, Trash2 } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { cn, label, productNameLabel } from '@/lib/utils'
import { useAuth } from '@/composable/useAuth'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
import SearchableSelect from '@/components/SearchableSelect.vue'
import { usePage } from '@inertiajs/vue3'
import { toastFlash } from '@/lib/flashToasts'
import { usePsgcAddress } from '@/composable/usePsgcAddress'
import { usePsgcCascade } from '@/composable/usePsgcCascade'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogClose,
} from '@/components/ui/dialog'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['view', 'edit', 'create'].includes(value),
  },
  partner: { type: Object, required: true },
  products: { type: Array, default: () => [] },
  productRegistrationDocuments: { type: Object, default: () => ({}) },
  enums: { type: Object, default: () => ({}) },
  documents: { type: Array, default: () => [] },
  centers: { type: Array, default: () => [] },
  projects: { type: Object, default: () => ({}) },
  dairyBoxes: { type: Array, default: () => [] },
  defaultCenterId: { type: Number, default: null },
  disableCenterField: { type: Boolean, default: false },
  class: { type: String, default: '' },
})

const modalState = inject('modalState', null)
const createDrafts = inject('createDrafts', null)
const fieldErrors = ref({})
const submitting = ref(false)
const isViewMode = computed(() => props.mode === 'view')
const isEditMode = computed(() => props.mode === 'edit')
const isCreateMode = computed(() => props.mode === 'create')

const isFormMode = computed(() => isEditMode.value || isCreateMode.value)

const useDrafts = computed(() => isCreateMode.value && createDrafts)
const displayedProductionUnits = computed(() =>
  useDrafts.value ? createDrafts.productionUnits : props.partner.production_units || []
)
const displayedConsolidationUnits = computed(() =>
  useDrafts.value ? createDrafts.consolidationUnits : props.partner.consolidation_units || []
)
const displayedProcessingUnits = computed(() =>
  useDrafts.value ? createDrafts.processingUnits : props.partner.processing_units || []
)
const displayedMarketingUnits = computed(() =>
  useDrafts.value ? createDrafts.marketingUnits : props.partner.marketing_units || []
)
const displayedProducts = computed(() =>
  useDrafts.value ? createDrafts.products : props.products || []
)
const displayedDocuments = computed(() =>
  useDrafts.value ? createDrafts.documents : props.documents || []
)

const psgc = usePsgcAddress()

const {
  regions: regionsData,
  provinces: addressProvinces,
  cities: addressCities,
  barangays: addressBarangays,
  load: loadPsgc,
  getRegionNameByCode,
  getProvinceNameByCode,
  getCityNameByCode,
  getBarangayNameByCode,
} = psgc

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
        options: props.enums?.assistanceStatuses || {},
      },
      { id: 'date_assisted', label: 'Date Assisted', type: 'date' },
      {
        id: 'partner_businesstype',
        label: 'Business Type',
        type: 'select',
        options: props.enums?.categories || {},
      },
      {
        id: 'registrationtype',
        label: 'Registration Type',
        type: 'select',
        options: props.enums?.registrationTypes || {},
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
      { id: 'contact_person_contact_no', label: 'Contact Person Contact No.', type: 'tel' },
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
      { id: 'function_in_the_value_chain', label: 'Function in the Value Chain', type: 'text' },
      { id: 'production', label: 'Production', type: 'select', options: { yes: 'Yes', no: 'No' } },
      { id: 'processing', label: 'Processing', type: 'select', options: { yes: 'Yes', no: 'No' } },
      { id: 'marketing', label: 'Marketing', type: 'select', options: { yes: 'Yes', no: 'No' } },
      {
        id: 'engagedmilkfeeding',
        label: 'Engaged in Milk Feeding',
        type: 'select',
        options: { yes: 'Yes', no: 'No' },
      },
    ],
  },
]

const metadataFieldIds = metadataSections.flatMap(section => section.fields.map(field => field.id))

const form = useForm({
  center_id: props.partner.center_id || props.defaultCenterId || '',
  code: props.partner.code || '',
  name: props.partner.name || '',
  status: props.partner.status || '',
  website: props.partner.website || '',
  date_established: props.partner.metadata?.date_established || '',
  contact_person: props.partner.contact_name || '',
  position: props.partner.metadata?.position || '',
  contact_number: props.partner.mobile_number || '',
  telephone_number: props.partner.telephone_number || '',
  email_address: props.partner.contact_email || '',
  category: props.partner.category || '',
  partnership_type: props.partner.partnership_type || '',
  project_ids: (props.partner.projects || []).map(p => p.id),
  total_composition: props.partner.total_composition || 0,
  male_count: props.partner.male_count || 0,
  female_count: props.partner.female_count || 0,
  avg_monthly_income: props.partner.avg_monthly_income || 0,
  logo: null,
  street: props.partner.address?.street || '',
  barangay: '',
  city: '',
  province: '',
  region: '',
  district: props.partner.address?.district || '',
  zip_code: props.partner.address?.zip_code || '',
  country: props.partner.address?.country || 'Philippines',
  latitude: props.partner.address?.latitude || '',
  longitude: props.partner.address?.longitude || '',
  ...Object.fromEntries(metadataFieldIds.map(id => [id, props.partner.metadata?.[id] ?? ''])),
})

const { hydrateAddressFields, addressRegionHasProvinces, districtDisabled, districts } =
  usePsgcCascade({ form, psgc, withDistrict: true })

watch(
  () => props.partner,
  async newPartner => {
    form.center_id = newPartner.center_id || props.defaultCenterId || ''
    form.code = newPartner.code || ''
    form.name = newPartner.name || ''
    form.status = newPartner.status || ''
    form.website = newPartner.website || ''
    form.date_established = newPartner.metadata?.date_established || ''
    form.contact_person = newPartner.contact_name || ''
    form.position = newPartner.metadata?.position || ''
    form.contact_number = newPartner.mobile_number || ''
    form.telephone_number = newPartner.telephone_number || ''
    form.email_address = newPartner.contact_email || ''
    form.category = newPartner.category || ''
    form.partnership_type = newPartner.partnership_type || ''
    form.project_ids = (newPartner.projects || []).map(p => p.id)
    form.total_composition = newPartner.total_composition || 0
    form.male_count = newPartner.male_count || 0
    form.female_count = newPartner.female_count || 0
    form.avg_monthly_income = newPartner.avg_monthly_income || 0
    form.street = newPartner.address?.street || ''
    await hydrateAddressFields(newPartner)
    form.zip_code = newPartner.address?.zip_code || ''
    form.country = newPartner.address?.country || 'Philippines'
    form.latitude = newPartner.address?.latitude || ''
    form.longitude = newPartner.address?.longitude || ''
    metadataFieldIds.forEach(id => {
      form[id] = newPartner.metadata?.[id] ?? ''
    })
    logoPreview.value = newPartner.logo_url || null
    logoFile.value = null
  },
  { deep: true }
)

const clearError = id => {
  if (fieldErrors.value?.[id]) {
    const copy = { ...fieldErrors.value }
    delete copy[id]
    fieldErrors.value = copy
  }
}

onMounted(async () => {
  await loadPsgc()
  await hydrateAddressFields(props.partner)
})

const logoFile = ref(null)
const logoPreview = ref(props.partner.logo_url || null)
const page = usePage()
const csrfToken = page.props.csrf_token

const showUploadModal = ref(false)
const uploadForm = ref({
  document_type: '',
  file: null,
  description: '',
  registration_number: '',
  validity: '',
  compliance_status: '',
})
const uploading = ref(false)
const uploadErrors = ref({ file: '', document_type: '' })

const showDocumentModal = ref(false)
const selectedDocument = ref(null)

const showEditDocumentModal = ref(false)
const editDocumentForm = ref(null)
const editDocumentFile = ref(null)

const normalizedAddress = address => {
  if (!address) return 'N/A'
  const { street, barangay, city, province, region, zip_code, country } = address
  return [street, barangay, city, province, region, zip_code, country].filter(Boolean).join(', ')
}

const openDocumentModal = document => {
  selectedDocument.value = document
  showDocumentModal.value = true
}

const openEditDocumentModal = document => {
  const documentType = document.tags?.find(t => t.key === 'document')?.value || ''
  editDocumentForm.value = {
    id: document.id,
    title: document.title,
    description: document.description || '',
    registration_number: document.registration_number || '',
    validity: document.validity || '',
    compliance_status: document.compliance_status || '',
    document_type: documentType,
  }
  editDocumentFile.value = null
  showEditDocumentModal.value = true
}

const handleEditDocumentFileChange = event => {
  editDocumentFile.value = event.target.files[0]
}

const viewDocument = doc => {
  if (doc?.file?.path) window.open(`/${doc.file.path}`, '_blank')
}

const downloadDocument = async doc => {
  try {
    if (!doc?.file?.path) return
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
    viewDocument(doc)
  }
}

const handleDocumentFileChange = event => {
  uploadForm.value.file = event.target.files[0]
  if (uploadForm.value.file) uploadErrors.value.file = ''
}

watch(
  () => uploadForm.value.document_type,
  newVal => {
    if (newVal) uploadErrors.value.document_type = ''
  }
)

const resetUploadForm = () => {
  uploadForm.value = {
    document_type: '',
    file: null,
    description: '',
    registration_number: '',
    validity: '',
    compliance_status: '',
  }
  uploadErrors.value = { file: '', document_type: '' }
  showUploadModal.value = false
}

const uploadDocument = async () => {
  if (isCreateMode.value) {
    await stageDraftDocument()
    return
  }
  if (!props.partner?.id) {
    toastFlash({ error: 'Please save the partner first before uploading documents.' })
    return
  }
  uploadErrors.value = { file: '', document_type: '' }
  let hasError = false
  if (!uploadForm.value.file) {
    uploadErrors.value.file = 'File is required.'
    hasError = true
  }
  if (!uploadForm.value.document_type) {
    uploadErrors.value.document_type = 'Document Type is required.'
    hasError = true
  }
  if (hasError) return
  uploading.value = true
  try {
    const presignedResponse = await fetch('/xhr/files-presigned-url', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({
        filename: uploadForm.value.file.name,
        contentType: uploadForm.value.file.type,
      }),
    })
    if (!presignedResponse.ok)
      throw new Error(`Failed to get presigned URL: ${presignedResponse.status}`)
    const presignedData = await presignedResponse.json()
    const uploadResponse = await fetch(presignedData.url, {
      method: 'PUT',
      headers: { 'Content-Type': uploadForm.value.file.type },
      body: uploadForm.value.file,
    })
    if (!uploadResponse.ok) throw new Error(`File upload failed: ${uploadResponse.status}`)
    const formData = new FormData()
    formData.append('title', uploadForm.value.file.name)
    formData.append('file_name', uploadForm.value.file.name)
    formData.append('file_key', presignedData.key)
    formData.append('file_type', uploadForm.value.file.type)
    formData.append('file_size', uploadForm.value.file.size)
    formData.append('description', uploadForm.value.description)
    formData.append('registration_number', uploadForm.value.registration_number)
    formData.append('validity', uploadForm.value.validity)
    formData.append('compliance_status', uploadForm.value.compliance_status)
    const tags = [
      { key: 'partner', value: String(props.partner.id) },
      { key: 'document', value: uploadForm.value.document_type },
    ]
    tags.forEach((tag, index) => {
      formData.append(`tags[${index}][key]`, tag.key)
      formData.append(`tags[${index}][value]`, tag.value)
    })
    const documentResponse = await fetch('/documents', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
      body: formData,
    })
    if (!documentResponse.ok) {
      const responseBody = await documentResponse.json().catch(() => null)
      const validationMessages = Object.values(responseBody?.errors || {}).flat()
      const message =
        responseBody?.message ||
        responseBody?.error ||
        validationMessages.join(' ') ||
        `Failed to create document: ${documentResponse.status}`
      throw new Error(message)
    }
    toastFlash({ success: 'Document uploaded successfully.' })
    router.reload()
    resetUploadForm()
  } catch (error) {
    toastFlash({ error: `Upload failed: ${error?.message ?? 'Unknown error'}` })
  } finally {
    uploading.value = false
  }
}

const stageDraftDocument = async () => {
  uploadErrors.value = { file: '', document_type: '' }
  let hasDraftError = false
  if (!uploadForm.value.file) {
    uploadErrors.value.file = 'File is required.'
    hasDraftError = true
  }
  if (!uploadForm.value.document_type) {
    uploadErrors.value.document_type = 'Document Type is required.'
    hasDraftError = true
  }
  if (hasDraftError) return
  uploading.value = true
  try {
    const presignedResponse = await fetch('/xhr/files-presigned-url', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({
        filename: uploadForm.value.file.name,
        contentType: uploadForm.value.file.type,
      }),
    })
    if (!presignedResponse.ok)
      throw new Error(`Failed to get presigned URL: ${presignedResponse.status}`)
    const presignedData = await presignedResponse.json()
    const uploadResponse = await fetch(presignedData.url, {
      method: 'PUT',
      headers: { 'Content-Type': uploadForm.value.file.type },
      body: uploadForm.value.file,
    })
    if (!uploadResponse.ok) throw new Error(`File upload failed: ${uploadResponse.status}`)
    if (createDrafts) {
      createDrafts.documents.push({
        id: `draft-${Date.now()}`,
        title: uploadForm.value.file.name,
        file_name: uploadForm.value.file.name,
        file_key: presignedData.key,
        file_type: uploadForm.value.file.type,
        file_size: uploadForm.value.file.size,
        description: uploadForm.value.description || '',
        registration_number: uploadForm.value.registration_number || '',
        validity: uploadForm.value.validity || '',
        compliance_status: uploadForm.value.compliance_status || '',
        document_type: uploadForm.value.document_type,
      })
    }
    toastFlash({ success: 'Document staged. It will be saved when you create the partner.' })
    resetUploadForm()
  } catch (error) {
    toastFlash({ error: `Upload failed: ${error?.message ?? 'Unknown error'}` })
  } finally {
    uploading.value = false
  }
}

const removeDraftDocument = id => {
  if (createDrafts) {
    createDrafts.documents = createDrafts.documents.filter(doc => doc.id !== id)
  }
}

const updateDocument = async () => {
  if (!editDocumentForm.value) return
  try {
    let presignedData = null
    if (editDocumentFile.value) {
      const presignedResponse = await fetch('/xhr/files-presigned-url', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
          filename: editDocumentFile.value.name,
          contentType: editDocumentFile.value.type,
        }),
      })
      if (!presignedResponse.ok)
        throw new Error(`Failed to get presigned URL: ${presignedResponse.status}`)
      presignedData = await presignedResponse.json()
      const uploadResponse = await fetch(presignedData.url, {
        method: 'PUT',
        headers: { 'Content-Type': editDocumentFile.value.type },
        body: editDocumentFile.value,
      })
      if (!uploadResponse.ok) throw new Error(`File upload failed: ${uploadResponse.status}`)
    }
    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('title', editDocumentForm.value.title)
    formData.append('description', editDocumentForm.value.description)
    formData.append('registration_number', editDocumentForm.value.registration_number)
    formData.append('validity', editDocumentForm.value.validity)
    formData.append('compliance_status', editDocumentForm.value.compliance_status || '')
    formData.append('document_type', editDocumentForm.value.document_type || '')
    formData.append('partner_code', props.partner?.code || '')
    if (presignedData && editDocumentFile.value) {
      formData.append('file_name', editDocumentFile.value.name)
      formData.append('file_key', presignedData.key)
      formData.append('file_type', editDocumentFile.value.type)
      formData.append('file_size', editDocumentFile.value.size)
    }
    const response = await fetch(`/documents/${editDocumentForm.value.id}`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
      body: formData,
    })
    if (!response.ok) throw new Error(`Failed to update document: ${response.status}`)
    toastFlash({ success: 'Document updated successfully.' })
    showEditDocumentModal.value = false
    router.reload()
  } catch (error) {
    toastFlash({ error: `Update failed: ${error?.message ?? 'Unknown error'}` })
  }
}

const handleLogoChange = event => {
  const file = event.target.files[0]
  if (file) {
    logoFile.value = file
    logoPreview.value = window.URL.createObjectURL(file)
  }
}

const appendMetadata = formData => {
  formData.append('district', form.district)
  formData.append('date_established', form.date_established)
  metadataFieldIds.forEach(id => {
    formData.append(id, form[id] ?? '')
  })
}

const appendDrafts = formData => {
  if (!createDrafts) return
  const unitRequestKeys = {
    productionUnits: 'production_units',
    consolidationUnits: 'consolidation_units',
    processingUnits: 'processing_units',
    marketingUnits: 'marketing_units',
  }
  const unitFields = [
    'name',
    'category',
    'herd_type',
    'date_of_establishment',
    'contact_person',
    'position',
    'contact_number',
    'telephone_number',
    'email_address',
    'website',
    'number_of_staff_male',
    'number_of_staff_female',
    'avg_salary_of_staff',
  ]
  Object.entries(unitRequestKeys).forEach(([draftKey, requestKey]) => {
    createDrafts[draftKey].forEach((unit, index) => {
      unitFields.forEach(field => {
        const value = unit[field]
        if (value !== undefined && value !== null && value !== '') {
          formData.append(`${requestKey}[${index}][${field}]`, value)
        }
      })
    })
  })

  createDrafts.products.forEach((product, index) => {
    const prefix = `products[${index}]`
    formData.append(`${prefix}[code]`, product.code)
    formData.append(`${prefix}[name]`, product.name)
    if (product.category) formData.append(`${prefix}[category]`, product.category)
    if (product.sub_category) formData.append(`${prefix}[sub_category]`, product.sub_category)
    if (product.product_registration)
      formData.append(`${prefix}[product_registration]`, product.product_registration)
    if (product.size) formData.append(`${prefix}[size]`, product.size)
    if (product.cost_per_qty) formData.append(`${prefix}[cost_per_qty]`, product.cost_per_qty)
    if (product.details) formData.append(`${prefix}[details]`, product.details)
    if (product.registration_file_name) {
      formData.append(`${prefix}[registration_file_name]`, product.registration_file_name)
      formData.append(`${prefix}[registration_file_key]`, product.registration_file_key)
      formData.append(`${prefix}[registration_file_type]`, product.registration_file_type)
      formData.append(`${prefix}[registration_file_size]`, product.registration_file_size)
    }
  })

  createDrafts.documents.forEach((doc, index) => {
    ;[
      'title',
      'file_name',
      'file_key',
      'file_type',
      'file_size',
      'description',
      'registration_number',
      'validity',
      'compliance_status',
      'document_type',
    ].forEach(field => {
      const value = doc[field]
      if (value !== undefined && value !== null && value !== '') {
        formData.append(`documents[${index}][${field}]`, value)
      }
    })
  })
}

const onSubmit = () => {
  if (isEditMode.value) {
    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('center_id', form.center_id)
    formData.append('code', form.code)
    formData.append('name', form.name)
    formData.append('status', form.status)
    formData.append('website', form.website)
    formData.append('contact_person', form.contact_person)
    formData.append('position', form.position)
    formData.append('contact_number', form.contact_number)
    formData.append('telephone_number', form.telephone_number)
    formData.append('email_address', form.email_address)
    formData.append('category', form.category)
    formData.append('partnership_type', form.partnership_type)
    form.project_ids.forEach((id, i) => formData.append(`project_ids[${i}]`, id))
    formData.append('total_composition', form.total_composition)
    formData.append('male_count', form.male_count)
    formData.append('female_count', form.female_count)
    formData.append('avg_monthly_income', form.avg_monthly_income)
    formData.append('street', form.street)
    formData.append('barangay', getBarangayNameByCode(form.barangay) || form.barangay)
    formData.append('city', getCityNameByCode(form.city) || form.city)
    formData.append('province', getProvinceNameByCode(form.province) || form.province)
    formData.append('region', getRegionNameByCode(form.region) || form.region)

    formData.append('street', form.street)
    formData.append('zip_code', form.zip_code)
    formData.append('country', form.country)
    formData.append('latitude', form.latitude)
    formData.append('longitude', form.longitude)
    appendMetadata(formData)
    if (logoFile.value) formData.append('logo', logoFile.value)
    submitting.value = true
    router.post(route('partners.update', props.partner.id), formData, {
      preserveScroll: true,
      onSuccess: () => {
        logoFile.value = null
        submitting.value = false
      },
      onError: errors => {
        fieldErrors.value = errors
        submitting.value = false
      },
      onFinish: () => {
        submitting.value = false
      },
    })
  }
  if (isCreateMode.value) {
    const formData = new FormData()
    formData.append('center_id', form.center_id)
    formData.append('code', form.code)
    formData.append('name', form.name)
    formData.append('status', form.status)
    formData.append('website', form.website)
    formData.append('contact_person', form.contact_person)
    formData.append('position', form.position)
    formData.append('contact_number', form.contact_number)
    formData.append('telephone_number', form.telephone_number)
    formData.append('email_address', form.email_address)
    formData.append('category', form.category)
    formData.append('partnership_type', form.partnership_type)
    form.project_ids.forEach((id, i) => formData.append(`project_ids[${i}]`, id))
    formData.append('total_composition', form.total_composition)
    formData.append('male_count', form.male_count)
    formData.append('female_count', form.female_count)
    formData.append('avg_monthly_income', form.avg_monthly_income)
    formData.append('street', form.street)
    formData.append('barangay', getBarangayNameByCode(form.barangay) || form.barangay)
    formData.append('city', getCityNameByCode(form.city) || form.city)
    formData.append('province', getProvinceNameByCode(form.province) || form.province)
    formData.append('region', getRegionNameByCode(form.region) || form.region)

    formData.append('street', form.street)
    formData.append('zip_code', form.zip_code)
    formData.append('country', form.country)
    formData.append('latitude', form.latitude)
    formData.append('longitude', form.longitude)
    appendMetadata(formData)
    appendDrafts(formData)
    if (logoFile.value) formData.append('logo', logoFile.value)
    submitting.value = true
    router.post(route('partners.store'), formData, {
      preserveScroll: true,
      onSuccess: () => {
        logoFile.value = null
        submitting.value = false
      },
      onError: errors => {
        fieldErrors.value = errors
        submitting.value = false
      },
      onFinish: () => {
        submitting.value = false
      },
    })
  }
}

const getEnumOptions = enumObj => {
  if (!enumObj) return []
  return Object.entries(enumObj).map(([key, value]) => ({ key, value }))
}

const { isCenterScoped } = useAuth()

const centerOptions = computed(() => props.centers.map(c => ({ value: c.id, label: c.name })))

const openEditModal = (type, id, category = null) => {
  if (!modalState) {
    toastFlash({
      error: 'Please save the partner first before adding value chain units or products.',
    })
    return
  }
  modalState.value = { open: true, type, id, category }
}

// Track which Product tab is active so the header Add button stays in sync
const activeProductTab = ref('milk')
const productTabLabels = {
  milk: 'Milk',
  meat: 'Meat',
  manure: 'Manure',
  hide: 'Hide',
  others: 'Other',
}
const activeProductTabLabel = computed(() => productTabLabels[activeProductTab.value] || 'Product')

const removeDraft = (key, id) => {
  if (!createDrafts) return
  createDrafts[key] = createDrafts[key].filter(item => item.id !== id)
}

const deleteProductionUnit = unit => {
  if (useDrafts.value) {
    if (confirm(`Are you sure you want to delete ${unit.name}?`)) {
      removeDraft('productionUnits', unit.id)
    }
    return
  }

  if (!unit?.id) return

  if (confirm(`Are you sure you want to delete ${unit.name}?`)) {
    router.delete(route('partners.production-units.destroy', [props.partner.id, unit.id]), {
      preserveScroll: true,
    })
  }
}

const deleteConsolidationUnit = unit => {
  if (useDrafts.value) {
    if (confirm(`Are you sure you want to delete ${unit.name}?`)) {
      removeDraft('consolidationUnits', unit.id)
    }
    return
  }

  if (!unit?.id) return

  if (confirm(`Are you sure you want to delete ${unit.name}?`)) {
    router.delete(route('partners.consolidation-units.destroy', [props.partner.id, unit.id]), {
      preserveScroll: true,
    })
  }
}

const deleteProcessingUnit = unit => {
  if (useDrafts.value) {
    if (confirm(`Are you sure you want to delete ${unit.name}?`)) {
      removeDraft('processingUnits', unit.id)
    }
    return
  }

  if (!unit?.id) return

  if (confirm(`Are you sure you want to delete ${unit.name}?`)) {
    router.delete(route('partners.processing-units.destroy', [props.partner.id, unit.id]), {
      preserveScroll: true,
    })
  }
}

const deleteMarketingUnit = unit => {
  if (useDrafts.value) {
    if (confirm(`Are you sure you want to delete ${unit.name}?`)) {
      removeDraft('marketingUnits', unit.id)
    }
    return
  }

  if (!unit?.id) return

  if (confirm(`Are you sure you want to delete ${unit.name}?`)) {
    router.delete(route('partners.marketing-units.destroy', [props.partner.id, unit.id]), {
      preserveScroll: true,
    })
  }
}

const deleteProduct = product => {
  if (useDrafts.value) {
    if (confirm(`Are you sure you want to delete ${productNameLabel(product, props.enums)}?`)) {
      removeDraft('products', product.id)
    }
    return
  }

  if (!product?.id) return

  if (confirm(`Are you sure you want to delete ${productNameLabel(product, props.enums)}?`)) {
    router.delete(route('partners.products.destroy', [props.partner.id, product.id]), {
      preserveScroll: true,
    })
  }
}

// Track active Value Chain tab so header Add button stays in sync
const activeValueChainTab = ref('production')
const valueChainTabLabels = {
  production: 'Production',
  consolidation: 'Consolidation',
  processing: 'Processing',
  marketing: 'Marketing',
}
const activeValueChainTabLabel = computed(
  () => valueChainTabLabels[activeValueChainTab.value] || 'Production'
)

// Active Extended Profile tab (defaults to first section)
const activeMetadataTab = ref(metadataSections[0]?.title || '')
const activeMetadataSection = computed(
  () =>
    metadataSections.find(section => section.title === activeMetadataTab.value) ||
    metadataSections[0]
)

const activeMetadataTabLabel = computed(
  () => activeMetadataSection.value?.title || 'Extended Profile'
)

const hasMetadataValue = id => {
  const value = form[id]
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

const metadataModalOpen = ref(false)

const openMetadataModal = () => {
  metadataModalOpen.value = true
}
</script>

<template>
  <div class="flex flex-col space-y-4">
    <!-- ── Logo + General Info ── -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
      <!-- Logo -->
      <div class="flex flex-col rounded-lg bg-gray-100 p-4 lg:col-span-1">
        <Avatar :class="cn('m-auto aspect-square h-auto w-full max-w-[280px] md:max-w-[18rem]')">
          <AvatarImage
            :src="logoPreview || partner.logo_url"
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
        <div v-if="isFormMode" class="mt-4">
          <Label for="logo-upload" class="sr-only">Upload Logo</Label>
          <div class="rounded-md bg-gray-100 p-2 text-gray-700">
            <Input
              id="logo-upload"
              type="file"
              accept="image/*"
              class="w-full cursor-pointer bg-transparent text-gray-700 file:mr-2 file:cursor-pointer file:rounded file:border-0 file:bg-gray-300 file:px-3 file:py-1 file:text-gray-700"
              @change="handleLogoChange"
            />
          </div>
        </div>
      </div>

      <!-- General Info card -->
      <Card class="lg:col-span-4">
        <CardHeader>
          <CardTitle>General Information</CardTitle>
          <CardDescription>Demographics and Geographic</CardDescription>
        </CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="onSubmit">
            <!-- View mode: responsive dl -->
            <dl v-if="isViewMode" class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Partner Code</dt>

                <dd class="break-words">{{ partner.code || 'N/A' }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Name</dt>

                <dd class="break-words">{{ partner.name || 'N/A' }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Status</dt>

                <dd class="">{{ label(enums.statuses, partner.status) || 'N/A' }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Category</dt>

                <dd class="">
                  {{ label(enums.categories, partner.category) || 'N/A' }}
                </dd>
              </div>
              <!-- Partnership Type (hidden for now)
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Partnership Type</dt>

                <dd class="">
                  {{ label(enums.partnershipTypes, partner.partnership_type) || 'N/A' }}
                </dd>
              </div>
              -->
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Program</dt>

                <dd class="">
                  {{ label(enums.programs, partner.program) || 'N/A' }}
                </dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Website</dt>

                <dd class="break-all">{{ partner.website || 'N/A' }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Date Established</dt>

                <dd class="">{{ partner.metadata?.date_established || 'N/A' }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Contact Person</dt>

                <dd class="">{{ partner.contact_person || 'N/A' }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Position</dt>

                <dd class="">{{ partner.position || 'N/A' }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Contact Number</dt>

                <dd class="">{{ partner.contact_number || 'N/A' }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Telephone Number</dt>

                <dd class="">{{ partner.telephone_number || 'N/A' }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Email Address</dt>

                <dd class="break-all">{{ partner.email_address || 'N/A' }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Total Composition</dt>

                <dd class="">{{ partner.total_composition || 0 }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Male Count</dt>

                <dd class="">{{ partner.male_count || 0 }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Female Count</dt>

                <dd class="">{{ partner.female_count || 0 }}</dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-muted-foreground font-medium">Average Monthly Income</dt>

                <dd class="">
                  {{ partner.avg_monthly_income ? `₱${partner.avg_monthly_income}` : '₱0.00' }}
                </dd>
              </div>
            </dl>

            <!-- Edit / Create mode: input grid -->
            <div
              v-if="isFormMode"
              class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2 lg:grid-cols-4"
            >
              <!-- Center -->
              <div v-if="!isCenterScoped" class="space-y-2">
                <Label for="center_id" class="text-muted-foreground font-medium">Center</Label>
                <SearchableSelect
                  v-model="form.center_id"
                  :options="centerOptions"
                  placeholder="Select center"
                  :disabled="disableCenterField || isCenterScoped"
                />
                <p v-if="fieldErrors.center_id" class="mt-1 text-sm text-red-500">
                  {{ fieldErrors.center_id[0] }}
                </p>
              </div>

              <!-- Dynamic fields -->
              <div
                v-for="field in [
                  {
                    id: 'code',
                    label: 'Partner Code',
                    type: 'text',
                    disabled: isEditMode,
                    required: true,
                  },
                  { id: 'name', label: 'Name', type: 'text', required: true },
                  { id: 'status', label: 'Status', type: 'select', options: enums.statuses },
                  { id: 'website', label: 'Website', type: 'text' },
                  { id: 'date_established', label: 'Date Established', type: 'date' },
                  { id: 'contact_person', label: 'Contact Person', type: 'text' },
                  { id: 'position', label: 'Position', type: 'text' },
                  { id: 'contact_number', label: 'Contact Number', type: 'tel' },
                  { id: 'telephone_number', label: 'Telephone Number', type: 'tel' },
                  { id: 'email_address', label: 'Email Address', type: 'email' },
                  { id: 'total_composition', label: 'Total Composition', type: 'number' },
                  { id: 'male_count', label: 'Male Count', type: 'number' },
                  { id: 'female_count', label: 'Female Count', type: 'number' },
                  {
                    id: 'avg_monthly_income',
                    label: 'Average Monthly Income',
                    type: 'number',
                    step: '0.01',
                  },
                ]"
                :key="field.id"
                class="space-y-2"
              >
                <Label :for="field.id" class="text-muted-foreground font-medium">{{
                  field.label
                }}</Label>
                <Select v-if="field.type === 'select'" :id="field.id" v-model="form[field.id]">
                  <SelectTrigger class="h-8 w-full">
                    <SelectValue :placeholder="'Select ' + field.label.toLowerCase()" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(label, value) in field.options" :key="value" :value="value">
                      {{ label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <Input
                  v-else
                  :id="field.id"
                  v-model="form[field.id]"
                  :type="field.type"
                  :step="field.step"
                  :placeholder="field.disabled ? '' : 'Enter ' + field.label.toLowerCase()"
                  :disabled="field.disabled || false"
                  :required="field.required || false"
                  :class="[
                    'h-8',
                    fieldErrors[field.id]
                      ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                      : '',
                  ]"
                  @input="clearError(field.id)"
                />
                <p v-if="fieldErrors[field.id]" class="mt-1 text-sm text-red-500">
                  {{
                    Array.isArray(fieldErrors[field.id])
                      ? fieldErrors[field.id][0]
                      : fieldErrors[field.id]
                  }}
                </p>
              </div>

              <!-- Projects (multi-select) -->
              <div class="space-y-2">
                <Label class="text-muted-foreground font-medium">Projects</Label>
                <Select v-if="isFormMode" v-model="form.project_ids" multiple>
                  <SelectTrigger class="h-8 w-full">
                    <SelectValue placeholder="Select projects" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(name, id) in props.projects" :key="id" :value="Number(id)">
                      {{ name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="flex min-h-[2rem] flex-wrap items-center gap-1">
                  <span
                    v-for="id in form.project_ids"
                    v-if="form.project_ids.length"
                    :key="id"
                    class="bg-muted rounded px-2 py-0.5 text-xs"
                    >{{ props.projects[id] || id }}</span
                  >
                  <span v-else class="text-muted-foreground text-sm">—</span>
                </div>
              </div>

              <!-- Category -->
              <div class="space-y-2">
                <Label for="category" class="text-muted-foreground font-medium">Category</Label>
                <Select v-model="form.category">
                  <SelectTrigger class="h-8 w-full"
                    ><SelectValue placeholder="Select category"
                  /></SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="opt in getEnumOptions(enums.categories)"
                      :key="opt.key"
                      :value="opt.key"
                    >
                      {{ opt.value }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="fieldErrors.category" class="mt-1 text-sm text-red-500">
                  {{ fieldErrors.category[0] }}
                </p>
              </div>

              <!-- Partnership Type (hidden for now)
              <div class="space-y-2">
                <Label for="partnership_type" class="text-muted-foreground font-medium"
                  >Partnership Type</Label
                >
                <Select v-model="form.partnership_type">
                  <SelectTrigger class="h-8 w-full"
                    ><SelectValue placeholder="Select partnership type"
                  /></SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="opt in getEnumOptions(enums.partnershipTypes)"
                      :key="opt.key"
                      :value="opt.key"
                    >
                      {{ opt.value }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="fieldErrors.partnership_type" class="mt-1 text-sm text-red-500">
                  {{ fieldErrors.partnership_type[0] }}
                </p>
              </div>
              -->

              <!-- Address Fields -->
              <div class="sm:col-span-2 lg:col-span-4">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                  <div class="space-y-2">
                    <Label for="region" class="text-muted-foreground font-medium">Region</Label>
                    <Select v-model="form.region" @update:model-value="clearError('region')">
                      <SelectTrigger class="h-8 w-full">
                        <SelectValue placeholder="Select region" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="r in regionsData" :key="r.code" :value="r.code">
                          {{ r.name }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                    <p v-if="fieldErrors.region" class="mt-1 text-sm text-red-500">
                      {{ fieldErrors.region[0] }}
                    </p>
                  </div>
                  <div class="space-y-2">
                    <Label for="province" class="text-muted-foreground font-medium">Province</Label>
                    <Select
                      v-model="form.province"
                      :disabled="!form.region || !addressRegionHasProvinces"
                      @update:model-value="clearError('province')"
                    >
                      <SelectTrigger class="h-8 w-full">
                        <SelectValue placeholder="Select province" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="p in addressProvinces" :key="p.code" :value="p.code">
                          {{ p.name }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                    <p v-if="fieldErrors.province" class="mt-1 text-sm text-red-500">
                      {{ fieldErrors.province[0] }}
                    </p>
                  </div>
                  <div class="space-y-2">
                    <Label for="city" class="text-muted-foreground font-medium"
                      >City / Municipality</Label
                    >
                    <Select
                      v-model="form.city"
                      :disabled="addressRegionHasProvinces ? !form.province : !form.region"
                      @update:model-value="clearError('city')"
                    >
                      <SelectTrigger class="h-8 w-full">
                        <SelectValue placeholder="Select city / municipality" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="c in addressCities" :key="c.code" :value="c.code">
                          {{ c.name }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                    <p v-if="fieldErrors.city" class="mt-1 text-sm text-red-500">
                      {{ fieldErrors.city[0] }}
                    </p>
                  </div>
                  <div class="space-y-2">
                    <Label for="barangay" class="text-muted-foreground font-medium">Barangay</Label>
                    <Select
                      v-model="form.barangay"
                      :disabled="!form.city"
                      @update:model-value="clearError('barangay')"
                    >
                      <SelectTrigger class="h-8 w-full">
                        <SelectValue placeholder="Select barangay" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="b in addressBarangays" :key="b.code" :value="b.code">
                          {{ b.name }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                    <p v-if="fieldErrors.barangay" class="mt-1 text-sm text-red-500">
                      {{ fieldErrors.barangay[0] }}
                    </p>
                  </div>
                  <div class="space-y-2">
                    <Label for="district" class="text-muted-foreground font-medium">District</Label>
                    <Select
                      v-model="form.district"
                      :disabled="districtDisabled"
                      @update:model-value="clearError('district')"
                    >
                      <SelectTrigger class="h-8 w-full">
                        <SelectValue placeholder="Select district" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="d in districts" :key="d.city_code" :value="d.name">
                          {{ d.name }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                    <p v-if="fieldErrors.district" class="mt-1 text-sm text-red-500">
                      {{ fieldErrors.district[0] }}
                    </p>
                  </div>
                  <div class="space-y-2">
                    <Label for="street" class="text-muted-foreground font-medium">Street</Label>
                    <Input
                      id="street"
                      v-model="form.street"
                      type="text"
                      placeholder="Street"
                      :class="[
                        'h-8',
                        fieldErrors.street
                          ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                          : '',
                      ]"
                      @input="clearError('street')"
                    />
                    <p v-if="fieldErrors.street" class="mt-1 text-sm text-red-500">
                      {{ fieldErrors.street[0] }}
                    </p>
                  </div>
                  <div class="space-y-2">
                    <Label for="zip_code" class="text-muted-foreground font-medium">ZIP Code</Label>
                    <Input
                      id="zip_code"
                      v-model="form.zip_code"
                      type="text"
                      placeholder="ZIP Code"
                      :class="[
                        'h-8',
                        fieldErrors.zip_code
                          ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                          : '',
                      ]"
                      @input="clearError('zip_code')"
                    />
                    <p v-if="fieldErrors.zip_code" class="mt-1 text-sm text-red-500">
                      Zip Code Should be 4 digits.
                    </p>
                  </div>
                  <div class="space-y-2">
                    <Label for="country" class="text-muted-foreground font-medium">Country</Label>
                    <Select
                      :model-value="form.country"
                      @update:model-value="
                        val => {
                          form.country = val
                          clearError('country')
                        }
                      "
                    >
                      <SelectTrigger class="h-8 w-full"
                        ><SelectValue placeholder="Select country"
                      /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="Philippines">Philippines</SelectItem>
                      </SelectContent>
                    </Select>
                    <p v-if="fieldErrors.country" class="mt-1 text-sm text-red-500">
                      {{ fieldErrors.country[0] }}
                    </p>
                  </div>
                  <div class="space-y-2">
                    <Label for="latitude" class="text-muted-foreground font-medium">Latitude</Label>
                    <Input
                      id="latitude"
                      v-model="form.latitude"
                      type="text"
                      placeholder="Latitude"
                      :class="[
                        'h-8',
                        fieldErrors.latitude
                          ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                          : '',
                      ]"
                      @input="clearError('latitude')"
                    />
                    <p v-if="fieldErrors.latitude" class="mt-1 text-sm text-red-500">
                      {{ fieldErrors.latitude[0] }}
                    </p>
                  </div>
                  <div class="space-y-2">
                    <Label for="longitude" class="text-muted-foreground font-medium"
                      >Longitude</Label
                    >
                    <Input
                      id="longitude"
                      v-model="form.longitude"
                      type="text"
                      placeholder="Longitude"
                      :class="[
                        'h-8',
                        fieldErrors.longitude
                          ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                          : '',
                      ]"
                      @input="clearError('longitude')"
                    />
                    <p v-if="fieldErrors.longitude" class="mt-1 text-sm text-red-500">
                      {{ fieldErrors.longitude[0] }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Submit Buttons — full-width on mobile, auto on sm+ -->
              <div
                class="col-span-1 flex flex-wrap justify-end gap-2 pt-4 sm:col-span-2 lg:col-span-4"
              >
                <Button
                  type="submit"
                  :disabled="submitting"
                  class="w-full bg-black text-white hover:bg-black/90 sm:w-auto"
                >
                  {{ submitting ? 'Saving...' : isCreateMode ? 'Create Partner' : 'Save Changes' }}
                </Button>
              </div>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>

    <!-- ── Extended Profile ── -->
    <Card>
      <CardHeader>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <CardTitle>Extended Profile</CardTitle>
            <CardDescription
              >Registration, management, business details, and financial
              information</CardDescription
            >
          </div>
          <Button
            v-if="isFormMode"
            class="bg-blue-600 hover:bg-blue-700"
            type="button"
            @click="openMetadataModal"
          >
            {{ sectionHasData(activeMetadataSection) ? 'Edit' : 'Add' }}
            {{ activeMetadataTabLabel }}
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <Tabs
          v-model="activeMetadataTab"
          :default-value="metadataSections[0]?.title"
          class="bordered"
        >
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
                  {{ metadataValueLabel(field, form[field.id]) || 'N/A' }}
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

    <!-- ── Value Chain Units ── -->
    <Card>
      <CardHeader>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <CardTitle>Value Chain Units</CardTitle>
            <CardDescription
              >Manage production, consolidation, processing, and marketing units</CardDescription
            >
          </div>
          <!-- Single Add button that tracks the active tab -->
          <Button
            v-if="activeValueChainTab !== 'marketing'"
            class="bg-blue-600 hover:bg-blue-700"
            type="button"
            @click="openEditModal(activeValueChainTab + '-units', null)"
          >
            Add {{ activeValueChainTabLabel }} Unit
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <Tabs v-model="activeValueChainTab" default-value="production" class="bordered">
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
                v-for="(production, index) in displayedProductionUnits"
                :key="production.id || index"
                class="rounded-lg border p-4"
              >
                <div class="mb-4 flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <h4 class="text-lg font-semibold">{{ production.name || 'N/A' }}</h4>
                    <p class="text-muted-foreground text-sm">
                      {{ label(enums.productionCategory, production.category) }} •
                      {{ label(enums.herdType, production.herd_type) }}
                    </p>
                  </div>
                  <div class="flex shrink-0 gap-1">
                    <Button
                      size="sm"
                      variant="outline"
                      @click="openEditModal('production-units', production.id)"
                    >
                      <Pencil class="h-4 w-4" />
                    </Button>
                    <Button size="sm" variant="outline" @click="deleteProductionUnit(production)">
                      <Trash2 class="h-4 w-4 text-red-600" />
                    </Button>
                  </div>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Contact Person</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ production.contact_person || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Contact Number</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ production.contact_number || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Position</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ production.position || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Telephone Number</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ production.telephone_number || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Email Address</dt>

                    <dd class="min-w-0 font-medium break-words break-all">
                      {{ production.email_address || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Website</dt>

                    <dd class="min-w-0 font-medium break-words break-all">
                      {{ production.website || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ production.number_of_staff_male || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ production.number_of_staff_female || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ production.avg_salary_of_staff || 'N/A' }}
                    </dd>
                  </div>
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
                v-for="(consolidation, index) in displayedConsolidationUnits"
                :key="consolidation.id || index"
                class="rounded-lg border p-4"
              >
                <div class="mb-4 flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <h4 class="text-lg font-semibold">{{ consolidation.name || 'N/A' }}</h4>
                    <p class="text-muted-foreground text-sm">
                      {{ label(enums.consolidationCategory, consolidation.category) }}
                    </p>
                  </div>
                  <div class="flex shrink-0 gap-1">
                    <Button
                      size="sm"
                      variant="outline"
                      @click="openEditModal('consolidation-units', consolidation.id)"
                    >
                      <Pencil class="h-4 w-4" />
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      @click="deleteConsolidationUnit(consolidation)"
                    >
                      <Trash2 class="h-4 w-4 text-red-600" />
                    </Button>
                  </div>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Contact Person</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ consolidation.contact_person || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Contact Number</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ consolidation.contact_number || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Position</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ consolidation.position || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Telephone Number</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ consolidation.telephone_number || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Email Address</dt>

                    <dd class="min-w-0 font-medium break-words break-all">
                      {{ consolidation.email_address || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Website</dt>

                    <dd class="min-w-0 font-medium break-words break-all">
                      {{ consolidation.website || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ consolidation.number_of_staff_male || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ consolidation.number_of_staff_female || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ consolidation.avg_salary_of_staff || 'N/A' }}
                    </dd>
                  </div>
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
                v-for="(processing, index) in displayedProcessingUnits"
                :key="processing.id || index"
                class="rounded-lg border p-4"
              >
                <div class="mb-4 flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <h4 class="text-lg font-semibold">{{ processing.name || 'N/A' }}</h4>
                    <p class="text-muted-foreground text-sm">
                      {{ label(enums.processingCategory, processing.category) }}
                    </p>
                  </div>
                  <div class="flex shrink-0 gap-1">
                    <Button
                      size="sm"
                      variant="outline"
                      @click="openEditModal('processing-units', processing.id)"
                    >
                      <Pencil class="h-4 w-4" />
                    </Button>
                    <Button size="sm" variant="outline" @click="deleteProcessingUnit(processing)">
                      <Trash2 class="h-4 w-4 text-red-600" />
                    </Button>
                  </div>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Contact Person</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ processing.contact_person || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Contact Number</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ processing.contact_number || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Position</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ processing.position || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Telephone Number</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ processing.telephone_number || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Email Address</dt>

                    <dd class="min-w-0 font-medium break-words break-all">
                      {{ processing.email_address || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Website</dt>

                    <dd class="min-w-0 font-medium break-words break-all">
                      {{ processing.website || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ processing.number_of_staff_male || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ processing.number_of_staff_female || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ processing.avg_salary_of_staff || 'N/A' }}
                    </dd>
                  </div>
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
            <div class="space-y-4">
              <div v-if="dairyBoxes && dairyBoxes.length > 0">
                <div
                  v-for="dairyBox in dairyBoxes"
                  :key="dairyBox.id"
                  class="mb-6 rounded-lg border p-6"
                >
                  <div class="mb-4 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <h4 class="text-lg font-semibold">{{ dairyBox.business_name || 'N/A' }}</h4>
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
                    <div class="flex items-baseline justify-between gap-2">
                      <dt class="text-muted-foreground font-medium">Manager</dt>

                      <dd class="min-w-0 font-medium break-words">
                        {{ dairyBox.manager || 'N/A' }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                      <dt class="text-muted-foreground font-medium">Dairy Box Focal</dt>

                      <dd class="min-w-0 font-medium break-words">
                        {{ dairyBox.dairy_box_focal || 'N/A' }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                      <dt class="text-muted-foreground font-medium">Contact Number</dt>

                      <dd class="min-w-0 font-medium break-words">
                        {{ dairyBox.contact_number || 'N/A' }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                      <dt class="text-muted-foreground font-medium">Status</dt>

                      <dd class="min-w-0 font-medium break-words">
                        {{ enums.dairyBoxStatuses?.[dairyBox.status] || dairyBox.status || 'N/A' }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                      <dt class="text-muted-foreground font-medium">Launched Date</dt>

                      <dd class="min-w-0 font-medium break-words">
                        {{ dairyBox.launched_date || 'N/A' }}
                      </dd>
                    </div>
                  </dl>
                </div>
              </div>
              <div
                v-for="(marketing, index) in displayedMarketingUnits"
                :key="marketing.id || index"
                class="rounded-lg border p-4"
              >
                <div class="mb-4 flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <h4 class="text-lg font-semibold">{{ marketing.name || 'N/A' }}</h4>
                  </div>
                  <div class="flex shrink-0 gap-1">
                    <Button
                      size="sm"
                      variant="outline"
                      @click="openEditModal('marketing-units', marketing.id)"
                    >
                      <Pencil class="h-4 w-4" />
                    </Button>
                    <Button size="sm" variant="outline" @click="deleteMarketingUnit(marketing)">
                      <Trash2 class="h-4 w-4 text-red-600" />
                    </Button>
                  </div>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Contact Person</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ marketing.contact_person || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Contact Number</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ marketing.contact_number || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Position</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ marketing.position || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Telephone Number</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ marketing.telephone_number || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Email Address</dt>

                    <dd class="min-w-0 font-medium break-words break-all">
                      {{ marketing.email_address || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Website</dt>

                    <dd class="min-w-0 font-medium break-words break-all">
                      {{ marketing.website || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ marketing.number_of_staff_male || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ marketing.number_of_staff_female || 'N/A' }}
                    </dd>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>

                    <dd class="min-w-0 font-medium break-words">
                      {{ marketing.avg_salary_of_staff || 'N/A' }}
                    </dd>
                  </div>
                </dl>
              </div>
              <div
                v-if="!dairyBoxes?.length && !partner.marketing_units?.length"
                class="text-muted-foreground py-8 text-center"
              >
                No marketing data found.
              </div>
            </div>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>

    <!-- ── Products ── -->
    <Card>
      <CardHeader>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <CardTitle>Products</CardTitle>
            <CardDescription>Processed or market items</CardDescription>
          </div>
          <Button
            v-if="isFormMode"
            class="bg-blue-600 hover:bg-blue-700"
            type="button"
            @click="openEditModal('products', null, activeProductTab)"
          >
            Add {{ activeProductTabLabel }} Product
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <Tabs v-model="activeProductTab" default-value="milk" :class="cn('bordered')">
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
                      <TableHead v-if="isFormMode">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow
                      v-for="product in displayedProducts.filter(tab.filter)"
                      :key="product.id"
                    >
                      <TableCell>{{ productNameLabel(product, enums) }}</TableCell>
                      <TableCell>
                        <button
                          v-if="
                            productRegistrationDocuments?.[product.code] &&
                            product.product_registration
                          "
                          type="button"
                          class="flex items-center space-x-1 text-blue-600 hover:underline"
                          @click="openDocumentModal(productRegistrationDocuments[product.code])"
                        >
                          {{ product.product_registration }}
                        </button>
                        <span v-else>{{ product.product_registration || 'N/A' }}</span>
                      </TableCell>
                      <TableCell>{{ product.size }}</TableCell>
                      <TableCell>{{ product.cost_per_qty }}</TableCell>
                      <TableCell v-if="isFormMode && product.id">
                        <Button
                          variant="ghost"
                          size="sm"
                          @click="openEditModal('products', product.id)"
                        >
                          <Pencil class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" @click="deleteProduct(product)">
                          <Trash2 class="h-4 w-4 text-red-600" />
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
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <CardTitle>Documents</CardTitle>
            <CardDescription>Uploaded files related to the partner</CardDescription>
          </div>
          <Button
            v-if="isFormMode"
            class="bg-blue-600 hover:bg-blue-700"
            type="button"
            @click="
              () => {
                if (!isCreateMode && !partner?.id) {
                  toastFlash({ error: 'Please save the partner first before uploading documents.' })
                  return
                }
                showUploadModal = true
              }
            "
          >
            Add Document
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <div class="overflow-x-auto">
          <Table class="min-w-[600px]">
            <TableHeader>
              <TableRow>
                <TableHead>Document Type</TableHead>
                <TableHead>Registration Number</TableHead>
                <TableHead>Validity</TableHead>
                <TableHead>Uploaded By</TableHead>
                <TableHead>Uploaded At</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="document in displayedDocuments" :key="document.id">
                <TableCell>
                  <template v-if="useDrafts">
                    {{ enums.document?.[document.document_type] || document.document_type || '-' }}
                  </template>
                  <template v-else>
                    <span v-if="document.tags && document.tags.length > 0">
                      {{
                        enums.document?.[document.tags.find(t => t.key === 'document')?.value] ||
                        document.tags.find(t => t.key === 'document')?.value ||
                        '-'
                      }}
                    </span>
                    <span v-else>-</span>
                  </template>
                </TableCell>
                <TableCell>
                  <button
                    v-if="!useDrafts && document.file"
                    type="button"
                    class="flex items-center space-x-1 text-blue-600 hover:underline"
                    @click="openDocumentModal(document)"
                  >
                    {{ document.registration_number || '-' }}
                  </button>
                  <span v-else>{{ document.registration_number || '-' }}</span>
                </TableCell>
                <TableCell>{{
                  document.validity ? new Date(document.validity).toLocaleDateString() : '-'
                }}</TableCell>
                <TableCell v-if="useDrafts">-</TableCell>
                <TableCell v-else>{{
                  document.file?.uploaded_by?.name || document.file?.uploadedBy?.name
                }}</TableCell>
                <TableCell v-if="useDrafts">Pending</TableCell>
                <TableCell v-else>{{
                  new Date(document.file.created_at).toLocaleDateString()
                }}</TableCell>
                <TableCell>
                  <Button
                    v-if="!useDrafts"
                    variant="ghost"
                    size="sm"
                    @click="openEditDocumentModal(document)"
                  >
                    <Pencil class="h-4 w-4" />
                  </Button>
                  <Button
                    v-if="useDrafts"
                    variant="ghost"
                    size="sm"
                    @click="removeDraftDocument(document.id)"
                  >
                    <Trash2 class="h-4 w-4 text-red-600" />
                  </Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
  </div>

  <!-- ── Document View Modal ── -->
  <Dialog v-model:open="showDocumentModal">
    <DialogContent
      class="flex max-h-[95dvh] w-full flex-col overflow-hidden rounded-t-2xl sm:max-h-[90vh] sm:max-w-4xl sm:rounded-lg"
    >
      <DialogHeader class="shrink-0">
        <DialogTitle>{{ selectedDocument?.title }}</DialogTitle>
        <DialogDescription>File preview and download options</DialogDescription>
      </DialogHeader>
      <div v-if="selectedDocument" class="flex min-h-0 flex-1 flex-col space-y-4 overflow-y-auto">
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
        <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 sm:gap-4">
          <div>
            <span class="font-semibold">File Type:</span
            ><span class="ml-2">{{ selectedDocument.file?.type }}</span>
          </div>
          <div>
            <span class="font-semibold">File Size:</span>
            <span class="ml-2">{{
              selectedDocument.file?.size
                ? (selectedDocument.file.size / 1024 / 1024).toFixed(2) + ' MB'
                : 'Unknown'
            }}</span>
          </div>
          <div>
            <span class="font-semibold">Document Type:</span>
            <span class="ml-2">{{
              enums.document?.[selectedDocument.tags?.find(t => t.key === 'document')?.value] ||
              selectedDocument.tags?.find(t => t.key === 'document')?.value ||
              '-'
            }}</span>
          </div>
          <div>
            <span class="font-semibold">Uploaded By:</span>
            <span class="ml-2">{{
              selectedDocument.file?.uploaded_by?.name || selectedDocument.file?.uploadedBy?.name
            }}</span>
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

  <!-- ── Document Upload Modal ── -->
  <Dialog v-model:open="showUploadModal">
    <DialogContent class="max-h-[95dvh] w-full overflow-y-auto sm:max-h-[90vh] sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>Upload Document</DialogTitle>
        <DialogDescription
          >Add a new document for {{ partner.name || 'this partner' }}</DialogDescription
        >
      </DialogHeader>
      <div class="space-y-4">
        <div>
          <label class="mb-2 block text-sm font-medium"
            >File <span class="text-red-500">*</span></label
          >
          <input
            type="file"
            accept=".pdf"
            class="w-full rounded-md border p-2"
            :disabled="uploading"
            @change="handleDocumentFileChange"
          />
          <span v-if="uploadErrors.file" class="mt-1 block text-sm text-red-500">{{
            uploadErrors.file
          }}</span>
        </div>
        <div>
          <Label class="mb-2 block text-sm font-medium"
            >Document Type <span class="text-red-500">*</span></Label
          >
          <Select v-model="uploadForm.document_type" :disabled="uploading" required>
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select document type" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="(label, value) in enums.document" :key="value" :value="value">
                {{ label }}
              </SelectItem>
            </SelectContent>
          </Select>
          <span v-if="uploadErrors.document_type" class="mt-1 block text-sm text-red-500">{{
            uploadErrors.document_type
          }}</span>
        </div>
        <div>
          <Label class="mb-2 block text-sm font-medium">Status</Label>
          <Select v-model="uploadForm.compliance_status" :disabled="uploading">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="(statusLabel, value) in enums.complianceStatuses"
                :key="value"
                :value="value"
              >
                {{ statusLabel }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium">Registration Number</label>
          <input
            v-model="uploadForm.registration_number"
            type="text"
            placeholder="Enter registration number (optional)"
            class="w-full rounded-md border p-2"
            :disabled="uploading"
          />
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium">Validity</label>
          <input
            v-model="uploadForm.validity"
            type="date"
            class="w-full rounded-md border p-2"
            :disabled="uploading"
          />
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium">Description</label>
          <textarea
            v-model="uploadForm.description"
            placeholder="Enter document description (optional)"
            rows="3"
            class="w-full rounded-md border p-2"
            :disabled="uploading"
          ></textarea>
        </div>
      </div>
      <DialogFooter class="flex-col gap-2 sm:flex-row sm:gap-0">
        <Button
          :disabled="uploading"
          class="w-full bg-blue-600 hover:bg-blue-700 sm:w-auto"
          @click="uploadDocument"
        >
          {{ uploading ? 'Uploading...' : 'Upload Document' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <!-- ── Document Edit Modal ── -->
  <Dialog v-model:open="showEditDocumentModal">
    <DialogContent class="max-h-[95dvh] w-full overflow-y-auto sm:max-h-[90vh] sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>Edit Document</DialogTitle>
        <DialogDescription>Update document information</DialogDescription>
      </DialogHeader>
      <div v-if="editDocumentForm" class="space-y-4">
        <div>
          <label class="mb-2 block text-sm font-medium"
            >Replace File (Optional) <span class="text-xs text-gray-500">PDF only</span></label
          >
          <input
            type="file"
            accept=".pdf"
            class="w-full rounded-md border p-2"
            @change="handleEditDocumentFileChange"
          />
          <p v-if="editDocumentFile" class="mt-1 text-sm text-gray-600">
            New file: {{ editDocumentFile.name }}
          </p>
        </div>
        <div>
          <Label class="mb-2 block text-sm font-medium"
            >Document Type <span class="text-red-500">*</span></Label
          >
          <Select v-model="editDocumentForm.document_type">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select document type" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="(label, value) in enums.document" :key="value" :value="value">
                {{ label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label class="mb-2 block text-sm font-medium">Status</Label>
          <Select v-model="editDocumentForm.compliance_status">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="(statusLabel, value) in enums.complianceStatuses"
                :key="value"
                :value="value"
              >
                {{ statusLabel }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium">Registration Number</label>
          <input
            v-model="editDocumentForm.registration_number"
            type="text"
            placeholder="Enter registration number (optional)"
            class="w-full rounded-md border p-2"
          />
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium">Validity</label>
          <input
            v-model="editDocumentForm.validity"
            type="date"
            class="w-full rounded-md border p-2"
          />
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium">Description</label>
          <textarea
            v-model="editDocumentForm.description"
            placeholder="Enter document description (optional)"
            rows="3"
            class="w-full rounded-md border p-2"
          ></textarea>
        </div>
      </div>
      <DialogFooter class="flex-col gap-2 sm:flex-row sm:gap-0">
        <Button class="w-full bg-blue-600 hover:bg-blue-700 sm:w-auto" @click="updateDocument"
          >Update Document</Button
        >
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <!-- ── Extended Profile Modal ── -->
  <Dialog v-model:open="metadataModalOpen">
    <DialogContent class="max-h-[95dvh] w-full overflow-y-auto sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>{{ activeMetadataTabLabel }}</DialogTitle>
        <DialogDescription
          >Update {{ activeMetadataTabLabel.toLowerCase() }} information</DialogDescription
        >
      </DialogHeader>
      <div class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
        <div v-for="field in activeMetadataSection.fields" :key="field.id" class="space-y-2">
          <Label :for="field.id" class="text-muted-foreground font-medium">{{ field.label }}</Label>
          <Select v-if="field.type === 'select'" :id="field.id" v-model="form[field.id]">
            <SelectTrigger class="h-8 w-full">
              <SelectValue :placeholder="'Select ' + field.label.toLowerCase()" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="(optionLabel, optionValue) in field.options"
                :key="optionValue"
                :value="optionValue"
              >
                {{ optionLabel }}
              </SelectItem>
            </SelectContent>
          </Select>
          <Input
            v-else
            :id="field.id"
            v-model="form[field.id]"
            :type="field.type"
            :step="field.step"
            :placeholder="'Enter ' + field.label.toLowerCase()"
            :class="[
              'h-8',
              fieldErrors[field.id] ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '',
            ]"
            @input="clearError(field.id)"
          />
          <p v-if="fieldErrors[field.id]" class="mt-1 text-sm text-red-500">
            {{
              Array.isArray(fieldErrors[field.id])
                ? fieldErrors[field.id][0]
                : fieldErrors[field.id]
            }}
          </p>
        </div>
      </div>
      <DialogFooter class="flex-col gap-2 sm:flex-row sm:gap-0">
        <Button type="button" variant="outline" class="sm:w-auto" @click="metadataModalOpen = false"
          >Cancel</Button
        >
        <Button class="bg-blue-600 hover:bg-blue-700 sm:w-auto" @click="metadataModalOpen = false">
          Save
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
