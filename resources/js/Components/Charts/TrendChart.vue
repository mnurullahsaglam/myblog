<script setup>
import { computed } from 'vue'
import Chart from 'primevue/chart'
import { AXIS, GRID, chartPalette, humanDuration } from './palette'

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
        borderColor: accent,
        backgroundColor: `${accent}1F`,
        borderWidth: 2,
        fill: true,
        tension: 0.3,
        pointRadius: 0,
        pointHoverRadius: 4,
        pointHoverBackgroundColor: accent,
      },
    ],
  }
})

const options = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: { label: (context) => humanDuration(context.parsed.y) },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: AXIS, font: { family: 'JetBrains Mono', size: 10 }, maxRotation: 0, autoSkipPadding: 16 },
    },
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
  <Chart type="line" :data="data" :options="options" class="h-64" />
</template>
