<script setup>
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceForm from '@/Components/Form/ResourceForm.vue'

const props = defineProps({
  schema: { type: Object, required: true },
  values: { type: Object, required: true },
  placeholders: { type: Object, default: () => ({}) },
  recordId: { type: [Number, String], required: true },
})

const form = useForm(props.values)

function submit() {
  form
    .transform((data) => ({ ...data, _method: 'put' }))
    .post(route('admin.books.update', props.recordId), { forceFormData: true })
}
</script>

<template>
  <AdminLayout title="Edit book">
    <ResourceForm
      :schema="schema"
      :form="form"
      :placeholders="placeholders"
      submit-label="Save changes"
      @submit="submit"
      @cancel="router.visit(route('admin.books.index'))"
    />
  </AdminLayout>
</template>
