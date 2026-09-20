<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import draggable from 'vuedraggable'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import TaskCard from '@/Components/Board/TaskCard.vue'
import TaskDialog from '@/Components/Board/TaskDialog.vue'

const props = defineProps({
  columns: { type: Array, required: true },
  statuses: { type: Array, required: true },
  projects: { type: Array, default: () => [] },
  repositories: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const confirm = useConfirm()

const editingTask = ref(null)
const dialogOpen = ref(false)

const search = ref(props.filters.search ?? '')
const project = ref(props.filters.project ?? null)

const local = ref(cloneColumns(props.columns))

function cloneColumns(columns) {
  return columns.map((column) => ({ ...column, tasks: [...column.tasks] }))
}

watch(
  () => props.columns,
  (columns) => (local.value = cloneColumns(columns)),
)

const total = computed(() => props.columns.reduce((sum, column) => sum + column.tasks.length, 0))

let searchTimer = null

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(reload, 300)
})

watch(project, reload)

function reload() {
  router.get(
    route('admin.tasks.board'),
    { search: search.value || undefined, project: project.value || undefined },
    { preserveState: true, preserveScroll: true, replace: true },
  )
}

function onChange(column, event) {
  const moved = event.added ?? event.moved

  if (!moved) {
    return
  }

  router.patch(
    route('admin.tasks.move', moved.element.id),
    { status: column.key, position: moved.newIndex },
    {
      preserveScroll: true,
      onError: () => (local.value = cloneColumns(props.columns)),
    },
  )
}

function openNew() {
  editingTask.value = null
  dialogOpen.value = true
}

function openEdit(task) {
  editingTask.value = task
  dialogOpen.value = true
}

function closeDialog() {
  dialogOpen.value = false
  editingTask.value = null
}

function syncTask(task) {
  confirm.require({
    header: 'Sync to GitHub',
    message: `Push "${task.title}" to issue #${task.githubNumber}?`,
    icon: 'pi pi-github',
    acceptProps: { label: 'Sync' },
    rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
    accept: () => router.post(route('admin.tasks.sync-github', task.id), {}, { preserveScroll: true }),
  })
}
</script>

<template>
  <AdminLayout title="Task board" :subtitle="`${total} ${total === 1 ? 'task' : 'tasks'}`">
    <template #actions>
      <Button label="New task" icon="pi pi-plus" size="small" @click="openNew" />
    </template>

    <div class="mb-5 flex flex-wrap items-center gap-2">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Filter tasks" size="small" class="w-64" />
      </IconField>

      <Select
        v-model="project"
        :options="projects"
        option-label="label"
        option-value="value"
        placeholder="All projects"
        size="small"
        show-clear
        class="min-w-48"
      />
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <section
        v-for="column in local"
        :key="column.key"
        class="border-surface-200 bg-surface-100/40 rounded-lg border p-3 dark:border-[#272B35] dark:bg-[#15171C]/60"
      >
        <header class="mb-3 flex items-center gap-2">
          <h2 class="text-sm font-semibold">{{ column.label }}</h2>
          <Badge :value="column.tasks.length" :severity="column.color" />
        </header>

        <draggable
          v-model="column.tasks"
          group="tasks"
          item-key="id"
          class="flex min-h-24 flex-col gap-2"
          ghost-class="opacity-40"
          @change="onChange(column, $event)"
        >
          <template #item="{ element }">
            <TaskCard :task="element" @edit="openEdit" @sync="syncTask" />
          </template>
        </draggable>

        <p v-if="!column.tasks.length" class="text-surface-500 py-8 text-center font-mono text-[11px]">Nothing here</p>
      </section>
    </div>

    <TaskDialog
      :task="editingTask"
      :open="dialogOpen"
      :statuses="statuses"
      :projects="projects"
      :repositories="repositories"
      @close="closeDialog"
    />
  </AdminLayout>
</template>
