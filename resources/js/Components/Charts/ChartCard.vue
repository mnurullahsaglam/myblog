<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    badge: { type: String, default: null },
    empty: { type: Boolean, default: false },
})
</script>

<template>
    <section class="rounded-lg border border-surface-200 bg-surface-0 p-4 dark:border-[#272B35] dark:bg-[#15171C]">
        <header class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-medium tracking-[-0.01em]">{{ title }}</h2>
                <p v-if="subtitle" class="mt-0.5 font-mono text-[11px] text-surface-500">{{ subtitle }}</p>
            </div>
            <span
                v-if="badge"
                class="shrink-0 rounded border border-surface-200 px-2 py-0.5 font-mono text-[11px] text-surface-500 dark:border-[#272B35]"
            >{{ badge }}</span>
        </header>

        <div v-if="empty" class="py-12 text-center text-sm text-surface-500">No data in this range.</div>
        <slot v-else />
    </section>
</template>
