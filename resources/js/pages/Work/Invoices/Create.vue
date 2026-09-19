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
    form.post(route('admin.invoices.store'), { forceFormData: true })
}
</script>

<template>
    <AdminLayout title="New invoice">
        <ResourceForm
            :schema="schema"
            :form="form"
            submit-label="Create invoice"
            @submit="submit"
            @cancel="router.visit(route('admin.invoices.index'))"
        />
    </AdminLayout>
</template>
