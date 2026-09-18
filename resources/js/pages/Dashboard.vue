<script setup>
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatTile from '@/Components/Stats/StatTile.vue'

defineProps({
    budget: { type: Array, default: () => [] },
    work: { type: Array, default: () => [] },
    library: { type: Array, default: () => [] },
    recentPosts: { type: Array, default: () => [] },
    openTasks: { type: Array, default: () => [] },
})

const SECTIONS = [
    ['budget', 'Budget', 'admin.expenses.index'],
    ['work', 'Work', 'admin.tasks.board'],
    ['library', 'Library', 'admin.books.index'],
]

const STATUS_LABEL = { todo: 'To do', in_progress: 'In progress' }
</script>

<template>
    <AdminLayout title="Dashboard">
        <div class="flex flex-col gap-8">
            <section v-for="[key, title, routeName] in SECTIONS" :key="key">
                <header class="mb-3 flex items-center justify-between">
                    <h2 class="font-mono text-[11px] font-semibold uppercase tracking-[0.06em] text-surface-500">
                        {{ title }}
                    </h2>
                    <Link :href="route(routeName)" class="font-mono text-[11px] text-surface-500 hover:text-primary">
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
                <section class="rounded-lg border border-surface-200 bg-surface-0 p-4 dark:border-[#272B35] dark:bg-[#15171C]">
                    <header class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-medium tracking-[-0.01em]">Recent posts</h2>
                        <Link :href="route('admin.posts.index')" class="font-mono text-[11px] text-surface-500 hover:text-primary">
                            all →
                        </Link>
                    </header>

                    <p v-if="!recentPosts.length" class="py-8 text-center text-sm text-surface-500">
                        Nothing written yet.
                    </p>

                    <ul v-else class="divide-y divide-surface-100 dark:divide-[#20232B]">
                        <li v-for="post in recentPosts" :key="post.id">
                            <Link
                                :href="route('admin.posts.edit', post.id)"
                                class="flex items-center gap-3 py-2.5 hover:text-primary"
                            >
                                <span class="min-w-0 flex-1 truncate text-[13px]">{{ post.title }}</span>
                                <span class="shrink-0 font-mono text-[11px] text-surface-500">{{ post.updatedAt }}</span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section class="rounded-lg border border-surface-200 bg-surface-0 p-4 dark:border-[#272B35] dark:bg-[#15171C]">
                    <header class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-medium tracking-[-0.01em]">Open tasks</h2>
                        <Link :href="route('admin.tasks.board')" class="font-mono text-[11px] text-surface-500 hover:text-primary">
                            board →
                        </Link>
                    </header>

                    <p v-if="!openTasks.length" class="py-8 text-center text-sm text-surface-500">
                        Nothing open.
                    </p>

                    <ul v-else class="divide-y divide-surface-100 dark:divide-[#20232B]">
                        <li v-for="task in openTasks" :key="task.id" class="flex items-center gap-3 py-2.5">
                            <span
                                class="inline-block h-1.5 w-1.5 shrink-0 rounded-full"
                                :class="task.status === 'in_progress' ? 'bg-primary' : 'bg-surface-400'"
                            />
                            <span class="min-w-0 flex-1 truncate text-[13px]">{{ task.title }}</span>
                            <span v-if="task.repository" class="shrink-0 font-mono text-[11px] text-surface-500">
                                {{ task.repository }}
                            </span>
                            <span class="shrink-0 font-mono text-[11px] text-surface-500">
                                {{ STATUS_LABEL[task.status] }}
                            </span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
