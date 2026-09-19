<script setup>
import { ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'

const props = defineProps({
  task: { type: Object, default: null },
  open: { type: Boolean, default: false },
  statuses: { type: Array, required: true },
  projects: { type: Array, default: () => [] },
  repositories: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

const confirm = useConfirm()
const visible = ref(false)

const form = useForm({
  title: '',
  description: '',
  status: 'todo',
  project_id: null,
  repository_id: null,
})

watch(
  () => [props.task, props.open],
  () => {
    visible.value = props.open

    if (!props.open) {
      return
    }

    form.clearErrors()
    form.defaults({
      title: props.task?.title ?? '',
      description: props.task?.description ?? '',
      status: props.task?.status ?? 'todo',
      project_id: props.task?.projectId ?? null,
      repository_id: props.task?.repositoryId ?? null,
    })
    form.reset()
  },
)

function submit() {
  const options = {
    onSuccess: () => {
      visible.value = false
      emit('close')
    },
  }

  if (props.task) {
    form.put(route('admin.tasks.update', props.task.id), options)
  } else {
    form.post(route('admin.tasks.store'), options)
  }
}

function destroy() {
  confirm.require({
    header: 'Delete this task?',
    message: `"${props.task.title}" will be permanently removed.`,
    icon: 'pi pi-exclamation-triangle',
    acceptProps: { label: 'Delete', severity: 'danger' },
    rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
    accept: () =>
      router.delete(route('admin.tasks.destroy', props.task.id), {
        onSuccess: () => {
          visible.value = false
          emit('close')
        },
      }),
  })
}

const labelClass = 'font-mono text-[11px] font-semibold uppercase tracking-[0.06em] text-surface-500'
</script>

<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="task ? 'Edit task' : 'New task'"
    :style="{ width: '32rem' }"
    @hide="emit('close')"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-1.5">
        <label for="title" :class="labelClass">Title</label>
        <InputText id="title" v-model="form.title" autofocus fluid :invalid="Boolean(form.errors.title)" />
        <small v-if="form.errors.title" class="text-[#D95757]">{{ form.errors.title }}</small>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="description" :class="labelClass">Description</label>
        <Textarea id="description" v-model="form.description" :rows="4" auto-resize />
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-1.5">
          <label for="status" :class="labelClass">Status</label>
          <Select
            id="status"
            v-model="form.status"
            :options="statuses"
            option-label="label"
            option-value="value"
            fluid
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label for="project_id" :class="labelClass">Project</label>
          <Select
            id="project_id"
            v-model="form.project_id"
            :options="projects"
            option-label="label"
            option-value="value"
            placeholder="None"
            show-clear
            fluid
          />
        </div>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="repository_id" :class="labelClass">Repository</label>
        <Select
          id="repository_id"
          v-model="form.repository_id"
          :options="repositories"
          option-label="label"
          option-value="value"
          placeholder="None"
          show-clear
          filter
          fluid
        />
        <small class="text-surface-500">A new task in a repository opens a GitHub issue.</small>
      </div>

      <div class="flex items-center gap-2 pt-1">
        <Button type="submit" :label="task ? 'Save changes' : 'Create task'" :loading="form.processing" />
        <Button type="button" label="Cancel" severity="secondary" text @click="visible = false" />

        <Button v-if="task" type="button" label="Delete" severity="danger" text class="ml-auto" @click="destroy" />
      </div>
    </form>
  </Dialog>
</template>
