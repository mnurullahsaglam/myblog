<script setup>
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceForm from '@/Components/Form/ResourceForm.vue'

const props = defineProps({
    schema: { type: Object, required: true },
    values: { type: Object, required: true },
    placeholders: { type: Object, default: () => ({}) },
    recordId: { type: Number, required: true },
})

const form = useForm(props.values)

/** Cheap reading stats, matching the design's meta row. */
const stats = computed(() => {
    const words = String(form.content ?? '').trim().split(/\s+/).filter(Boolean).length

    return {
        words: words.toLocaleString(),
        minutes: Math.max(1, Math.round(words / 220)),
    }
})

function submit() {
    // PUT cannot carry a file upload, so spoof the method.
    form.transform((data) => ({ ...data, _method: 'put' }))
        .post(route('admin.posts.update', props.recordId), { forceFormData: true })
}
</script>

<template>
    <AdminLayout title="Edit post">
        <template #subheader>
            <p class="mb-6 flex flex-wrap items-center gap-x-3 gap-y-1 font-mono text-[11px] text-surface-500">
                <span>{{ stats.words }} words</span>
                <span>·</span>
                <span>~{{ stats.minutes }} min read</span>
                <template v-if="placeholders.updated_at">
                    <span>·</span>
                    <span>modified {{ placeholders.updated_at }}</span>
                </template>
            </p>
        </template>

        <ResourceForm
            :schema="schema"
            :form="form"
            :placeholders="placeholders"
            submit-label="Save changes"
            @submit="submit"
            @cancel="router.visit(route('admin.posts.index'))"
        />
    </AdminLayout>
</template>
