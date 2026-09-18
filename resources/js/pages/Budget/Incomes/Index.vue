<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceTable from '@/Components/Table/ResourceTable.vue'

defineProps({
    schema: { type: Object, required: true },
    rows: { type: Object, required: true },
})
</script>

<template>
    <AdminLayout title="Incomes">
        <ResourceTable
            :schema="schema"
            :rows="rows"
            resource="incomes"
            label="income"
            :row-actions="['view', 'edit', 'delete']"
            :bulk-actions="['delete']"
        />
    </AdminLayout>
</template>
