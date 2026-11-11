<script setup>
import { computed, ref, inject, watch, onMounted } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { Pencil, Trash2, Eye, EyeOff } from 'lucide-vue-next'
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
import { Badge } from '@/components/ui/badge'
import { cn, label, productNameLabel } from '@/lib/utils'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogClose,
} from '@/components/ui/dialog'
import { toastFlash } from '@/lib/flashToasts'
import { usePsgcAddress } from '@/composable/usePsgcAddress'
import { usePsgcCascade } from '@/composable/usePsgcCascade'
import {
  normalizeAnimalInventory,
  animalInventoryPayload,
  ANIMAL_INVENTORY_BREEDS,
} from '@/composable/useFarmerForm'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['view', 'edit', 'create'].includes(value),
  },
  center: {
    type: Object,
    required: true,
  },
  products: {
    type: Array,
    default: () => [],
  },
  productRegistrationDocuments: {
    type: Object,
    default: () => ({}),
  },
  enums: {
    type: Object,
    default: () => ({}),
  },
  documents: {
    type: Array,
    default: () => [],
  },
  projects: {
    type: Object,
    default: () => ({}),
  },
  class: {
    type: String,
    default: '',
  },
})

// Get modal state from parent via provide/inject
const modalState = inject('modalState', null)
const createDrafts = inject('createDrafts', null)
const nextDraftId = inject('nextDraftId', null)
const fieldErrors = ref({})
const isViewMode = computed(() => props.mode === 'view')
const isEditMode = computed(() => props.mode === 'edit')
const isCreateMode = computed(() => props.mode === 'create')
const isFormMode = computed(() => isEditMode.value || isCreateMode.value)

// On create the email address belongs to the Account Information section (it is the
// login email for the center account), so it is omitted from General Information here.
// It stays in General Information on edit, where there is no account section.
const generalInformationFields = computed(() =>
  [
    {
      id: 'code',
      label: 'Center Code',
      type: 'text',
      disabled: isEditMode.value,
      required: true,
      // centers.code is char(12)
      maxlength: 12,
    },
    { id: 'name', label: 'Name', type: 'text', required: true },
    { id: 'website', label: 'Website', type: 'text' },
    {
      id: 'date_of_establishment',
      label: 'Date of Establishment',
      type: 'date',
    },
    {
      id: 'date_assisted',
      label: 'Date Assisted',
      type: 'date',
    },
    { id: 'contact_person', label: 'Contact Person', type: 'text' },
    { id: 'position', label: 'Position', type: 'text' },
    { id: 'contact_number', label: 'Contact Number', type: 'tel' },
    { id: 'telephone_number', label: 'Telephone Number', type: 'tel' },
    { id: 'email_address', label: 'Email Address', type: 'email', omitOnCreate: true },
    {
      id: 'status',
      label: 'Status',
      type: 'select',
      options: props.enums?.statuses,
    },
  ].filter(field => !(field.omitOnCreate && isCreateMode.value))
)

