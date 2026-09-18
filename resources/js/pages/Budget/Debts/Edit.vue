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
    form.put(route('admin.debts.update', props.recordId))
}
</script>

<template>
    <AdminLayout title="Edit debt">
        <ResourceForm
            :schema="schema"
            :form="form"
            :placeholders="placeholders"
            submit-label="Save changes"
            @submit="submit"
            @cancel="router.visit(route('admin.debts.index'))"
        />
    </AdminLayout>
</template>
