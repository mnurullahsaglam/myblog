<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceTable from '@/Components/Table/ResourceTable.vue'
import StatTile from '@/Components/Stats/StatTile.vue'

defineProps({
  schema: { type: Object, required: true },
  rows: { type: Object, required: true },
  tiles: { type: Array, default: () => [] },
})
</script>

<template>
  <AdminLayout title="Books">
    <ResourceTable
      :schema="schema"
      :rows="rows"
      resource="books"
      label="book"
      :row-actions="['edit', 'delete']"
      :bulk-actions="['delete']"
      exportable
    >
      <template #tiles>
        <div v-if="tiles.length" class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <StatTile
            v-for="tile in tiles"
            :key="tile.label"
            :label="tile.label"
            :value="tile.value"
            :caption="tile.caption"
            :icon="tile.icon"
          />
        </div>
      </template>
    </ResourceTable>
  </AdminLayout>
</template>
