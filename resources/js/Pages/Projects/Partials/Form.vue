<script setup>
import { computed, ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
  project: { type: Object, required: true },
  enums: { type: Object, required: true },
})

const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')
const isViewMode = computed(() => props.mode === 'view')

const programOptions = computed(() => {
  const programs = props.enums?.programs || {}
  const options = Object.entries(programs).map(([value, label]) => ({ value, label }))

  if (isEditMode.value && props.project?.program) {
    const existingProgram = props.project.program
    if (!programs[existingProgram]) {
      options.push({ value: existingProgram, label: existingProgram })
    }
  }
  options.push({ value: 'other', label: 'Other' })
  return options
})

const form = useForm({
  name: props.project?.name || '',
  program: props.project?.program || '',
  is_funded: props.project?.is_funded ?? true,
  description: props.project?.description || '',
  project_involvement: props.project?.project_involvement || [],
  other_program: '',
})

const projectInvolvementInput = ref('')

const addProjectInvolvement = () => {
  const value = projectInvolvementInput.value.trim()
  if (value && !form.project_involvement.includes(value)) {
    form.project_involvement.push(value)
    projectInvolvementInput.value = ''
  }
}

const removeProjectInvolvement = value => {
  const index = form.project_involvement.indexOf(value)
  if (index > -1) {
    form.project_involvement.splice(index, 1)
  }
}

const handleProjectInvolvementKeydown = event => {
  if (event.key === 'Enter') {
    event.preventDefault()
    addProjectInvolvement()
  }
}

const submit = () => {
  const formData = { ...form.data() }
  if (form.program === 'other' && form.other_program) {
    formData.program = form.other_program
  }

  if (isEditMode.value) {
    router.put(route('projects.update', props.project?.id), formData)
  } else {
    router.post(route('projects.store'), formData)
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <Card>
      <CardHeader>
        <div class="flex items-start justify-between gap-3">
          <div>
            <CardTitle>
              {{ isViewMode ? 'Project Details' : isEditMode ? 'Edit Project' : 'Create Project' }}
            </CardTitle>
            <CardDescription>Fill in the project information</CardDescription>
          </div>
          <div class="flex items-center gap-2">
            <Button
              v-if="isViewMode"
              variant="outline"
              type="button"
              @click="router.visit(route('projects.edit', props.project?.id))"
            >
              Edit
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <form class="flex flex-col gap-4" @submit.prevent="submit">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Basic Information</CardTitle>
              <CardDescription>Project details and settings</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <!-- Row 1: Project Name | Status -->
              <div class="space-y-2">
                <Label for="name">Project Name *</Label>
                <Input
                  v-if="!isViewMode"
                  id="name"
                  v-model="form.name"
                  placeholder="Enter project name"
                  required
                />
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.project?.name || 'N/A' }}
                </div>
                <p v-if="form.errors.name" class="text-sm text-red-500">
                  {{ form.errors.name }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="is_funded">Status *</Label>
                <Select v-if="!isViewMode" v-model="form.is_funded">
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select funding status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="true">Funded</SelectItem>
                    <SelectItem :value="false">Unfunded</SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.project?.is_funded ? 'Funded' : 'Unfunded' }}
                </div>
                <p v-if="form.errors.is_funded" class="text-sm text-red-500">
                  {{ form.errors.is_funded }}
                </p>
              </div>

              <!-- Row 2: Program | (empty) -->
              <div class="space-y-2">
                <Label for="program">Program *</Label>
                <Select v-if="!isViewMode" v-model="form.program">
                  <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Select program" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="program in programOptions"
                      :key="program.value"
                      :value="program.value"
                    >
                      {{ program.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-else class="rounded bg-gray-50 p-2 text-sm">
                  {{ props.project?.program ? props.project.program.replace('_', '/') : 'N/A' }}
                </div>
                <p v-if="form.errors.program" class="text-sm text-red-500">
                  {{ form.errors.program }}
                </p>
              </div>

              <!-- Empty spacer for Program row -->
              <div class="hidden md:block" />

              <!-- Other Program, only shown when "Other" is selected -->
              <div v-if="!isViewMode && form.program === 'other'" class="space-y-2">
                <Label for="other_program">Other Program *</Label>
                <Input
                  id="other_program"
                  v-model="form.other_program"
                  placeholder="Enter program name"
                  class="h-9 w-full"
                  required
                />
                <p v-if="form.errors.other_program" class="text-sm text-red-500">
                  {{ form.errors.other_program }}
                </p>
              </div>

              <!-- Row 3: Project Involvement -->
              <div class="space-y-2 md:col-span-2">
                <Label for="project_involvement">Project Involvement</Label>

                <div v-if="isViewMode" class="rounded bg-gray-50 p-2 text-sm">
                  {{
                    props.project?.project_involvement?.join(', ') ||
                    'No project involvement specified'
                  }}
                </div>

                <div v-else>
                  <div v-if="form.project_involvement.length > 0" class="mb-2 flex flex-wrap gap-2">
                    <span
                      v-for="involvement in form.project_involvement"
                      :key="involvement"
                      class="bg-primary/10 inline-flex items-center gap-1 rounded-sm px-2 py-1 text-xs"
                    >
                      {{ involvement }}
                      <button
                        class="hover:text-destructive"
                        type="button"
                        @click="removeProjectInvolvement(involvement)"
                      >
                        <X class="h-3 w-3" />
                      </button>
                    </span>
                  </div>

                  <Input
                    id="project_involvement"
                    v-model="projectInvolvementInput"
                    placeholder="Type project involvement and press Enter to add"
                    @keydown="handleProjectInvolvementKeydown"
                  />

                  <div class="text-muted-foreground mt-2 text-xs">Example: CBIN-2020</div>
                </div>

                <p v-if="form.errors.project_involvement" class="text-sm text-red-500">
                  {{ form.errors.project_involvement }}
                </p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="text-base">Description</CardTitle>
              <CardDescription>Project details and additional information</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="space-y-2">
                <textarea
                  v-if="!isViewMode"
                  id="description"
                  v-model="form.description"
                  rows="4"
                  class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                  placeholder="Enter project description"
                ></textarea>
                <div v-else class="min-h-[100px] rounded bg-gray-50 p-2 text-sm">
                  {{ props.project?.description || 'No description provided' }}
                </div>
                <p v-if="form.errors.description" class="text-sm text-red-500">
                  {{ form.errors.description }}
                </p>
              </div>
            </CardContent>
          </Card>

          <div v-if="!isViewMode" class="flex justify-end gap-2">
            <Button type="button" variant="outline" @click="router.visit(route('projects.index'))">
              Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
              {{
                form.processing ? 'Saving...' : isCreateMode ? 'Create Project' : 'Update Project'
              }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
