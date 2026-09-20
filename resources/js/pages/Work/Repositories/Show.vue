<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  schema: { type: Object, required: true },
  values: { type: Object, required: true },
  placeholders: { type: Object, default: () => ({}) },
  recordId: { type: [Number, String], required: true },
})

const fields = computed(() => props.schema.fields.filter((field) => field.type !== 'hidden'))

function display(field) {
  if (field.type === 'placeholder') {
    return props.placeholders[field.key] ?? '—'
  }

  const value = props.values[field.key]

  if (value === null || value === undefined || value === '') {
    return '—'
  }

  if (Array.isArray(value)) {
    const labels = value.map(
      (item) => field.options?.find((option) => String(option.value) === String(item))?.label ?? item,
    )

    return labels.length ? labels.join(', ') : '—'
  }

  if (field.options?.length) {
    return field.options.find((option) => String(option.value) === String(value))?.label ?? value
  }

  if (field.type === 'toggle') {
    return value ? 'Yes' : 'No'
  }

  return value
}

function isMono(field) {
  return ['money', 'number', 'date', 'datetime', 'readonlyCode', 'placeholder'].includes(field.type)
}
</script>

<template>
  <AdminLayout title="Repository">
    <template #actions>
      <Button
        label="Edit"
        icon="pi pi-pencil"
        size="small"
        @click="router.visit(route('admin.repositories.edit', recordId))"
      />
    </template>

    <div class="border-surface-200 bg-surface-0 rounded-lg border p-4 dark:border-[#272B35] dark:bg-[#15171C]">
      <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
        <div v-for="field in fields" :key="field.key">
          <dt class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase" lang="en">
            {{ field.label }}
          </dt>
          <dd class="mt-1 text-[13px] break-words" :class="isMono(field) ? 'font-mono tabular-nums' : ''">
            {{ display(field) }}
          </dd>
        </div>
      </dl>
    </div>
  </AdminLayout>
</template>
