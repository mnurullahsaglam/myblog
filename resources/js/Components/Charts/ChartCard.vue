<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: null },
  badge: { type: String, default: null },
  empty: { type: Boolean, default: false },
})
</script>

<template>
  <section class="border-surface-200 bg-surface-0 rounded-lg border p-4 dark:border-[#272B35] dark:bg-[#15171C]">
    <header class="mb-4 flex items-start justify-between gap-3">
      <div>
        <h2 class="text-lg font-medium tracking-[-0.01em]">{{ title }}</h2>
        <p v-if="subtitle" class="text-surface-500 mt-0.5 font-mono text-[11px]">{{ subtitle }}</p>
      </div>
      <span
        v-if="badge"
        class="border-surface-200 text-surface-500 shrink-0 rounded border px-2 py-0.5 font-mono text-[11px] dark:border-[#272B35]"
        >{{ badge }}</span
      >
    </header>

    <div v-if="empty" class="text-surface-500 py-12 text-center text-sm">No data in this range.</div>
    <slot v-else />
  </section>
</template>
