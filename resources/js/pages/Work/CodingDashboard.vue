<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import SelectButton from 'primevue/selectbutton'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatTile from '@/Components/Stats/StatTile.vue'
import ChartCard from '@/Components/Charts/ChartCard.vue'
import TrendChart from '@/Components/Charts/TrendChart.vue'
import WeekdayChart from '@/Components/Charts/WeekdayChart.vue'
import BreakdownChart from '@/Components/Charts/BreakdownChart.vue'

const props = defineProps({
    data: { type: Object, required: true },
    ranges: { type: Array, required: true },
})

const range = ref(props.data.range)

watch(range, (value) => {
    if (!value || value === props.data.range) {
        return
    }

    router.get(route('admin.coding-dashboard'), { range: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
})

const BREAKDOWNS = [
    ['language', 'Languages'],
    ['editor', 'Editors'],
    ['project', 'Projects'],
    ['category', 'Categories'],
    ['operating_system', 'Operating systems'],
]
</script>

<template>
    <AdminLayout title="Coding analytics" subtitle="WakaTime activity, synced automatically.">
        <template #actions>
            <SelectButton
                v-model="range"
                :options="ranges"
                option-label="label"
                option-value="value"
                :allow-empty="false"
                size="small"
            />
        </template>

        <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <StatTile
                v-for="tile in data.tiles"
                :key="tile.label"
                :label="tile.label"
                :value="tile.value"
                :caption="tile.caption"
                :icon="tile.icon"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <ChartCard
                title="Daily trend"
                :subtitle="data.trend.peak ? `Peak ${data.trend.peak.value} on ${data.trend.peak.label}` : null"
                :badge="data.rangeLabel"
                :empty="!data.trend.labels.length"
                class="lg:col-span-2"
            >
                <TrendChart :series="data.trend" />
            </ChartCard>

            <ChartCard
                title="Weekday distribution"
                :subtitle="`Weekdays ${data.weekday.weekdayAverage}/day · weekends ${data.weekday.weekendAverage}/day`"
                :empty="!data.weekday.data.some((value) => value > 0)"
            >
                <WeekdayChart :series="data.weekday" />
            </ChartCard>

            <ChartCard
                v-for="[key, title] in BREAKDOWNS"
                :key="key"
                :title="title"
                :badge="`${data.breakdowns[key].labels.length} tracked`"
                :empty="!data.breakdowns[key].labels.length"
            >
                <BreakdownChart :series="data.breakdowns[key]" />
            </ChartCard>
        </div>

        <p v-if="data.syncedAt" class="mt-5 font-mono text-[11px] text-surface-500">
            Last synced {{ data.syncedAt }}
        </p>
    </AdminLayout>
</template>