const useDrafts = computed(() => isCreateMode.value && createDrafts)
const displayedProductionUnits = computed(() =>
  useDrafts.value ? createDrafts.productionUnits : props.center.production_units || []
)
const displayedConsolidationUnits = computed(() =>
  useDrafts.value ? createDrafts.consolidationUnits : props.center.consolidation_units || []
)
const displayedProcessingUnits = computed(() =>
  useDrafts.value ? createDrafts.processingUnits : props.center.processing_units || []
)
const displayedMarketingUnits = computed(() =>
  useDrafts.value ? createDrafts.marketingUnits : props.center.marketing_units || []
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

const selectableDocumentTypes = computed(() => {
  const documentTypes = props.enums?.document || {}
  return Object.fromEntries(
    Object.entries(documentTypes).filter(([value]) => value !== 'product_registration')
  )
})

const form = useForm({
  code: props.center.code || '',
  name: props.center.name || '',
  status: props.center.status || '',
  program: props.center.program || '',
  project_ids: (props.center.projects || []).map(p => p.id),
  website: props.center.website || '',
  date_of_establishment: props.center.metadata?.date_of_establishment || '',
  date_assisted: props.center.date_assisted
    ? String(props.center.date_assisted).substring(0, 10)
    : props.center.metadata?.date_assisted || '',
  contact_person: props.center.contact_person || '',
  position: props.center.metadata?.position || '',
  contact_number: props.center.contact_number || '',
  telephone_number: props.center.metadata?.telephone_number || '',
  email_address: props.center.email_address || '',
  username: '',
  password: '',
  password_confirmation: '',
  logo: null,
  street: props.center.address?.street || '',
  barangay: '',
  city: '',
  province: '',
  region: '',
  district: props.center.address?.district || '',
  zip_code: props.center.address?.zip_code || '',
  country: props.center.address?.country || 'Philippines',
  latitude: props.center.address?.latitude || '',
  longitude: props.center.address?.longitude || '',
  animal_inventory: normalizeAnimalInventory(props.center.animal_inventory),
})

const { hydrateAddressFields, addressRegionHasProvinces, districtDisabled, districts } =
  usePsgcCascade({
    form,
    psgc,
    withDistrict: true,
  })

watch(
  () => props.center,
  async newCenter => {
    form.code = newCenter.code || ''
    form.name = newCenter.name || ''
    form.status = newCenter.status || ''
    form.program = newCenter.program || ''
    form.project_ids = (newCenter.projects || []).map(p => p.id)
    form.website = newCenter.website || ''
    form.date_of_establishment = newCenter.metadata?.date_of_establishment || ''
    form.date_assisted = newCenter.date_assisted
      ? String(newCenter.date_assisted).substring(0, 10)
      : newCenter.metadata?.date_assisted || ''
    form.contact_person = newCenter.contact_person || ''
    form.position = newCenter.metadata?.position || ''
    form.contact_number = newCenter.contact_number || ''
    form.telephone_number = newCenter.metadata?.telephone_number || ''
    form.email_address = newCenter.email_address || ''

    form.street = newCenter.address?.street || ''
    await hydrateAddressFields(newCenter)
    form.zip_code = newCenter.address?.zip_code || ''
    form.country = newCenter.address?.country || 'Philippines'
    form.latitude = newCenter.address?.latitude || ''
    form.longitude = newCenter.address?.longitude || ''
    form.animal_inventory = normalizeAnimalInventory(newCenter.animal_inventory)

    logoPreview.value = newCenter.logo_url || null
    logoFile.value = null
  },
  { deep: true }
)

const clearError = id => {
  if (fieldErrors.value && fieldErrors.value[id]) {
    const copy = { ...fieldErrors.value }
    delete copy[id]
    fieldErrors.value = copy
  }
}

const firstError = errors => (Array.isArray(errors) ? errors[0] : errors)

onMounted(async () => {
  await loadPsgc()
  await hydrateAddressFields(props.center)
})

// Reactive state for table data (Value Chain Units and Products) - only used in edit mode
const productionUnits = ref((props.center.production_units || []).map(unit => ({ ...unit })))
const consolidationUnits = ref((props.center.consolidation_units || []).map(unit => ({ ...unit })))
const processingUnits = ref((props.center.processing_units || []).map(unit => ({ ...unit })))
const marketingUnits = ref((props.center.marketing_units || []).map(unit => ({ ...unit })))
const productsList = ref((props.products || []).map(product => ({ ...product })))

// Account Information password visibility state
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
const PASSWORD_MIN_LENGTH = 8
const USERNAME_MIN_LENGTH = 3

// Validate the Account Information fields locally so errors render inline,
// mirroring how the rest of this form surfaces validation beneath each field.
const validateAccountCredentials = () => {
  const errors = { ...fieldErrors.value }
  delete errors.email_address
  delete errors.username
  delete errors.password
  delete errors.password_confirmation

  if (!form.email_address) {
    errors.email_address = 'Email address is required.'
  }

  if (form.username && form.username.length < USERNAME_MIN_LENGTH) {
    errors.username = `Username must be at least ${USERNAME_MIN_LENGTH} characters.`
  }

  if (!form.password) {
    errors.password = 'Password is required.'
  } else if (form.password.length < PASSWORD_MIN_LENGTH) {
    errors.password = `Password must be at least ${PASSWORD_MIN_LENGTH} characters.`
  }

  if (!form.password_confirmation) {
    errors.password_confirmation = 'Confirm password is required.'
  } else if (form.password_confirmation !== form.password) {
    errors.password_confirmation = 'Passwords do not match.'
  }

  fieldErrors.value = errors

  return (
    !errors.email_address && !errors.username && !errors.password && !errors.password_confirmation
  )
}

// Logo upload state
const logoFile = ref(null)
const logoPreview = ref(props.center.logo_url || null)
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

watch(
  () => uploadForm.value.document_type,
  newVal => {
    if (newVal) uploadErrors.value.document_type = ''
  }
)

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

const removeDraft = (key, id) => {
  if (!createDrafts) return
  createDrafts[key] = createDrafts[key].filter(item => item.id !== id)
}

const deleteProduct = product => {
  if (useDrafts.value) {
    if (confirm(`Are you sure you want to remove ${productNameLabel(product, props.enums)}?`)) {
      removeDraft('products', product.id)
    }
    return
  }

  if (!product?.id) return

  if (confirm(`Are you sure you want to remove ${productNameLabel(product, props.enums)}?`)) {
    router.delete(route('centers.products.destroy', [props.center.id, product.id]), {
      preserveScroll: true,
    })
  }
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
    router.delete(route('centers.production-units.destroy', [props.center.id, unit.id]), {
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
    router.delete(route('centers.consolidation-units.destroy', [props.center.id, unit.id]), {
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
    router.delete(route('centers.processing-units.destroy', [props.center.id, unit.id]), {
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
    router.delete(route('centers.marketing-units.destroy', [props.center.id, unit.id]), {
      preserveScroll: true,
    })
  }
}

const handleEditDocumentFileChange = event => {
  editDocumentFile.value = event.target.files[0]
}

const viewDocument = doc => {
  if (doc?.file?.path) {
    window.open(`/${doc.file.path}`, '_blank')
  }
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

  if (!props.center?.code) {
    toastFlash({ error: 'Please save the center first before uploading documents.' })
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
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify({
        filename: uploadForm.value.file.name,
        contentType: uploadForm.value.file.type,
      }),
    })

    if (!presignedResponse.ok) {
      throw new Error(`Failed to get presigned URL: ${presignedResponse.status}`)
    }

    const presignedData = await presignedResponse.json()

    const uploadResponse = await fetch(presignedData.url, {
      method: 'PUT',
      headers: {
        'Content-Type': uploadForm.value.file.type,
      },
      body: uploadForm.value.file,
    })

    if (!uploadResponse.ok) {
      throw new Error(`File upload failed: ${uploadResponse.status}`)
    }

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
      { key: 'center', value: props.center.code },
      { key: 'document', value: uploadForm.value.document_type },
    ]
    tags.forEach((tag, index) => {
      formData.append(`tags[${index}][key]`, tag.key)
      formData.append(`tags[${index}][value]`, tag.value)
    })

    const documentResponse = await fetch('/documents', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        Accept: 'application/json',
      },
      body: formData,
    })

    if (!documentResponse.ok) {
      throw new Error(`Failed to create document: ${documentResponse.status}`)
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
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify({
        filename: uploadForm.value.file.name,
        contentType: uploadForm.value.file.type,
      }),
    })

    if (!presignedResponse.ok) {
      throw new Error(`Failed to get presigned URL: ${presignedResponse.status}`)
    }

    const presignedData = await presignedResponse.json()

    const uploadResponse = await fetch(presignedData.url, {
      method: 'PUT',
      headers: {
        'Content-Type': uploadForm.value.file.type,
      },
      body: uploadForm.value.file,
    })

    if (!uploadResponse.ok) {
      throw new Error(`File upload failed: ${uploadResponse.status}`)
    }

    createDrafts.documents.push({
      id: nextDraftId ? nextDraftId() : `draft-${Date.now()}`,
      title: uploadForm.value.file.name,
      file_name: uploadForm.value.file.name,
      file_key: presignedData.key,
      file_type: uploadForm.value.file.type,
      file_size: uploadForm.value.file.size,
      description: uploadForm.value.description,
      registration_number: uploadForm.value.registration_number,
      validity: uploadForm.value.validity,
      compliance_status: uploadForm.value.compliance_status,
      document_type: uploadForm.value.document_type,
      status: 'Pending',
    })

    toastFlash({ success: 'Document staged successfully.' })
    resetUploadForm()
  } catch (error) {
    toastFlash({ error: `Upload failed: ${error?.message ?? 'Unknown error'}` })
  } finally {
    uploading.value = false
  }
}

