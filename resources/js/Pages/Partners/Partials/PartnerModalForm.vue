<script setup>
import { computed, watch, ref, inject, nextTick } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const props = defineProps({
  partner: {
    type: Object,
    required: true,
  },
  products: {
    type: Array,
    default: () => [],
  },
  enums: {
    type: Object,
    default: () => ({}),
  },
})

const getEnumOptions = enumObj => {
  if (!enumObj) return []
  return Object.entries(enumObj).map(([key, value]) => ({ key, value }))
}

const meatProductGroups = computed(() =>
  Object.entries(props.enums?.meatProductTypeCategories || {}).map(([key, group]) => ({
    key,
    label: group?.label ?? key,
    products: Array.isArray(group?.products) ? group.products : [],
  }))
)

const ungroupedMeatOptions = computed(() => {
  const grouped = new Set(meatProductGroups.value.flatMap(group => group.products))
  return getEnumOptions(props.enums?.meatProductType).filter(opt => !grouped.has(opt.key))
})

const modalState = inject('modalState', ref({ open: false, type: null, id: null }))
const createDrafts = inject('createDrafts', null)
const nextDraftId = inject('nextDraftId', null)

const isCreateMode = computed(() => !props.partner?.id)

const editType = computed(() => {
  const type = modalState.value?.type
  if (type === 'production-units') return 'production'
  if (type === 'consolidation-units') return 'consolidation'
  if (type === 'processing-units') return 'processing'
  if (type === 'marketing-units') return 'marketing'
  if (type === 'products') return 'product'
  return null
})

const draftStoreKey = computed(() => {
  const map = {
    production: 'productionUnits',
    consolidation: 'consolidationUnits',
    processing: 'processingUnits',
    marketing: 'marketingUnits',
    product: 'products',
  }
  return map[editType.value]
})

const editId = computed(() => {
  if (modalState.value?.id === null || modalState.value?.id === undefined) return null
  if (modalState.value?.id === '') return null
  if (typeof modalState.value.id === 'string' && modalState.value.id.startsWith('draft-')) {
    return modalState.value.id
  }
  return parseInt(modalState.value.id)
})

const isOpen = computed(() => {
  return modalState.value?.open === true && editType.value !== null
})

const currentItem = computed(() => {
  if (!editType.value || !editId.value) return null
  if (isCreateMode.value && createDrafts) {
    const list = createDrafts[draftStoreKey.value]
    return list?.find(item => item.id === editId.value) || null
  }
  const type = editType.value
  if (type === 'production') return props.partner.production_units?.find(u => u.id === editId.value)
  if (type === 'consolidation')
    return props.partner.consolidation_units?.find(u => u.id === editId.value)
  if (type === 'processing') return props.partner.processing_units?.find(u => u.id === editId.value)
  if (type === 'marketing') return props.partner.marketing_units?.find(u => u.id === editId.value)
  if (type === 'product') return props.products?.find(p => p.id === editId.value)
  return null
})

const form = useForm({
  name: '',
  category: '',
  herd_type: '',
  date_of_establishment: '',
  contact_person: '',
  position: '',
  contact_number: '',
  telephone_number: '',
  email_address: '',
  website: '',
  number_of_staff_male: 0,
  number_of_staff_female: 0,
  avg_salary_of_staff: 0,
  code: '',
  sub_category: '',
  product_type: '',
  product_registration: '',
  size: '',
  cost_per_qty: 0,
  details: '',
  registration_file_name: null,
  registration_file_key: null,
  registration_file_type: null,
  registration_file_size: null,
})

const registrationFile = ref(null)
const page = usePage()
const csrfToken = page.props.csrf_token

const handleRegistrationFileChange = event => {
  registrationFile.value = event.target.files?.[0] || null
}

