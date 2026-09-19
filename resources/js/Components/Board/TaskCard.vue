<script setup>
import Button from 'primevue/button'

defineProps({
  task: { type: Object, required: true },
})

const emit = defineEmits(['edit', 'sync'])

const done = (task) => task.status === 'completed'
</script>

<template>
  <article
    class="group border-surface-200 bg-surface-0 hover:border-surface-300 cursor-grab rounded border p-3 transition-colors active:cursor-grabbing dark:border-[#272B35] dark:bg-[#15171C] dark:hover:border-[#9096A2]"
  >
    <div class="flex items-start gap-2">
      <p
        v-if="task.repository"
        class="border-surface-200 bg-surface-100 text-surface-500 min-w-0 truncate rounded border px-1.5 py-0.5 font-mono text-[11px] dark:border-[#272B35] dark:bg-[#1A1D24]"
      >
        {{ task.repository }}
      </p>

      <span class="ml-auto flex shrink-0 gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
        <Button
          icon="pi pi-pencil"
          text
          rounded
          size="small"
          severity="secondary"
          aria-label="Edit task"
          @click="emit('edit', task)"
        />
        <Button
          v-if="task.isGithubIssue"
          icon="pi pi-sync"
          text
          rounded
          size="small"
          severity="secondary"
          aria-label="Sync to GitHub"
          @click="emit('sync', task)"
        />
      </span>
    </div>

    <h3 class="mt-2 text-[13px] leading-snug font-medium" :class="done(task) ? 'text-surface-500 line-through' : ''">
      {{ task.title }}
    </h3>

    <div v-if="task.labels.length" class="mt-2 flex flex-wrap gap-1">
      <span
        v-for="label in task.labels"
        :key="label.name"
        class="rounded px-1.5 py-0.5 font-mono text-[10px]"
        :style="{ backgroundColor: `#${label.color}22`, color: `#${label.color}` }"
        >{{ label.name }}</span
      >
    </div>

    <div class="text-surface-500 mt-2.5 flex items-center gap-2 font-mono text-[11px]">
      <a v-if="task.githubUrl" :href="task.githubUrl" target="_blank" rel="noopener" class="hover:text-primary"
        >#{{ task.githubNumber }}</a
      >
      <span v-if="task.project" class="truncate">{{ task.project }}</span>
    </div>
  </article>
</template>