const removeDraftDocument = doc => {
  if (confirm(`Are you sure you want to remove ${doc.title}?`)) {
    removeDraft('documents', doc.id)
  }
}

const updateDocument = async () => {
  if (!editDocumentForm.value) return

  try {
    let presignedData = null

    if (editDocumentFile.value) {
      const presignedResponse = await fetch('/xhr/files-presigned-url', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          filename: editDocumentFile.value.name,
          contentType: editDocumentFile.value.type,
        }),
      })

      if (!presignedResponse.ok) {
        throw new Error(`Failed to get presigned URL: ${presignedResponse.status}`)
      }

      presignedData = await presignedResponse.json()

      const uploadResponse = await fetch(presignedData.url, {
        method: 'PUT',
        headers: {
          'Content-Type': editDocumentFile.value.type,
        },
        body: editDocumentFile.value,
      })

      if (!uploadResponse.ok) {
        throw new Error(`File upload failed: ${uploadResponse.status}`)
      }
    }

    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('title', editDocumentForm.value.title)
    formData.append('description', editDocumentForm.value.description)
    formData.append('registration_number', editDocumentForm.value.registration_number)
    formData.append('validity', editDocumentForm.value.validity)
    formData.append('compliance_status', editDocumentForm.value.compliance_status)
    formData.append('document_type', editDocumentForm.value.document_type || '')
    formData.append('center_code', props.center?.code || '')

    if (presignedData && editDocumentFile.value) {
      formData.append('file_name', editDocumentFile.value.name)
      formData.append('file_key', presignedData.key)
      formData.append('file_type', editDocumentFile.value.type)
      formData.append('file_size', editDocumentFile.value.size)
    }

    const response = await fetch(`/documents/${editDocumentForm.value.id}`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        Accept: 'application/json',
      },
      body: formData,
    })

    if (!response.ok) {
      throw new Error(`Failed to update document: ${response.status}`)
    }

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

