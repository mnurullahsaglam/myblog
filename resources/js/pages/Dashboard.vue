<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatTile from '@/Components/Stats/StatTile.vue'

// Deliberately undefined rather than [] by default. The controller omits the
// props for areas this user cannot reach, and an absent panel has to be
// distinguishable from a panel whose area simply has nothing in it yet.
const props = defineProps({
  budget: { type: Array, required: false, default: undefined },
  work: { type: Array, required: false, default: undefined },
  library: { type: Array, required: false, default: undefined },
  recentPosts: { type: Array, required: false, default: undefined },
  openTasks: { type: Array, required: false, default: undefined },
})

const ALL_SECTIONS = [
  ['budget', 'Budget', 'admin.expenses.index'],
  ['work', 'Work', 'admin.tasks.board'],
  ['library', 'Library', 'admin.books.index'],
]

const sections = computed(() => ALL_SECTIONS.filter(([key]) => props[key] !== undefined))

const STATUS_LABEL = { todo: 'To do', in_progress: 'In progress' }
</script>

<template>
  <AdminLayout title="Dashboard">
    <div class="flex flex-col gap-8">
      <section v-for="[key, title, routeName] in sections" :key="key">
        <header class="mb-3 flex items-center justify-between">
          <h2 class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase">
            {{ title }}
          </h2>
          <Link :href="route(routeName)" class="text-surface-500 hover:text-primary font-mono text-[11px]">
            open →
          </Link>
        </header>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <StatTile
            v-for="tile in $props[key]"
            :key="tile.label"
            :label="tile.label"
            :value="tile.value"
            :caption="tile.caption"
            :icon="tile.icon"
          />
        </div>
      </section>

      <div class="grid gap-4 lg:grid-cols-2">
        <section
          v-if="recentPosts"
          class="border-surface-200 bg-surface-0 rounded-lg border p-4 dark:border-[#272B35] dark:bg-[#15171C]"
        >
          <header class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-medium tracking-[-0.01em]">Recent posts</h2>
            <Link :href="route('admin.posts.index')" class="text-surface-500 hover:text-primary font-mono text-[11px]">
              all →
            </Link>
          </header>

          <p v-if="!recentPosts.length" class="text-surface-500 py-8 text-center text-sm">Nothing written yet.</p>

          <ul v-else class="divide-surface-100 divide-y dark:divide-[#20232B]">
            <li v-for="post in recentPosts" :key="post.id">
              <Link
                :href="route('admin.posts.edit', post.id)"
                class="hover:text-primary flex items-center gap-3 py-2.5"
              >
                <span class="min-w-0 flex-1 truncate text-[13px]">{{ post.title }}</span>
                <span class="text-surface-500 shrink-0 font-mono text-[11px]">{{ post.updatedAt }}</span>
              </Link>
            </li>
          </ul>
        </section>

        <section
          v-if="openTasks"
          class="border-surface-200 bg-surface-0 rounded-lg border p-4 dark:border-[#272B35] dark:bg-[#15171C]"
        >
          <header class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-medium tracking-[-0.01em]">Open tasks</h2>
            <Link :href="route('admin.tasks.board')" class="text-surface-500 hover:text-primary font-mono text-[11px]">
              board →
            </Link>
          </header>

          <p v-if="!openTasks.length" class="text-surface-500 py-8 text-center text-sm">Nothing open.</p>

          <ul v-else class="divide-surface-100 divide-y dark:divide-[#20232B]">
            <li v-for="task in openTasks" :key="task.id" class="flex items-center gap-3 py-2.5">
              <span
                class="inline-block h-1.5 w-1.5 shrink-0 rounded-full"
                :class="task.status === 'in_progress' ? 'bg-primary' : 'bg-surface-400'"
              />
              <span class="min-w-0 flex-1 truncate text-[13px]">{{ task.title }}</span>
              <span v-if="task.repository" class="text-surface-500 shrink-0 font-mono text-[11px]">
                {{ task.repository }}
              </span>
              <span class="text-surface-500 shrink-0 font-mono text-[11px]">
                {{ STATUS_LABEL[task.status] }}
              </span>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </AdminLayout>
</template>
