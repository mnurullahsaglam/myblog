<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ResourceTable from '@/Components/Table/ResourceTable.vue'
import StatTile from '@/Components/Stats/StatTile.vue'
import Button from 'primevue/button'
import PayDebtDialog from './PayDebtDialog.vue'
import { ref } from 'vue'

defineProps({
  schema: { type: Object, required: true },
  rows: { type: Object, required: true },
  tiles: { type: Array, default: () => [] },
})

const payingDebt = ref(null)

function openPayDialog(row) {
  payingDebt.value = {
    id: row.id,
    amount: row.cells.amount.raw,
    formattedAmount: row.cells.amount.display,
    creditorName: row.cells.creditor_name.display,
  }
}
</script>

<template>
  <AdminLayout title="Debts">
    <ResourceTable
      :schema="schema"
      :rows="rows"
      resource="debts"
      label="debt"
      :row-actions="['edit', 'delete']"
      :bulk-actions="['delete']"
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

      <template #rowActions="{ row }">
        <Button
          v-if="row.cells.status.raw === 'pending'"
          icon="pi pi-money-bill"
          text
          rounded
          size="small"
          severity="success"
          aria-label="Record a payment"
          @click="openPayDialog(row)"
        />
      </template>
    </ResourceTable>

    <PayDebtDialog :debt="payingDebt" @close="payingDebt = null" />
  </AdminLayout>
</template>
