<script setup>
defineProps({
    size: { type: Number, default: 26 },
})
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 32 32"
        fill="none"
        aria-hidden="true"
        class="shrink-0"
    >
        <rect
            x="0.5"
            y="0.5"
            width="31"
            height="31"
            rx="7"
            class="fill-surface-100 stroke-surface-300 dark:fill-surface-900 dark:stroke-surface-700"
            stroke-width="1"
        />
        <path
            d="M12 10.5 L19 16 L12 21.5"
            class="stroke-primary"
            stroke-width="2.4"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
