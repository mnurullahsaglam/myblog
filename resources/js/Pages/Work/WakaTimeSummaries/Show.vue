<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceTable from '@/Components/Table/ResourceTable.vue'
import StatTile from '@/Components/Stats/StatTile.vue'

defineProps({
    summary: { type: Object, required: true },
    schema: { type: Object, required: true },
    rows: { type: Object, required: true },
})
</script>

<template>
    <AdminLayout :title="summary.date" subtitle="A single day of coding activity.">
        <div class="mb-5 grid gap-3 sm:grid-cols-3">
            <StatTile label="Time coded" :value="summary.duration" icon="pi pi-clock" />
            <StatTile
                label="Seconds"
                :value="summary.totalSeconds.toLocaleString()"
                caption="raw total"
                icon="pi pi-stopwatch"
            />
            <StatTile
                label="Entries"
                :value="summary.entryCount.toLocaleString()"
                caption="languages, editors, projects"
                icon="pi pi-list"
            />
        </div>

        <ResourceTable
            :schema="schema"
            :rows="rows"
            resource="waka-time-summaries"
            label="entry"
            :row-actions="[]"
            :bulk-actions="[]"
        />
    </AdminLayout>
</template>
