<script setup>
import { updatePreset } from '@primeuix/themes'
import { RAMPS } from '@/theme/ramps'

defineProps({
  accents: { type: Array, required: true },
  modelValue: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue'])

function choose(name) {
  emit('update:modelValue', name)

  updatePreset({ semantic: { primary: RAMPS[name] } })
}
</script>

<template>
  <div class="flex flex-wrap gap-2.5">
    <button
      v-for="accent in accents"
      :key="accent.name"
      type="button"
      class="flex h-10 w-10 items-center justify-center rounded border-2 transition-colors"
      :class="
        accent.name === modelValue
          ? 'border-surface-900 dark:border-surface-0'
          : 'hover:border-surface-300 border-transparent dark:hover:border-[#5A606E]'
      "
      :style="{ backgroundColor: accent.swatch }"
      :aria-label="accent.name"
      :aria-pressed="accent.name === modelValue"
      :title="accent.name"
      @click="choose(accent.name)"
    >
      <i v-if="accent.name === modelValue" class="pi pi-check text-sm" style="color: #141517" />
    </button>
  </div>
</template>
