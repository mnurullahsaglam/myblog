<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  caption: { type: String, default: null },
  icon: { type: String, default: null },
})
</script>

<template>
  <div class="border-surface-200 bg-surface-0 rounded-lg border p-4 dark:border-[#272B35] dark:bg-[#15171C]">
    <div class="flex items-start justify-between gap-2">
      <p class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase" lang="en">
        {{ label }}
      </p>
      <i v-if="icon" :class="icon" class="text-surface-500" style="font-size: 0.8rem" />
    </div>

    <p class="mt-2 font-mono text-[26px] leading-8 font-medium tracking-[-0.03em] tabular-nums">
      {{ value }}
    </p>

    <p v-if="caption" class="text-surface-500 mt-1 font-mono text-[11px]">{{ caption }}</p>
  </div>
</template>
