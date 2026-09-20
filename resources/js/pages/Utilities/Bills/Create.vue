<script setup>
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceForm from '@/Components/Form/ResourceForm.vue'

const props = defineProps({
  schema: { type: Object, required: true },
  values: { type: Object, required: true },
})

const form = useForm(props.values)

function submit() {
  form.post(route('admin.utility-bills.store'), { forceFormData: true })
}
</script>

<template>
  <AdminLayout title="New bill">
    <ResourceForm
      :schema="schema"
      :form="form"
      submit-label="Create bill"
      @submit="submit"
      @cancel="router.visit(route('admin.utility-bills.index'))"
    />
  </AdminLayout>
</template>
