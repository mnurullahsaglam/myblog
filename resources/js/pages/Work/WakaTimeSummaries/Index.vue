<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceTable from '@/Components/Table/ResourceTable.vue'

defineProps({
  schema: { type: Object, required: true },
  rows: { type: Object, required: true },
})
</script>

<template>
  <AdminLayout title="Daily summaries" subtitle="Synced from WakaTime. Read only.">
    <ResourceTable
      :schema="schema"
      :rows="rows"
      resource="waka-time-summaries"
      label="summary"
      :row-actions="['view']"
      :bulk-actions="[]"
    />
  </AdminLayout>
</template>