const onSubmit = () => {
  if (!isFormMode.value) return

  if (isEditMode.value) {
    const filteredProductionUnits = productionUnits.value.filter(
      unit => unit.name && unit.name.trim() !== ''
    )
    const filteredConsolidationUnits = consolidationUnits.value.filter(
      unit => unit.name && unit.name.trim() !== ''
    )
    const filteredProcessingUnits = processingUnits.value.filter(
      unit => unit.name && unit.name.trim() !== ''
    )
    const filteredMarketingUnits = marketingUnits.value.filter(
      unit => unit.name && unit.name.trim() !== ''
    )
    const filteredProducts = productsList.value.filter(
      product =>
        product.code && product.code.trim() !== '' && product.name && product.name.trim() !== ''
    )

    const formData = new FormData()

    formData.append('_method', 'PUT')

    formData.append('code', form.code)
    formData.append('name', form.name)
    formData.append('status', form.status)
    formData.append('program', form.program)
    form.project_ids.forEach((id, i) => formData.append(`project_ids[${i}]`, id))
    formData.append('website', form.website)
    formData.append('date_of_establishment', form.date_of_establishment)
    formData.append('date_assisted', form.date_assisted)
    formData.append('contact_person', form.contact_person)
    formData.append('position', form.position)
    formData.append('contact_number', form.contact_number)
    formData.append('telephone_number', form.telephone_number)
    formData.append('email_address', form.email_address)
    formData.append('street', form.street)
    formData.append('barangay', getBarangayNameByCode(form.barangay) || form.barangay)
    formData.append('city', getCityNameByCode(form.city) || form.city)
    formData.append('province', getProvinceNameByCode(form.province) || form.province)
    formData.append('region', getRegionNameByCode(form.region) || form.region)
    formData.append('district', form.district)
    formData.append('zip_code', form.zip_code)
    formData.append('country', form.country)
    formData.append('latitude', form.latitude)
    formData.append('longitude', form.longitude)

    formData.append('production_units', JSON.stringify(filteredProductionUnits))
    formData.append('consolidation_units', JSON.stringify(filteredConsolidationUnits))
    formData.append('processing_units', JSON.stringify(filteredProcessingUnits))
    formData.append('marketing_units', JSON.stringify(filteredMarketingUnits))
    formData.append('products', JSON.stringify(filteredProducts))
    appendAnimalInventory(formData)

    if (logoFile.value) {
      formData.append('logo', logoFile.value)
    }

    router.post(route('centers.update', props.center.id), formData, {
      preserveScroll: true,
      onSuccess: () => {
        logoFile.value = null
      },
      onError: errors => {
        fieldErrors.value = errors
      },
    })
  }

  if (isCreateMode.value) {
    if (!validateAccountCredentials()) return

    const formData = new FormData()

    formData.append('code', form.code)
    formData.append('name', form.name)
    formData.append('status', form.status)
    formData.append('program', form.program)
    form.project_ids.forEach((id, i) => formData.append(`project_ids[${i}]`, id))
    formData.append('website', form.website)
    formData.append('date_of_establishment', form.date_of_establishment)
    formData.append('date_assisted', form.date_assisted)
    formData.append('contact_person', form.contact_person)
    formData.append('position', form.position)
    formData.append('contact_number', form.contact_number)
    formData.append('telephone_number', form.telephone_number)
    formData.append('email_address', form.email_address)
    formData.append('street', form.street)
    formData.append('barangay', getBarangayNameByCode(form.barangay) || form.barangay)
    formData.append('city', getCityNameByCode(form.city) || form.city)
    formData.append('province', getProvinceNameByCode(form.province) || form.province)
    formData.append('region', getRegionNameByCode(form.region) || form.region)
    formData.append('district', form.district)
    formData.append('zip_code', form.zip_code)
    formData.append('country', form.country)
    formData.append('latitude', form.latitude)
    formData.append('longitude', form.longitude)

    if (logoFile.value) {
      formData.append('logo', logoFile.value)
    }

    formData.append('password', form.password)
    formData.append('password_confirmation', form.password_confirmation)
    formData.append('username', form.username)

    if (createDrafts) {
      appendDrafts(formData)
    }
    appendAnimalInventory(formData)

    router.post(route('centers.store'), formData, {
      preserveScroll: true,
      onSuccess: () => {
        logoFile.value = null
      },
      onError: errors => {
        fieldErrors.value = errors
      },
    })
  }
}

const appendDrafts = formData => {
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
    if (product.registration_file_name) {
      formData.append(`${prefix}[registration_file_name]`, product.registration_file_name)
      formData.append(`${prefix}[registration_file_key]`, product.registration_file_key)
      formData.append(`${prefix}[registration_file_type]`, product.registration_file_type)
      formData.append(`${prefix}[registration_file_size]`, product.registration_file_size)
    }
  })

  createDrafts.documents.forEach((doc, index) => {
    const prefix = `documents[${index}]`
    formData.append(`${prefix}[title]`, doc.title)
    formData.append(`${prefix}[file_name]`, doc.file_name)
    formData.append(`${prefix}[file_key]`, doc.file_key)
    formData.append(`${prefix}[file_type]`, doc.file_type)
    formData.append(`${prefix}[file_size]`, doc.file_size)
    if (doc.description) formData.append(`${prefix}[description]`, doc.description)
    if (doc.registration_number)
      formData.append(`${prefix}[registration_number]`, doc.registration_number)
    if (doc.validity) formData.append(`${prefix}[validity]`, doc.validity)
    if (doc.compliance_status)
      formData.append(`${prefix}[compliance_status]`, doc.compliance_status)
    if (doc.document_type) formData.append(`${prefix}[document_type]`, doc.document_type)
  })
}

const getEnumOptions = enumObj => {
  if (!enumObj) return []
  return Object.entries(enumObj).map(([key, value]) => ({ key, value }))
}

const openEditModal = (type, id, category = null) => {
  if (!modalState) {
    toastFlash({
      error: 'Please save the center first before adding value chain units or products.',
    })
    return
  }
  modalState.value = { open: true, type, id, category }
}

// Track which Value Chain tab is active so the header Add button stays in sync
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

// Track which Product tab is active
const activeProductTab = ref('milk')
const productTabLabels = {
  milk: 'Milk',
  meat: 'Meat',
  manure: 'Manure',
  hide: 'Hide',
  others: 'Other',
}
const activeProductTabLabel = computed(() => productTabLabels[activeProductTab.value] || 'Product')

const hasAnimalInventory = computed(() => (props.center.animal_inventory || []).length > 0)

