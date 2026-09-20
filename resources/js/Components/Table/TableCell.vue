<script setup>
import Tag from 'primevue/tag'

defineProps({
  cell: { type: Object, required: true },
  type: { type: String, required: true },
})

const SEVERITY = {
  primary: 'primary',
  secondary: 'secondary',
  success: 'success',
  warning: 'warn',
  danger: 'danger',
  info: 'info',
  gray: 'secondary',
}

function severity(variant) {
  return SEVERITY[variant] ?? 'secondary'
}
</script>

<template>
  <Tag
    v-if="type === 'badge' && cell.display"
    :value="cell.display"
    :severity="severity(cell.variant)"
    class="!font-mono !text-[11px]"
  />

  <i
    v-else-if="type === 'boolean'"
    :class="cell.raw ? 'pi pi-check-circle text-[#529E72]' : 'pi pi-minus text-surface-500'"
    style="font-size: 0.8rem"
    :aria-label="cell.display"
  />

  <img
    v-else-if="type === 'image' && cell.display"
    :src="cell.display"
    alt=""
    loading="lazy"
    class="object-cover"
    :class="cell.meta.circular ? 'rounded-full' : 'rounded'"
    :style="{ width: `${cell.meta.size}px`, height: `${cell.meta.size}px` }"
  />

  <span
    v-else-if="type === 'image'"
    class="border-surface-300 inline-block rounded border border-dashed dark:border-[#272B35]"
    :style="{ width: `${cell.meta.size ?? 32}px`, height: `${cell.meta.size ?? 32}px` }"
    aria-label="No image"
  />

  <span
    v-else-if="['money', 'date', 'datetime', 'count', 'number'].includes(type)"
    class="font-mono text-[13px] tabular-nums"
    >{{ cell.display }}</span
  >

  <span v-else v-tooltip.top="cell.tooltip ?? undefined" class="text-[13px]">{{ cell.display || '—' }}</span>
</template>
