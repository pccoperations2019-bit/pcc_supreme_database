<script setup>
import { inject } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

const props = defineProps({
  document: { type: Object, required: true },
})

const route = inject('route')

const form = useForm({
  title: props.document.title ?? '',
  description: props.document.description ?? '',
})

const submit = () => form.put(route('documents.update', props.document.id))
</script>

<template>
  <Head title="Edit Document" />
  <DashboardLayout>
    <div class="mx-auto max-w-3xl px-4">
      <Card>
        <CardHeader>
          <CardTitle>Edit Document</CardTitle>
        </CardHeader>
        <CardContent>
          <form class="space-y-6" @submit.prevent="submit">
            <div class="space-y-2">
              <Label for="title">Title</Label>
              <Input
                id="title"
                v-model="form.title"
                required
                maxlength="255"
                :aria-invalid="!!form.errors.title"
                aria-describedby="title-error"
              />
              <InputError id="title-error" :message="form.errors.title" />
            </div>
            <div class="space-y-2">
              <Label for="description">Description</Label>
              <Textarea
                id="description"
                v-model="form.description"
                :aria-invalid="!!form.errors.description"
                aria-describedby="description-error"
              />
              <InputError id="description-error" :message="form.errors.description" />
            </div>
            <div class="flex items-center gap-4">
              <Button type="submit" :disabled="form.processing">{{
                form.processing ? 'Saving…' : 'Save changes'
              }}</Button>
              <Link :href="route('documents.show', document.id)" class="text-sm underline"
                >Cancel</Link
              >
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </DashboardLayout>
</template>
