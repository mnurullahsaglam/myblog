<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceTable from '@/Components/Table/ResourceTable.vue'

defineProps({
  schema: { type: Object, required: true },
  rows: { type: Object, required: true },
})
</script>

<template>
  <AdminLayout title="Projects">
    <ResourceTable
      :schema="schema"
      :rows="rows"
      resource="projects"
      label="project"
      :row-actions="['edit', 'delete']"
      :bulk-actions="['delete']"
    />
  </AdminLayout>
</template>