const animalInventoryTotal = computed(() =>
  (form.animal_inventory || []).reduce((sum, row) => sum + (Number(row.count) || 0), 0)
)

const appendAnimalInventory = formData => {
  animalInventoryPayload(form.animal_inventory).forEach((row, index) => {
    Object.entries(row).forEach(([key, value]) => {
      if (value === null || value === undefined) return
      formData.append(`animal_inventory[${index}][${key}]`, value)
    })
  })
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
            :src="logoPreview || center.logo_url"
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
            <dl v-if="isViewMode" class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
              <dt class="text-muted-foreground font-medium">Code</dt>
              <dd class="sm:text-right">{{ center.code || 'N/A' }}</dd>
              <dt class="text-muted-foreground font-medium">Status</dt>
              <dd class="sm:text-right">
                {{ label(enums.statuses, center.status) || 'N/A' }}
              </dd>
              <dt class="text-muted-foreground font-medium">Address</dt>
              <dd class="wrap-break-word sm:text-right">
                {{ normalizedAddress(center.address) }}
              </dd>
              <dt class="text-muted-foreground font-medium">Website</dt>
              <dd class="break-all sm:text-right">{{ center.website || 'N/A' }}</dd>
              <dt class="text-muted-foreground font-medium">Date of Establishment</dt>
              <dd class="sm:text-right">
                {{ center.metadata?.date_of_establishment || 'N/A' }}
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
              <dd class="sm:text-right">{{ center.metadata?.position || 'N/A' }}</dd>
              <dt class="text-muted-foreground font-medium">Contact Number</dt>
              <dd class="sm:text-right">{{ center.contact_number || 'N/A' }}</dd>
              <dt class="text-muted-foreground font-medium">Telephone Number</dt>
              <dd class="sm:text-right">
                {{ center.metadata?.telephone_number || 'N/A' }}
              </dd>
              <dt class="text-muted-foreground font-medium">Email Address</dt>
              <dd class="break-all sm:text-right">{{ center.email_address || 'N/A' }}</dd>
            </dl>

            <!-- Edit / Create mode: input grid -->
            <div
              v-if="isFormMode"
              class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2 lg:grid-cols-4"
            >
              <div v-for="field in generalInformationFields" :key="field.id" class="space-y-2">
                <Label :for="field.id" class="text-muted-foreground font-medium">{{
                  field.label
                }}</Label>
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
                  :placeholder="field.disabled ? '' : 'Enter ' + field.label.toLowerCase()"
                  :disabled="field.disabled || false"
                  :required="field.required || false"
                  :maxlength="field.maxlength || undefined"
                  :class="[
                    fieldErrors[field.id]
                      ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                      : '',
                  ]"
                  @input="clearError(field.id)"
                />
                <p v-if="fieldErrors[field.id]" class="mt-1 text-sm text-red-500">
                  {{ firstError(fieldErrors[field.id]) }}
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
                      {{ firstError(fieldErrors.region) }}
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
                      {{ firstError(fieldErrors.province) }}
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
                      {{ firstError(fieldErrors.city) }}
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
                      {{ firstError(fieldErrors.barangay) }}
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
                      {{ firstError(fieldErrors.district) }}
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
                      {{ firstError(fieldErrors.street) }}
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
                      <SelectTrigger class="h-8 w-full">
                        <SelectValue placeholder="Select country" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="Philippines">Philippines</SelectItem>
                      </SelectContent>
                    </Select>
                    <p v-if="fieldErrors.country" class="mt-1 text-sm text-red-500">
                      {{ firstError(fieldErrors.country) }}
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
                      {{ firstError(fieldErrors.latitude) }}
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
                      {{ firstError(fieldErrors.longitude) }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <div v-if="isFormMode" class="mt-4 flex justify-end">
              <Button :disabled="form.processing" type="submit">
                {{
                  form.processing ? 'Saving...' : isCreateMode ? 'Create Center' : 'Save Changes'
                }}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>

    <!-- ── Account Information ── -->
    <Card v-if="isCreateMode">
      <CardHeader>
        <CardTitle>Account Information</CardTitle>
        <CardDescription>Login credentials for the center account</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
          <div class="space-y-2">
            <Label for="account_email" class="text-muted-foreground font-medium">
              Email Address
            </Label>
            <Input
              id="account_email"
              v-model="form.email_address"
              type="email"
              autocomplete="email"
              placeholder="Enter email address"
              required
              :class="[
                'h-8',
                fieldErrors.email_address
                  ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                  : '',
              ]"
              @input="clearError('email_address')"
            />
            <p v-if="fieldErrors.email_address" class="mt-1 text-sm text-red-500">
              {{ firstError(fieldErrors.email_address) }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="username" class="text-muted-foreground font-medium">Username</Label>
            <Input
              id="username"
              v-model="form.username"
              type="text"
              autocomplete="username"
              placeholder="Defaults to the center code"
              :class="[
                'h-8',
                fieldErrors.username
                  ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                  : '',
              ]"
              @input="clearError('username')"
            />
            <p v-if="fieldErrors.username" class="mt-1 text-sm text-red-500">
              {{ firstError(fieldErrors.username) }}
            </p>
            <p v-else class="text-muted-foreground mt-1 text-xs">
              Optional. Defaults to the lowercased center code.
            </p>
          </div>

          <div class="space-y-2">
            <Label for="password" class="text-muted-foreground font-medium">Password</Label>
            <div class="relative">
              <Input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="new-password"
                :minlength="PASSWORD_MIN_LENGTH"
                :class="[
                  'h-8 pr-8',
                  fieldErrors.password
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                    : '',
                ]"
                @input="clearError('password')"
              />
              <button
                type="button"
                class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute top-1/2 right-1 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded focus-visible:ring-1 focus-visible:outline-none"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                :aria-pressed="showPassword"
                :aria-controls="'password'"
                tabindex="0"
                @click="showPassword = !showPassword"
              >
                <EyeOff v-if="showPassword" class="h-4 w-4" aria-hidden="true" />
                <Eye v-else class="h-4 w-4" aria-hidden="true" />
              </button>
            </div>
            <p v-if="fieldErrors.password" class="mt-1 text-sm text-red-500">
              {{ firstError(fieldErrors.password) }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="password_confirmation" class="text-muted-foreground font-medium">
              Confirm Password
            </Label>
            <div class="relative">
              <Input
                id="password_confirmation"
                v-model="form.password_confirmation"
                :type="showPasswordConfirmation ? 'text' : 'password'"
                autocomplete="new-password"
                :minlength="PASSWORD_MIN_LENGTH"
                :class="[
                  'h-8 pr-8',
                  fieldErrors.password_confirmation
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                    : '',
                ]"
                @input="clearError('password_confirmation')"
              />
              <button
                type="button"
                class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute top-1/2 right-1 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded focus-visible:ring-1 focus-visible:outline-none"
                :aria-label="showPasswordConfirmation ? 'Hide password' : 'Show password'"
                :aria-pressed="showPasswordConfirmation"
                :aria-controls="'password_confirmation'"
                tabindex="0"
                @click="showPasswordConfirmation = !showPasswordConfirmation"
              >
                <EyeOff v-if="showPasswordConfirmation" class="h-4 w-4" aria-hidden="true" />
                <Eye v-else class="h-4 w-4" aria-hidden="true" />
              </button>
            </div>
            <p v-if="fieldErrors.password_confirmation" class="mt-1 text-sm text-red-500">
              {{ firstError(fieldErrors.password_confirmation) }}
            </p>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- ── Value Chain Units ── -->
    <Card>
      <CardHeader>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <CardTitle>Value Chain Units</CardTitle>
            <CardDescription>Contributors in the chain</CardDescription>
          </div>
          <!-- Add button moves with the active tab -->
          <Button
            v-if="isFormMode"
            class="bg-blue-600 hover:bg-blue-700"
            type="button"
            @click="openEditModal(activeValueChainTab + '-units', null)"
          >
            Add {{ activeValueChainTabLabel }} Unit
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <Tabs v-model="activeValueChainTab" default-value="production" :class="cn('bordered')">
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
                      {{ label(enums.herdType, production.herd_type) }} • Established:
                      {{ production.date_of_establishment || 'N/A' }}
                    </p>
                  </div>
                  <div class="flex shrink-0 gap-1">
                    <Button
                      v-if="isFormMode && production.id"
                      variant="ghost"
                      size="sm"
                      @click="openEditModal('production-units', production.id)"
                    >
                      <Pencil class="h-4 w-4" />
                    </Button>
                    <Button
                      v-if="isFormMode && production.id"
                      variant="ghost"
                      size="sm"
                      @click="deleteProductionUnit(production)"
                    >
                      <Trash2 class="h-4 w-4 text-red-600" />
                    </Button>
                  </div>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <dt class="text-muted-foreground font-medium">Contact Person</dt>
                  <dd class="font-medium sm:text-right">
                    {{ production.contact_person || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Position</dt>
                  <dd class="font-medium sm:text-right">
                    {{ production.position || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="font-medium sm:text-right">
                    {{ production.contact_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Telephone Number</dt>
                  <dd class="font-medium sm:text-right">
                    {{ production.telephone_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Email Address</dt>
                  <dd class="font-medium break-all sm:text-right">
                    {{ production.email_address || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Website</dt>
                  <dd class="font-medium break-all sm:text-right">
                    {{ production.website || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>
                  <dd class="font-medium sm:text-right">
                    {{ production.number_of_staff_male || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>
                  <dd class="font-medium sm:text-right">
                    {{ production.number_of_staff_female || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>
                  <dd class="font-medium sm:text-right">
                    {{ production.avg_salary_of_staff || 'N/A' }}
                  </dd>
                </dl>
              </div>
              <div
                v-if="!displayedProductionUnits.length"
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
                    <h4 class="text-lg font-semibold">
                      {{ consolidation.name || 'N/A' }}
                    </h4>
                    <p class="text-muted-foreground text-sm">
                      {{ label(enums.consolidationCategory, consolidation.category) }} •
                      Established: {{ consolidation.date_of_establishment || 'N/A' }}
                    </p>
                  </div>
                  <div class="flex shrink-0 gap-1">
                    <Button
                      v-if="isFormMode && consolidation.id"
                      variant="ghost"
                      size="sm"
                      @click="openEditModal('consolidation-units', consolidation.id)"
                    >
                      <Pencil class="h-4 w-4" />
                    </Button>
                    <Button
                      v-if="isFormMode && consolidation.id"
                      variant="ghost"
                      size="sm"
                      @click="deleteConsolidationUnit(consolidation)"
                    >
                      <Trash2 class="h-4 w-4 text-red-600" />
                    </Button>
                  </div>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <dt class="text-muted-foreground font-medium">Contact Person</dt>
                  <dd class="font-medium sm:text-right">
                    {{ consolidation.contact_person || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Position</dt>
                  <dd class="font-medium sm:text-right">
                    {{ consolidation.position || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="font-medium sm:text-right">
                    {{ consolidation.contact_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Telephone Number</dt>
                  <dd class="font-medium sm:text-right">
                    {{ consolidation.telephone_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Email Address</dt>
                  <dd class="font-medium break-all sm:text-right">
                    {{ consolidation.email_address || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Website</dt>
                  <dd class="font-medium break-all sm:text-right">
                    {{ consolidation.website || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>
                  <dd class="font-medium sm:text-right">
                    {{ consolidation.number_of_staff_male || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>
                  <dd class="font-medium sm:text-right">
                    {{ consolidation.number_of_staff_female || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>
                  <dd class="font-medium sm:text-right">
                    {{ consolidation.avg_salary_of_staff || 'N/A' }}
                  </dd>
                </dl>
              </div>
              <div
                v-if="!displayedConsolidationUnits.length"
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
                      {{ label(enums.processingCategory, processing.category) }} • Established:
                      {{ processing.date_of_establishment || 'N/A' }}
                    </p>
                  </div>
                  <div class="flex shrink-0 gap-1">
                    <Button
                      v-if="isFormMode && processing.id"
                      variant="ghost"
                      size="sm"
                      @click="openEditModal('processing-units', processing.id)"
                    >
                      <Pencil class="h-4 w-4" />
                    </Button>
                    <Button
                      v-if="isFormMode && processing.id"
                      variant="ghost"
                      size="sm"
                      @click="deleteProcessingUnit(processing)"
                    >
                      <Trash2 class="h-4 w-4 text-red-600" />
                    </Button>
                  </div>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <dt class="text-muted-foreground font-medium">Contact Person</dt>
                  <dd class="font-medium sm:text-right">
                    {{ processing.contact_person || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Position</dt>
                  <dd class="font-medium sm:text-right">
                    {{ processing.position || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="font-medium sm:text-right">
                    {{ processing.contact_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Telephone Number</dt>
                  <dd class="font-medium sm:text-right">
                    {{ processing.telephone_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Email Address</dt>
                  <dd class="font-medium break-all sm:text-right">
                    {{ processing.email_address || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Website</dt>
                  <dd class="font-medium break-all sm:text-right">
                    {{ processing.website || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>
                  <dd class="font-medium sm:text-right">
                    {{ processing.number_of_staff_male || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>
                  <dd class="font-medium sm:text-right">
                    {{ processing.number_of_staff_female || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>
                  <dd class="font-medium sm:text-right">
                    {{ processing.avg_salary_of_staff || 'N/A' }}
                  </dd>
                </dl>
              </div>
              <div
                v-if="!displayedProcessingUnits.length"
                class="text-muted-foreground py-8 text-center"
              >
                No processing units found.
              </div>
            </div>
          </TabsContent>

          <!-- Marketing -->
          <TabsContent value="marketing">
            <div class="space-y-4">
              <div
                v-for="(marketing, index) in displayedMarketingUnits"
                :key="marketing.id || index"
                class="rounded-lg border p-4"
              >
                <div class="mb-4 flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <h4 class="text-lg font-semibold">{{ marketing.name || 'N/A' }}</h4>
                    <p class="text-muted-foreground text-sm">
                      Established: {{ marketing.date_of_establishment || 'N/A' }}
                    </p>
                  </div>
                  <div class="flex shrink-0 gap-1">
                    <Button
                      v-if="isFormMode && marketing.id"
                      variant="ghost"
                      size="sm"
                      @click="openEditModal('marketing-units', marketing.id)"
                    >
                      <Pencil class="h-4 w-4" />
                    </Button>
                    <Button
                      v-if="isFormMode && marketing.id"
                      variant="ghost"
                      size="sm"
                      @click="deleteMarketingUnit(marketing)"
                    >
                      <Trash2 class="h-4 w-4 text-red-600" />
                    </Button>
                  </div>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                  <dt class="text-muted-foreground font-medium">Contact Person</dt>
                  <dd class="font-medium sm:text-right">
                    {{ marketing.contact_person || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Position</dt>
                  <dd class="font-medium sm:text-right">
                    {{ marketing.position || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Contact Number</dt>
                  <dd class="font-medium sm:text-right">
                    {{ marketing.contact_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Telephone Number</dt>
                  <dd class="font-medium sm:text-right">
                    {{ marketing.telephone_number || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Email Address</dt>
                  <dd class="font-medium break-all sm:text-right">
                    {{ marketing.email_address || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Website</dt>
                  <dd class="font-medium break-all sm:text-right">
                    {{ marketing.website || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Male)</dt>
                  <dd class="font-medium sm:text-right">
                    {{ marketing.number_of_staff_male || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Number of Staff (Female)</dt>
                  <dd class="font-medium sm:text-right">
                    {{ marketing.number_of_staff_female || 'N/A' }}
                  </dd>
                  <dt class="text-muted-foreground font-medium">Average Salary of Staff</dt>
                  <dd class="font-medium sm:text-right">
                    {{ marketing.avg_salary_of_staff || 'N/A' }}
                  </dd>
                </dl>
              </div>
              <div
                v-if="!displayedMarketingUnits.length"
                class="text-muted-foreground py-8 text-center"
              >
                No marketing units found.
              </div>
            </div>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>

    <!-- ── Animal Inventory ── -->
    <Card>
      <CardHeader>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <CardTitle>Animal Inventory</CardTitle>
            <CardDescription>Count of animals per classification</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <!-- View mode: read-only table -->
        <div v-if="isViewMode">
          <div v-if="hasAnimalInventory" class="overflow-x-auto rounded-lg border">
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
                <TableRow
                  v-for="row in normalizeAnimalInventory(center.animal_inventory)"
                  :key="row.classification"
                >
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
          <div v-else class="text-muted-foreground py-8 text-center">
            No animal inventory data found.
          </div>
        </div>

        <!-- Edit / Create mode: editable table -->
        <div v-else class="space-y-4">
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
                <TableRow v-for="(row, index) in form.animal_inventory" :key="row.classification">
                  <TableCell class="font-medium">{{ row.classification }}</TableCell>
                  <TableCell>
                    <Input
                      v-model.number="form.animal_inventory[index].count"
                      type="number"
                      min="0"
                      class="w-24"
                    />
                  </TableCell>
                  <TableCell>
                    <Select v-model="form.animal_inventory[index].breed">
                      <SelectTrigger class="w-32"
                        ><SelectValue placeholder="Select"
                      /></SelectTrigger>
                      <SelectContent>
                        <SelectItem
                          v-for="breed in ANIMAL_INVENTORY_BREEDS"
                          :key="breed"
                          :value="breed"
                        >
                          {{ breed }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </TableCell>
                  <TableCell>
                    <Input v-model="form.animal_inventory[index].remarks" />
                  </TableCell>
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
        </div>
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
            <CardDescription>Uploaded files related to the center</CardDescription>
          </div>
          <Button
            v-if="isFormMode"
            class="bg-blue-600 hover:bg-blue-700"
            @click="
              () => {
                if (!isCreateMode && !center?.code) {
                  toastFlash({
                    error: 'Please save the center first before uploading documents.',
                  })
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
                <TableHead>Status</TableHead>
                <TableHead>Uploaded By</TableHead>
                <TableHead>Uploaded At</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="document in displayedDocuments" :key="document.id">
                <TableCell>
                  <span v-if="document.status === 'Pending'" class="mr-2">
                    <Badge class="bg-amber-500">Pending</Badge>
                  </span>
                  <span v-if="document.tags && document.tags.length > 0">
                    {{
                      enums.document?.[document.tags.find(t => t.key === 'document')?.value] ||
                      document.tags.find(t => t.key === 'document')?.value ||
                      '-'
                    }}
                  </span>
                  <span v-else-if="document.document_type">{{ document.document_type }}</span>
                  <span v-else>-</span>
                </TableCell>
                <TableCell>
                  <button
                    v-if="document.file?.path"
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
                <TableCell>{{
                  label(enums.complianceStatuses, document.compliance_status) || '-'
                }}</TableCell>
                <TableCell>{{
                  document.file?.uploaded_by?.name || document.file?.uploadedBy?.name || '-'
                }}</TableCell>
                <TableCell>{{
                  document.file?.created_at
                    ? new Date(document.file.created_at).toLocaleDateString()
                    : '-'
                }}</TableCell>
                <TableCell>
                  <template v-if="document.status === 'Pending'">
                    <Button variant="ghost" size="sm" @click="removeDraftDocument(document)">
                      <Trash2 class="h-4 w-4 text-red-600" />
                    </Button>
                  </template>
                  <template v-else>
                    <Button variant="ghost" size="sm" @click="openEditDocumentModal(document)">
                      <Pencil class="h-4 w-4" />
                    </Button>
                  </template>
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
                enums.document?.[selectedDocument.tags?.find(t => t.key === 'document')?.value] ||
                selectedDocument.tags?.find(t => t.key === 'document')?.value ||
                '-'
              }}
            </span>
          </div>
          <div>
            <span class="font-semibold">Uploaded By:</span>
            <span class="ml-2">{{
              selectedDocument.file?.uploaded_by?.name || selectedDocument.file?.uploadedBy?.name
            }}</span>
          </div>
          <div v-if="selectedDocument.registration_number">
            <span class="font-semibold">Registration Number:</span>
            <span class="ml-2">{{ selectedDocument.registration_number }}</span>
          </div>
          <div v-if="selectedDocument.validity">
            <span class="font-semibold">Validity:</span>
            <span class="ml-2">{{ new Date(selectedDocument.validity).toLocaleDateString() }}</span>
          </div>
          <div v-if="selectedDocument.compliance_status">
            <span class="font-semibold">Status:</span>
            <span class="ml-2">{{
              label(enums.complianceStatuses, selectedDocument.compliance_status)
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
        <DialogDescription>
          Add a new document for {{ center.name || 'this center' }}
        </DialogDescription>
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
              <SelectItem
                v-for="(label, value) in selectableDocumentTypes"
                :key="value"
                :value="value"
              >
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
              <SelectItem
                v-for="(label, value) in selectableDocumentTypes"
                :key="value"
                :value="value"
              >
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
        <Button class="w-full bg-blue-600 hover:bg-blue-700 sm:w-auto" @click="updateDocument">
          Update Document
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
