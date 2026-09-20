<script setup>
import { computed } from 'vue'
import Chart from 'primevue/chart'
import { chartPalette } from './palette'

const props = defineProps({
  series: { type: Object, required: true },
})

const colors = computed(() => {
  const palette = chartPalette()

  return props.series.labels.map((_, index) => palette[index % palette.length])
})

const data = computed(() => ({
  labels: props.series.labels,
  datasets: [
    {
      data: props.series.data,
      backgroundColor: colors.value,
      borderWidth: 0,
      hoverOffset: 4,
    },
  ],
}))

const options = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '68%',
  // The legend is rendered as a list beside the chart instead.
  plugins: { legend: { display: false }, tooltip: { enabled: false } },
}
</script>

<template>
  <div class="flex flex-col items-center gap-5 sm:flex-row">
    <div class="relative h-40 w-40 shrink-0">
      <Chart type="doughnut" :data="data" :options="options" class="h-40 w-40" />

      <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
        <span class="font-mono text-[18px] font-medium tabular-nums">{{ series.total }}</span>
        <span class="text-surface-500 font-mono text-[10px] tracking-[0.06em] uppercase" lang="en">total</span>
      </div>
    </div>

    <ul class="w-full min-w-0 flex-1 space-y-1.5">
      <li v-for="(label, index) in series.labels" :key="label" class="flex items-center gap-2.5 text-[13px]">
        <span class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: colors[index] }" />
        <span class="min-w-0 flex-1 truncate">{{ label }}</span>
        <span class="text-surface-500 shrink-0 font-mono text-[11px] tabular-nums">
          {{ series.durations[index] }}
        </span>
        <span class="w-12 shrink-0 text-right font-mono text-[11px] font-medium tabular-nums">
          {{ series.percentages[index] }}%
        </span>
      </li>
    </ul>
  </div>
</template>
