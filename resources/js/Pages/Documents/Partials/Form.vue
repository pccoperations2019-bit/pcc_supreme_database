<script setup>
import { useForm } from '@inertiajs/vue3'
import { Check, Dot, Upload, Tag, FileText, Eye } from 'lucide-vue-next'
import { ref, computed, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { useAuth } from '@/composable/useAuth'

const { isCenterScoped } = useAuth()

const props = defineProps({
  tagOptions: {
    type: Object,
    required: true,
  },
  templatesRequiringProgram: {
    type: Array,
    default: () => [],
  },
  // Set for center admins: their own center code, applied without a selector.
  centerTagValue: {
    type: String,
    default: null,
  },
  allowedFileTypes: {
    type: Object,
    required: false,
    default: () => ({
      extensions: ['pdf', 'xlsx', 'xls', 'csv'],
      accept: '.pdf,.xlsx,.xls,.csv',
      acceptLabel: 'PDF, Excel (.xlsx, .xls) or CSV',
      maxSizeMb: 20,
    }),
  },
})
import {
  Stepper,
  StepperDescription,
  StepperItem,
  StepperSeparator,
  StepperTitle,
  StepperTrigger,
} from '@/components/ui/stepper'
import Repeater from '@/components/Repeater.vue'
import axios from 'axios'
import { DOCUMENT_STATUS } from '@/lib/contants'

const stepIndex = ref(1)

const steps = [
  {
    step: 1,
    title: 'Upload File',
    description: 'Select a file to upload',
    icon: Upload,
  },
  {
    step: 2,
    title: 'Select Tag',
    description: 'Choose a tag for the document',
    icon: Tag,
  },
  {
    step: 3,
    title: 'Add Metadata',
    description: 'Provide additional information',
    icon: FileText,
  },
  {
    step: 4,
    title: 'Review & Submit',
    description: 'Finalize your document upload',
    icon: Eye,
  },
]

const form = useForm({
  presignedUrl: null,
  file_key: null,
  file_name: null,
  file_type: null,
  file_size: null,
  files: [],
  tags: [],
  title: '',
  description: '',
  keywords: '',
})

const fileError = ref('')

// Scalable — single source from config/documents.php via DocumentController::create
const acceptAttr = computed(() => props.allowedFileTypes?.accept || '.pdf,.xlsx,.xls,.csv')
const acceptLabel = computed(
  () => props.allowedFileTypes?.acceptLabel || 'PDF, Excel (.xlsx, .xls) or CSV'
)
const maxSizeMb = computed(() => Number(props.allowedFileTypes?.maxSizeMb || 20))

function getExtension(filename) {
  const parts = String(filename || '').split('.')
  return parts.length > 1 ? parts.pop().toLowerCase() : ''
}

function inferMime(file) {
  // Browser may return '' for xlsx on some OS — infer from extension so backend validation passes
  if (file.type) return file.type
  const ext = getExtension(file.name)
  const map = {
    pdf: 'application/pdf',
    xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    xls: 'application/vnd.ms-excel',
    csv: 'text/csv',
  }
  return map[ext] || 'application/octet-stream'
}

function validateFile(file) {
  const ext = getExtension(file.name)
  const allowed = props.allowedFileTypes?.extensions || ['pdf', 'xlsx', 'xls', 'csv']
  if (!allowed.includes(ext)) {
    return `File type .${ext} not allowed. Allowed: ${allowed.join(', ')}`
  }
  const maxBytes = maxSizeMb.value * 1024 * 1024
  if (file.size > maxBytes) {
    return `${file.name} exceeds ${maxSizeMb.value} MB limit.`
  }
  if (file.size === 0) {
    return `${file.name} is empty.`
  }
  return null
}

const handleFileSelect = async event => {
  fileError.value = ''
  const selectedFiles = Array.from(event.target.files)
  // Allow re-selecting same file after error
  event.target.value = ''
  if (!selectedFiles.length) return

  for (const file of selectedFiles) {
    const err = validateFile(file)
    if (err) {
      fileError.value = err
      return
    }
  }

  try {
    const filesWithSignedUrls = []
    for (const file of selectedFiles) {
      const data = await requestSignedUrl(file)
      filesWithSignedUrls.push({
        file,
        file_key: data.key,
        file_type: inferMime(file),
        file_name: file.name,
        file_size: file.size,
        presignedUrl: data.url,
      })
    }
    form.files = filesWithSignedUrls
    if (!form.title && selectedFiles.length > 0) {
      form.title =
        selectedFiles.length === 1
          ? selectedFiles[0].name
          : `${selectedFiles.length} files uploaded`
    }
  } catch (e) {
    fileError.value =
      e?.response?.data?.message || e?.message || 'Failed to prepare upload. Please try again.'
  }
}

function isStepValid(step) {
  switch (step) {
    case 1:
      return form.files.length > 0
    case 2:
      return (
        form.tags !== null &&
        form.tags.length > 0 &&
        form.tags.every(t => t.value !== null && String(t.value).trim() !== '')
      )
    case 3:
      return String(form.title || '').trim() !== ''
    case 4:
      return true
    default:
      return false
  }
}

const uploadFile = async fileData => {
  try {
    await axios.put(fileData.presignedUrl, fileData.file, {
      headers: { 'Content-Type': fileData.file_type },
    })
  } catch (error) {
    const msg = error?.response?.data?.error || error?.response?.data?.message || error.message
    fileError.value = `Upload failed for ${fileData.file_name}: ${msg}`
    console.error('Error uploading file:', fileData.file_name, error)
    throw error
  }
}

const requestSignedUrl = async file => {
  try {
    const response = await axios.post('/xhr/files-presigned-url', {
      filename: file.name,
      contentType: inferMime(file),
    })
    return response.data
  } catch (error) {
    console.error('Error fetching presigned URL:', error)
    throw error
  }
}

const requiresProgramField = computed(() => {
  const templateTag = form.tags.find(tag => tag.key === 'template')
  if (!templateTag || !templateTag.value) return false
  return props.templatesRequiringProgram.includes(templateTag.value)
})

const visibleTags = ref([])

/**
 * Center admins get their center applied for them: the Center selector is hidden
 * and the tag is (re)applied here, because several watchers below rebuild
 * `form.tags` from scratch. store() re-applies it server-side regardless.
 */
const syncAdminCenterTag = () => {
  if (!isCenterScoped.value || !props.centerTagValue) return

  const centerTag = form.tags.find(tag => tag.key === 'center')

  if (!centerTag) {
    form.tags.push({
      key: 'center',
      value: props.centerTagValue,
      taggable_type: null,
      taggable_id: null,
    })
  } else if (!centerTag.value) {
    centerTag.value = props.centerTagValue
  }
}

const initializeTags = () => {
  if (form.tags.length === 0) {
    form.tags.push({ key: 'template', value: null, taggable_type: null, taggable_id: null })
  }
  syncAdminCenterTag()
  visibleTags.value = [...form.tags]
}

watch(
  () => form.tags[0]?.key,
  (newValue, oldValue) => {
    if (newValue === oldValue) return
    form.tags = [{ key: newValue, value: null, taggable_type: null, taggable_id: null }]
    updateVisibleTags()
  },
  { deep: true }
)

watch(
  () => form.tags.find(tag => tag.key === 'document')?.value,
  (newValue, oldValue) => {
    if (newValue === oldValue) return
    if (newValue) {
      if (!form.tags.find(tag => tag.key === 'entity_type')) {
        form.tags.push({ key: 'entity_type', value: null, taggable_type: null, taggable_id: null })
      }
    } else {
      form.tags = form.tags.filter(
        tag => !['entity_type', 'center', 'partner', 'farmer'].includes(tag.key)
      )
    }
    updateVisibleTags()
  }
)

watch(
  () => form.tags.find(tag => tag.key === 'entity_type')?.value,
  (newValue, oldValue) => {
    if (newValue === oldValue) return
    if (oldValue) form.tags = form.tags.filter(tag => tag.key !== oldValue)
    if (newValue && !form.tags.find(tag => tag.key === newValue)) {
      form.tags.push({ key: newValue, value: null, taggable_type: null, taggable_id: null })
    }
    updateVisibleTags()
  },
  { deep: true }
)

watch(
  () => form.tags.find(tag => tag.key === 'template')?.value,
  (newValue, oldValue) => {
    if (newValue === oldValue) return
    const oldRequiresProgram = oldValue && props.templatesRequiringProgram.includes(oldValue)
    const newRequiresProgram = newValue && props.templatesRequiringProgram.includes(newValue)
    if (oldRequiresProgram && !newRequiresProgram) {
      const programTag = form.tags.find(tag => tag.key === 'program')
      if (programTag) programTag.value = null
    }
    if (newRequiresProgram && !form.tags.find(tag => tag.key === 'program')) {
      form.tags.push({ key: 'program', value: null, taggable_type: null, taggable_id: null })
    }
    updateVisibleTags()
  }
)

const updateVisibleTags = () => {
  let tagsToShow = []
  if (form.tags.length > 0) {
    const firstTag = form.tags[0]
    tagsToShow.push(firstTag.key)
    if (firstTag.key === 'template') {
      if (firstTag.value && props.templatesRequiringProgram.includes(firstTag.value)) {
        tagsToShow.push('program')
      }
    } else if (firstTag.key === 'document') {
      if (firstTag.value) {
        tagsToShow.push('entity_type')
        const entityTypeTag = form.tags.find(tag => tag.key === 'entity_type')
        if (entityTypeTag?.value) tagsToShow.push(entityTypeTag.value)
      }
    }
  }
  syncAdminCenterTag()

  visibleTags.value = form.tags.filter(
    tag => tagsToShow.includes(tag.key) && !(isCenterScoped.value && tag.key === 'center')
  )
}

initializeTags()

const onSubmit = () => {
  const tagsToSubmit = requiresProgramField.value
    ? form.tags
    : form.tags.filter(tag => tag.key !== 'program')

  const uploadPromises = form.files.map(fileData => uploadFile(fileData))

  Promise.all(uploadPromises)
    .then(() => {
      const documentPromises = form.files.map(fileData => {
        const formData = {
          title: form.files.length === 1 ? form.title : `${fileData.file_name} - ${form.title}`,
          file_name: fileData.file_name,
          file_key: fileData.file_key,
          file_type: fileData.file_type,
          file_size: fileData.file_size,
          description: form.description,
          tags: tagsToSubmit,
        }
        return axios
          .post('/documents', formData)
          .then(response => {
            window.dispatchEvent(
              new CustomEvent('import-queued', {
                detail: {
                  documentId: response.data.document?.id || response.data.id,
                  fileName: fileData.file_name,
                  // A center admin's upload is submitted for review server-side
                  // as part of the upload, so the card must not sit on draft.
                  // Center-less uploads go straight to agency review.
                  status:
                    response.data.document?.status ||
                    (isCenterScoped.value
                      ? DOCUMENT_STATUS.PENDING_CENTER_REVIEW
                      : DOCUMENT_STATUS.PENDING_AGENCY_REVIEW),
                  progress: 0,
                },
              })
            )
            return response
          })
          .catch(errors => {
            throw errors
          })
      })
      return Promise.all(documentPromises)
    })
    .then(() => {
      window.location.href = '/documents'
    })
    .catch(error => {
      const validationErrors = error?.response?.data?.errors
      const firstValidationError = validationErrors ? Object.values(validationErrors)[0]?.[0] : null
      const msg =
        firstValidationError ||
        error?.response?.data?.error ||
        error?.response?.data?.message ||
        error.message
      if (msg) fileError.value = msg
      console.error('Document submit failed:', error)
    })
}
</script>

<template>
  <div class="px-4 py-6 sm:px-6 sm:py-8">
    <h1 class="mb-6 text-xl font-bold sm:text-2xl">Document Upload</h1>

    <Stepper
      v-slot="{ isPrevDisabled, nextStep, prevStep }"
      v-model="stepIndex"
      class="block w-full"
    >
      <form @submit.prevent="onSubmit">
        <!-- ── Step indicators ── -->
        <div class="flex w-full items-start gap-1 sm:gap-2">
          <StepperItem
            v-for="step in steps"
            :key="step.step"
            v-slot="{ state }"
            class="relative flex w-full min-w-0 flex-col items-center justify-center"
            :step="step.step"
          >
            <!-- Separator line between steps -->
            <StepperSeparator
              v-if="step.step !== steps[steps.length - 1].step"
              class="bg-muted group-data-[state=completed]:bg-primary absolute top-5 right-[calc(-50%+10px)] left-[calc(50%+20px)] block h-0.5 shrink-0 rounded-full"
            />

            <StepperTrigger as-child>
              <Button
                :variant="state === 'completed' || state === 'active' ? 'default' : 'outline-solid'"
                size="icon"
                class="z-10 shrink-0 rounded-full"
                :class="[
                  state === 'active' && 'ring-ring ring-offset-background ring-2 ring-offset-2',
                ]"
                :disabled="state !== 'completed' && !isStepValid(stepIndex)"
              >
                <Check v-if="state === 'completed'" class="size-5" />
                <component :is="step.icon" v-else-if="state === 'active'" class="size-4" />
                <Dot v-else class="size-5" />
              </Button>
            </StepperTrigger>

            <!-- On mobile: only show label for active step; all labels on sm+ -->
            <div class="mt-2 flex flex-col items-center text-center sm:mt-5">
              <StepperTitle
                :class="[
                  state === 'active' && 'text-primary',
                  state !== 'active' && 'sr-only sm:not-sr-only',
                ]"
                class="text-xs font-semibold transition sm:text-sm lg:text-base"
              >
                {{ step.title }}
              </StepperTitle>
              <StepperDescription
                :class="[state === 'active' && 'text-primary']"
                class="text-muted-foreground sr-only text-xs transition md:not-sr-only lg:text-sm"
              >
                {{ step.description }}
              </StepperDescription>
            </div>
          </StepperItem>
        </div>

        <!-- ── Step content ── -->
        <div class="mt-8 flex flex-col gap-4">
          <!-- Step 1: Upload File -->
          <template v-if="stepIndex === 1">
            <div class="space-y-4">
              <div>
                <Label for="file">Select Files <span class="text-red-500">*</span></Label>
                <Input
                  id="file"
                  type="file"
                  class="mt-2"
                  multiple
                  :accept="acceptAttr"
                  @change="handleFileSelect"
                />
                <p v-if="fileError" class="mt-1 text-sm text-red-500">
                  {{ fileError }}
                </p>
                <p v-if="form.errors.files" class="mt-1 text-sm text-red-500">
                  {{ form.errors.files }}
                </p>
              </div>

              <div v-if="form.files.length > 0" class="bg-muted rounded-md p-4">
                <p class="mb-2 text-sm font-medium">Selected Files ({{ form.files.length }}):</p>
                <ul class="space-y-1">
                  <li
                    v-for="fileData in form.files"
                    :key="fileData.file_name"
                    class="flex flex-wrap items-baseline gap-x-2 text-sm"
                  >
                    <!-- break-all so very long filenames don't overflow -->
                    <strong class="break-all">{{ fileData.file_name }}</strong>
                    <span class="text-muted-foreground shrink-0">
                      ({{ (fileData.file_size / 1024 / 1024).toFixed(2) }} MB)
                    </span>
                  </li>
                </ul>
                <p class="text-muted-foreground mt-2 text-sm">
                  Total Size:
                  {{
                    (
                      form.files.reduce((total, file) => total + file.file_size, 0) /
                      1024 /
                      1024
                    ).toFixed(2)
                  }}
                  MB
                </p>
              </div>
            </div>
          </template>

          <!-- Step 2: Select Tag -->
          <template v-if="stepIndex === 2">
            <div class="space-y-4">
              <!-- overflow-x-auto lets the Repeater's internal flex row scroll on mobile
                   instead of overflowing the card -->
              <div class="overflow-x-auto pb-2">
                <Repeater
                  v-model="visibleTags"
                  :keys="{
                    '': [{ label: 'Template', key: 'template' }],
                    template: [{ label: 'Template', key: 'template' }],
                    document: [{ label: 'Template', key: 'template' }],
                    entity_type: [{ label: 'Entity Type', key: 'entity_type' }],
                    center: [{ label: 'Center', key: 'center' }],
                    partner: [{ label: 'Partner', key: 'partner' }],
                    farmer: [{ label: 'Farmer', key: 'farmer' }],
                    program: [{ label: 'Program', key: 'program' }],
                  }"
                  :options="{
                    template: props.tagOptions.template || [],
                    document: props.tagOptions.document || [],
                    entity_type: props.tagOptions.entity_type || [],
                    center: props.tagOptions.center || [],
                    partner: props.tagOptions.partner || [],
                    farmer: props.tagOptions.farmer || [],
                    program: props.tagOptions.program || [],
                  }"
                  :show-code="['center']"
                />
              </div>
              <p v-if="form.errors.tags" class="mt-1 text-sm text-red-500">
                {{ form.errors.tags }}
              </p>
            </div>
          </template>

          <!-- Step 3: Add Metadata -->
          <template v-if="stepIndex === 3">
            <div class="space-y-4">
              <div>
                <Label for="title">Document Title <span class="text-red-500">*</span></Label>
                <Input
                  id="title"
                  v-model="form.title"
                  type="text"
                  placeholder="Enter document title"
                  class="mt-2"
                  required
                />
                <p v-if="form.errors.title" class="mt-1 text-sm text-red-500">
                  {{ form.errors.title }}
                </p>
              </div>

              <div>
                <Label for="description"
                  >Description <span class="text-muted-foreground text-xs">(optional)</span></Label
                >
                <Textarea
                  id="description"
                  v-model="form.description"
                  placeholder="Enter document description (optional)"
                  class="mt-2"
                  rows="4"
                />
                <p v-if="form.errors.description" class="mt-1 text-sm text-red-500">
                  {{ form.errors.description }}
                </p>
              </div>
            </div>
          </template>

          <!-- Step 4: Review & Submit -->
          <template v-if="stepIndex === 4">
            <div class="space-y-4">
              <div class="bg-muted space-y-3 rounded-md p-4">
                <h3 class="font-semibold">Review Your Document</h3>

                <div v-if="form.files.length > 0">
                  <Label class="text-sm font-medium">Files ({{ form.files.length }}):</Label>
                  <ul class="mt-1 space-y-1">
                    <li
                      v-for="fileData in form.files"
                      :key="fileData.file_name"
                      class="flex flex-wrap items-baseline gap-x-2 text-sm"
                    >
                      <span class="break-all">{{ fileData.file_name }}</span>
                      <span class="text-muted-foreground shrink-0">
                        ({{ (fileData.file_size / 1024 / 1024).toFixed(2) }} MB)
                      </span>
                    </li>
                  </ul>
                  <p class="text-muted-foreground mt-2 text-sm">
                    Total Size:
                    {{
                      (
                        form.files.reduce((total, file) => total + file.file_size, 0) /
                        1024 /
                        1024
                      ).toFixed(2)
                    }}
                    MB
                  </p>
                </div>

                <div>
                  <Label class="text-sm font-medium">Title:</Label>
                  <p class="text-sm break-words">{{ form.title || 'Not provided' }}</p>
                </div>

                <div>
                  <Label class="text-sm font-medium">Description:</Label>
                  <p class="text-sm break-words">{{ form.description || 'Not provided' }}</p>
                </div>

                <div v-if="form.keywords">
                  <Label class="text-sm font-medium">Keywords:</Label>
                  <p class="text-sm break-words">{{ form.keywords }}</p>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- ── Navigation buttons ── -->
        <p v-if="fileError && stepIndex === 4" class="mt-4 text-sm text-red-500">
          {{ fileError }}
        </p>
        <div class="mt-6 flex items-center justify-between gap-2">
          <Button
            :disabled="isPrevDisabled"
            variant="outline"
            size="sm"
            type="button"
            @click="prevStep()"
          >
            Back
          </Button>

          <div class="flex items-center gap-3">
            <Button
              v-if="stepIndex !== 4"
              size="sm"
              type="button"
              :disabled="!isStepValid(stepIndex)"
              @click="nextStep()"
            >
              Next
            </Button>
            <Button
              v-if="stepIndex === 4"
              size="sm"
              type="submit"
              :disabled="form.processing || !isStepValid(3)"
            >
              {{ form.processing ? 'Uploading...' : 'Submit' }}
            </Button>
          </div>
        </div>
      </form>
    </Stepper>
  </div>
</template>
