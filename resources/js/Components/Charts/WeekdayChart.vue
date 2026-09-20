<script setup>
import { computed } from 'vue'
import Chart from 'primevue/chart'
import { AXIS, GRID, NEUTRAL, chartPalette, humanDuration } from './palette'

const props = defineProps({
  series: { type: Object, required: true },
})

const data = computed(() => {
  const accent = chartPalette()[0]

  return {
    labels: props.series.labels,
    datasets: [
      {
        data: props.series.data,
        backgroundColor: props.series.data.map((_, index) =>
          props.series.weekendIndexes.includes(index) ? NEUTRAL : accent,
        ),
        borderRadius: 2,
        borderWidth: 0,
      },
    ],
  }
})

const options = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: (context) => humanDuration(context.parsed.y) } },
  },
  scales: {
    x: { grid: { display: false }, ticks: { color: AXIS, font: { family: 'JetBrains Mono', size: 10 } } },
    y: {
      grid: { color: GRID, drawBorder: false },
      ticks: {
        color: AXIS,
        font: { family: 'JetBrains Mono', size: 10 },
        callback: (value) => `${Math.round(value / 3600)}h`,
      },
    },
  },
}))
</script>

<template>
  <Chart type="bar" :data="data" :options="options" class="h-64" />
</template>