watch(
  [() => isOpen.value, () => currentItem.value],
  ([open, item]) => {
    if (open && item) {
      nextTick(() => {
        const fields = Object.keys(form.data())
        fields.forEach(key => {
          form[key] = item[key] ?? (typeof form[key] === 'number' ? 0 : '')
        })
        form.registration_file_name = null
        form.registration_file_key = null
        form.registration_file_type = null
        form.registration_file_size = null
        registrationFile.value = null
        form.clearErrors()
      })
    } else if (open && editId.value === null) {
      nextTick(() => {
        form.reset()
        form.clearErrors()
        registrationFile.value = null
        // Set category from modalState if provided (for products)
        if (editType.value === 'product' && modalState.value?.category) {
          form.category = modalState.value.category
        }
      })
    } else if (!open) {
      form.reset()
      registrationFile.value = null
    }
  },
  { immediate: true }
)

const getTitle = () => {
  const titles = {
    production: editId.value ? 'Edit Production Unit' : 'Add Production Unit',
    consolidation: editId.value ? 'Edit Consolidation Unit' : 'Add Consolidation Unit',
    processing: editId.value ? 'Edit Processing Unit' : 'Add Processing Unit',
    marketing: editId.value ? 'Edit Marketing Unit' : 'Add Marketing Unit',
    product: editId.value ? 'Edit Product' : 'Add Product',
  }
  return titles[editType.value] || 'Edit'
}

const getProductTitle = () => {
  if (editType.value !== 'product') return ''
  const categoryLabels = {
    milk: 'Milk',
    meat: 'Meat',
    manure: 'Manure',
    hide: 'Hide',
  }
  const categoryLabel = categoryLabels[form.category] || ''
  return editId.value ? 'Edit Product' : `Add ${categoryLabel} Product`
}

const getDescription = () => {
  if (!editType.value) return ''
  return editId.value
    ? `Update the ${editType.value} information below.`
    : `Add the ${editType.value} information below.`
}

const getUpdateUrl = () => {
  const base = `/partners/${props.partner.id}`
  const id = editId.value
  const routes = {
    production: id ? `${base}/production-units/${id}` : `${base}/production-units`,
    consolidation: id ? `${base}/consolidation-units/${id}` : `${base}/consolidation-units`,
    processing: id ? `${base}/processing-units/${id}` : `${base}/processing-units`,
    marketing: id ? `${base}/marketing-units/${id}` : `${base}/marketing-units`,
    product: id ? `${base}/products/${id}` : `${base}/products`,
  }
  return routes[editType.value] || ''
}

const close = () => {
  form.reset()
  form.clearErrors()
  registrationFile.value = null
  modalState.value = { open: false, type: null, id: null }
}

const buildDraftPayload = () => {
  const data = form.data()
  if (editType.value === 'product') {
    return Object.fromEntries(
      Object.entries({
        code: data.code,
        name: data.product_type,
        category: data.category,
        sub_category: data.sub_category,
        product_type: data.product_type,
        product_registration: data.product_registration,
        size: data.size,
        cost_per_qty: data.cost_per_qty,
        details: data.details,
        registration_file_name: data.registration_file_name,
        registration_file_key: data.registration_file_key,
        registration_file_type: data.registration_file_type,
        registration_file_size: data.registration_file_size,
      }).filter(([, value]) => value !== undefined && value !== null)
    )
  }

  const payload = {
    name: data.name,
    date_of_establishment: data.date_of_establishment,
    contact_person: data.contact_person,
    position: data.position,
    contact_number: data.contact_number,
    telephone_number: data.telephone_number,
    email_address: data.email_address,
    website: data.website,
    number_of_staff_male: data.number_of_staff_male,
    number_of_staff_female: data.number_of_staff_female,
    avg_salary_of_staff: data.avg_salary_of_staff,
  }
  if (editType.value !== 'marketing') {
    payload.category = data.category
  }
  if (editType.value === 'production') {
    payload.herd_type = data.herd_type
  }
  return Object.fromEntries(Object.entries(payload).filter(([, value]) => value !== undefined))
}

const submitDraft = () => {
  if (!createDrafts || !draftStoreKey.value) {
    close()
    return
  }
  const list = createDrafts[draftStoreKey.value]
  const payload = { ...buildDraftPayload() }
  if (editId.value) {
    const index = list.findIndex(item => item.id === editId.value)
    if (index !== -1) {
      list[index] = { ...list[index], ...payload, id: editId.value }
    }
  } else {
    list.push({ ...payload, id: nextDraftId ? nextDraftId() : `draft-${Date.now()}` })
  }
  close()
}

