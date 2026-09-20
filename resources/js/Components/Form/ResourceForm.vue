<script setup>
import { watch } from 'vue'
import Button from 'primevue/button'
import FormField from './FormField.vue'

const props = defineProps({
  schema: { type: Object, required: true },
  form: { type: Object, required: true },
  placeholders: { type: Object, default: () => ({}) },
  submitLabel: { type: String, default: 'Save' },
})

const emit = defineEmits(['submit', 'cancel'])

function slugify(value) {
  return String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

props.schema.fields
  .filter((field) => field.meta.slugFrom)
  .forEach((field) => {
    watch(
      () => props.form[field.meta.slugFrom],
      (value) => {
        props.form[field.key] = slugify(value)
      },
    )
  })

function applyResolved(data) {
  Object.entries(data.values ?? {}).forEach(([key, value]) => {
    const current = props.form[key]

    if (current === null || current === '' || current === undefined) {
      props.form[key] = value
    }
  })

  if (data.image) {
    props.form.image = data.image
  }

  ;['writer', 'publisher'].forEach((relation) => {
    const match = data[relation]
    const key = `${relation}_id`

    if (match?.id && !props.form[key]) {
      props.form[key] = match.id
    }
  })
}
</script>

<template>
  <form class="max-w-3xl" @submit.prevent="emit('submit')">
    <div class="grid gap-5" :class="schema.columns === 2 ? 'sm:grid-cols-2' : 'grid-cols-1'">
      <FormField
        v-for="field in schema.fields"
        :key="field.key"
        v-model="form[field.key]"
        :field="field"
        :error="form.errors[field.key]"
        :placeholder-value="placeholders[field.key]"
        @resolved="applyResolved"
      />
    </div>

    <div
      class="border-surface-200 bg-surface-50/90 sticky bottom-0 mt-8 flex items-center gap-2 border-t py-4 backdrop-blur dark:border-[#272B35] dark:bg-[#0D0E11]/90"
    >
      <Button type="submit" :label="submitLabel" :loading="form.processing" />
      <Button type="button" label="Cancel" severity="secondary" text @click="emit('cancel')" />

      <span v-if="form.isDirty" class="text-primary ml-auto flex items-center gap-1.5 font-mono text-[11px]">
        <span class="bg-primary inline-block h-1.5 w-1.5 rounded-full" />
        Unsaved changes
      </span>
    </div>
  </form>
</template>
