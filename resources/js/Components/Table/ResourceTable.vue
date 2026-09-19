<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import ColumnComponent from 'primevue/column'
import DataTable from 'primevue/datatable'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import { useConfirm } from 'primevue/useconfirm'
import FilterBar from './FilterBar.vue'
import TableCell from './TableCell.vue'
import { useTableState } from '@/composables/useTableState'

const props = defineProps({
  schema: { type: Object, required: true },
  rows: { type: Object, required: true },
  resource: { type: String, required: true },
  label: { type: String, default: 'record' },
  rowActions: { type: Array, default: () => ['edit', 'delete'] },
  bulkActions: { type: Array, default: () => ['delete'] },
  exportable: { type: Boolean, default: false },
})

const confirm = useConfirm()
const { state, reload } = useTableState(props.schema, props.resource)

const storageKey = `table:${props.resource}:columns`

function defaultVisibleKeys() {
  return props.schema.columns.filter((column) => !column.hiddenByDefault).map((column) => column.key)
}

function loadVisibleKeys() {
  try {
    const stored = window.localStorage.getItem(storageKey)

    return stored ? JSON.parse(stored) : defaultVisibleKeys()
  } catch {
    // Storage can be unavailable; column visibility is a convenience only.
    return defaultVisibleKeys()
  }
}

const visibleKeys = ref(loadVisibleKeys())

watch(visibleKeys, (keys) => {
  try {
    window.localStorage.setItem(storageKey, JSON.stringify(keys))
  } catch {
    // Ignored, see above.
  }
})

const visibleColumns = computed(() => props.schema.columns.filter((column) => visibleKeys.value.includes(column.key)))

const toggleableColumns = computed(() => props.schema.columns.filter((column) => column.toggleable))

const selection = ref([])

const sortField = computed(() => state.sort.replace(/^-/, ''))
const sortOrder = computed(() => (state.sort.startsWith('-') ? -1 : 1))

function onSort(event) {
  if (!event.sortField) {
    state.sort = props.schema.defaultSort
  } else {
    state.sort = event.sortOrder === 1 ? event.sortField : `-${event.sortField}`
  }

  reload({ resetPage: true })
}

function onPage(event) {
  state.page = event.page + 1
  state.perPage = event.rows
  reload()
}

function visit(name, ...args) {
  router.visit(route(`admin.${props.resource}.${name}`, ...args))
}

function destroy(row) {
  confirm.require({
    header: `Delete this ${props.label}?`,
    message: 'This cannot be undone.',
    icon: 'pi pi-exclamation-triangle',
    acceptProps: { label: 'Delete', severity: 'danger' },
    rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
    accept: () => router.delete(route(`admin.${props.resource}.destroy`, row.id), { preserveScroll: true }),
  })
}

function destroySelected() {
  const ids = selection.value.map((row) => row.id)

  confirm.require({
    header: `Delete ${ids.length} ${props.label}s?`,
    message: 'They will be permanently removed. This cannot be undone.',
    icon: 'pi pi-exclamation-triangle',
    acceptProps: { label: 'Delete', severity: 'danger' },
    rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
    accept: () =>
      router.delete(route(`admin.${props.resource}.bulk-destroy`), {
        data: { ids },
        preserveScroll: true,
        onSuccess: () => (selection.value = []),
      }),
  })
}

function runExport() {
  router.post(route('admin.exports.store', props.resource), {}, { preserveScroll: true })
}
</script>

<template>
  <div>
    <slot name="tiles" />

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <IconField v-if="schema.searchable">
        <InputIcon class="pi pi-search" />
        <InputText v-model="state.search" placeholder="Search" size="small" class="w-64" />
      </IconField>

      <MultiSelect
        v-if="toggleableColumns.length"
        v-model="visibleKeys"
        :options="schema.columns"
        option-label="label"
        option-value="key"
        :max-selected-labels="0"
        selected-items-label="Columns"
        placeholder="Columns"
        size="small"
        class="w-36"
      />

      <div class="ml-auto flex items-center gap-2">
        <Button
          v-if="bulkActions.includes('delete') && selection.length"
          :label="`Delete ${selection.length}`"
          icon="pi pi-trash"
          severity="danger"
          outlined
          size="small"
          @click="destroySelected"
        />

        <slot name="toolbar" />

        <Button
          v-if="exportable"
          label="Export CSV"
          icon="pi pi-download"
          severity="secondary"
          outlined
          size="small"
          @click="runExport"
        />

        <Button
          v-if="rowActions.includes('edit')"
          label="New"
          icon="pi pi-plus"
          size="small"
          @click="visit('create')"
        />
      </div>
    </div>

    <FilterBar v-model="state.filters" :filters="schema.filters" />

    <div class="border-surface-200 overflow-hidden rounded-lg border dark:border-[#272B35]">
      <DataTable
        v-model:selection="selection"
        :value="rows.data"
        lazy
        paginator
        :rows="rows.per_page"
        :total-records="rows.total"
        :first="(rows.current_page - 1) * rows.per_page"
        :rows-per-page-options="[10, 25, 50, 100]"
        :sort-field="sortField"
        :sort-order="sortOrder"
        data-key="id"
        size="small"
        removable-sort
        paginator-template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink RowsPerPageDropdown"
        current-page-report-template="{first}–{last} of {totalRecords}"
        @sort="onSort"
        @page="onPage"
      >
        <template #empty>
          <div class="text-surface-500 py-12 text-center text-sm">Nothing here yet.</div>
        </template>

        <ColumnComponent v-if="bulkActions.length" selection-mode="multiple" header-style="width: 3rem" />

        <ColumnComponent
          v-for="column in visibleColumns"
          :key="column.key"
          :field="column.key"
          :header="column.label"
          :sortable="column.sortable"
          :body-class="column.align === 'right' ? 'text-right' : ''"
          :header-class="column.align === 'right' ? 'text-right' : ''"
        >
          <template #body="{ data }">
            <TableCell :cell="data.cells[column.key]" :type="column.type" />
          </template>
        </ColumnComponent>

        <ColumnComponent header-style="width: 8rem" body-class="text-right">
          <template #body="{ data }">
            <div class="flex justify-end gap-0.5">
              <slot name="rowActions" :row="data" />

              <Button
                v-if="rowActions.includes('view')"
                icon="pi pi-eye"
                text
                rounded
                size="small"
                severity="secondary"
                aria-label="View"
                @click="visit('show', data.id)"
              />
              <Button
                v-if="rowActions.includes('edit')"
                icon="pi pi-pencil"
                text
                rounded
                size="small"
                severity="secondary"
                aria-label="Edit"
                @click="visit('edit', data.id)"
              />
              <Button
                v-if="rowActions.includes('delete')"
                icon="pi pi-trash"
                text
                rounded
                size="small"
                severity="danger"
                aria-label="Delete"
                @click="destroy(data)"
              />
            </div>
          </template>
        </ColumnComponent>
      </DataTable>
    </div>
  </div>
</template>