const uploadRegistrationPdfIfNeeded = async () => {
  if (editType.value !== 'product') return
  if (!registrationFile.value) return

  if (!registrationFile.value.type?.includes('pdf')) {
    throw new Error('Registration file must be a PDF.')
  }

  const presignedResponse = await fetch('/xhr/files-presigned-url', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({
      filename: registrationFile.value.name,
      contentType: registrationFile.value.type,
    }),
  })

  if (!presignedResponse.ok) {
    throw new Error(`Failed to get presigned URL: ${presignedResponse.status}`)
  }

  const presignedData = await presignedResponse.json()

  const uploadResponse = await fetch(presignedData.url, {
    method: 'PUT',
    headers: {
      'Content-Type': registrationFile.value.type,
    },
    body: registrationFile.value,
  })

  if (!uploadResponse.ok) {
    throw new Error(`File upload failed: ${uploadResponse.status}`)
  }

  form.registration_file_name = registrationFile.value.name
  form.registration_file_key = presignedData.key
  form.registration_file_type = registrationFile.value.type
  form.registration_file_size = registrationFile.value.size
}

const submit = async () => {
  if (editType.value === 'product' && !form.sub_category) {
    form.sub_category = null
  }

  // Map product_type to name for products
  if (editType.value === 'product') {
    form.name = form.product_type
  }

  if (isCreateMode.value) {
    try {
      await uploadRegistrationPdfIfNeeded()
    } catch (error) {
      toast.error(`Save failed: ${error.message}`)
      return
    }
    submitDraft()
    return
  }
  const url = getUpdateUrl()
  const action = editId.value ? 'updated' : 'created'
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      close()
      router.reload({ only: ['partner', 'products', 'productRegistrationDocuments'] })
    },
    onError: errors => {
      Object.entries(errors).forEach(([field, msg]) => toast.error(`${field}: ${msg}`))
    },
  }

  try {
    await uploadRegistrationPdfIfNeeded()
    if (editId.value) {
      form.put(url, options)
    } else {
      form.post(url, options)
    }
  } catch (error) {
    toast.error(`Save failed: ${error.message}`)
  }
}
</script>

<template>
  <Dialog :open="isOpen" @update:open="val => !val && close()">
    <DialogContent class="flex h-fit max-h-[90vh] w-[95vw] max-w-4xl flex-col overflow-hidden p-0">
      <DialogHeader class="shrink-0 border-b p-6">
        <DialogTitle>{{ editType === 'product' ? getProductTitle() : getTitle() }}</DialogTitle>
        <DialogDescription>{{ getDescription() }}</DialogDescription>
      </DialogHeader>

      <form v-if="isOpen" class="flex flex-col overflow-hidden" @submit.prevent="submit">
        <div class="max-h-[60vh] overflow-y-auto p-6">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <template v-if="editType === 'product'">
              <div class="space-y-2">
                <Label>Category</Label>
                <Select v-model="form.category">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="opt in getEnumOptions(enums.productCategory)"
                      :key="opt.key"
                      :value="opt.key"
                      >{{ opt.value }}</SelectItem
                    >
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Product Name</Label>
                <Select v-if="form.category === 'milk'" v-model="form.product_type">
                  <SelectTrigger><SelectValue placeholder="Select product name" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="opt in getEnumOptions(enums.milkProductType)"
                      :key="opt.key"
                      :value="opt.key"
                      >{{ opt.value }}</SelectItem
                    >
                  </SelectContent>
                </Select>
                <Select v-else-if="form.category === 'meat'" v-model="form.product_type">
                  <SelectTrigger><SelectValue placeholder="Select product name" /></SelectTrigger>
                  <SelectContent>
                    <template v-if="meatProductGroups.length">
                      <SelectGroup
                        v-for="group in meatProductGroups"
                        :key="group.key"
                        :value="group.key"
                      >
                        <SelectLabel>{{ group.label }}</SelectLabel>
                        <SelectItem
                          v-for="opt in getEnumOptions(enums.meatProductType).filter(item =>
                            group.products.includes(item.key)
                          )"
                          :key="opt.key"
                          :value="opt.key"
                          >{{ opt.value }}</SelectItem
                        >
                      </SelectGroup>
                    </template>
                    <SelectItem
                      v-for="opt in ungroupedMeatOptions"
                      :key="opt.key"
                      :value="opt.key"
                      >{{ opt.value }}</SelectItem
                    >
                  </SelectContent>
                </Select>
                <Select v-else-if="form.category === 'manure'" v-model="form.product_type">
                  <SelectTrigger><SelectValue placeholder="Select product name" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="opt in getEnumOptions(enums.manureProductType)"
                      :key="opt.key"
                      :value="opt.key"
                      >{{ opt.value }}</SelectItem
                    >
                  </SelectContent>
                </Select>
                <Select v-else-if="form.category === 'hide'" v-model="form.product_type">
                  <SelectTrigger><SelectValue placeholder="Select product name" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="opt in getEnumOptions(enums.hideProductType)"
                      :key="opt.key"
                      :value="opt.key"
                      >{{ opt.value }}</SelectItem
                    >
                  </SelectContent>
                </Select>
                <Input v-else v-model="form.product_type" placeholder="Enter product name" />
              </div>
              <div class="space-y-2">
                <Label>Product Registration</Label>
                <Input v-model="form.product_registration" />
              </div>
              <div class="space-y-2">
                <Label>Registration PDF</Label>
                <Input
                  type="file"
                  accept="application/pdf"
                  @change="handleRegistrationFileChange"
                />
                <p class="text-muted-foreground text-xs">
                  Upload a PDF and it will be linked to this product registration.
                </p>
              </div>
              <div class="space-y-2"><Label>Size</Label><Input v-model="form.size" /></div>
              <div class="space-y-2">
                <Label>Cost</Label>
                <Input v-model.number="form.cost_per_qty" type="number" step="0.01" />
              </div>
              <div class="space-y-2 md:col-span-2">
                <Label>Details</Label>
                <textarea
                  v-model="form.details"
                  placeholder="Additional product details"
                  class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                ></textarea>
              </div>
            </template>

            <template v-else>
              <div class="space-y-2">
                <Label>Name *</Label>
                <Input
                  v-model="form.name"
                  required
                  :disabled="editType === 'production' && !!editId"
                />
              </div>
              <div v-if="editType !== 'marketing'" class="space-y-2">
                <Label>Category</Label>
                <Select v-model="form.category">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="opt in getEnumOptions(enums[`${editType}Category`])"
                      :key="opt.key"
                      :value="opt.key"
                      >{{ opt.value }}</SelectItem
                    >
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Date of Establishment</Label>
                <Input v-model="form.date_of_establishment" type="date" />
              </div>
              <div class="space-y-2">
                <Label>Contact Person</Label>
                <Input v-model="form.contact_person" />
              </div>
              <div class="space-y-2">
                <Label>Position</Label>
                <Input v-model="form.position" />
              </div>
              <div class="space-y-2">
                <Label>Contact Number</Label>
                <Input v-model="form.contact_number" type="tel" />
              </div>
              <div class="space-y-2">
                <Label>Email</Label>
                <Input v-model="form.email_address" type="email" />
              </div>
              <div class="space-y-2">
                <Label>Website</Label>
                <Input v-model="form.website" type="text" placeholder="https://..." />
              </div>
              <div class="space-y-2">
                <Label>Male Staff</Label>
                <Input v-model.number="form.number_of_staff_male" type="number" />
              </div>
              <div class="space-y-2">
                <Label>Female Staff</Label>
                <Input v-model.number="form.number_of_staff_female" type="number" />
              </div>
              <div class="space-y-2">
                <Label>Average Salary</Label>
                <Input v-model.number="form.avg_salary_of_staff" type="number" step="0.01" />
              </div>
            </template>
          </div>
        </div>

        <DialogFooter class="bg-muted/20 shrink-0 border-t p-6">
          <Button type="button" variant="outline" @click="close">Cancel</Button>
          <Button
            type="submit"
            :disabled="form.processing"
            class="bg-black text-white hover:bg-black/90"
          >
            {{ form.processing ? 'Saving...' : 'Save Changes' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
