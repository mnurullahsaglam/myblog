<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
  users: { type: Object, required: true },
  devices: { type: Array, default: () => [] },
  invites: { type: Object, required: true },
  roles: { type: Array, required: true },
})

const page = usePage()

const inviteUrl = computed(() => page.props.flash?.invite_url ?? null)

const dialogOpen = ref(false)
const copied = ref(false)

const form = useForm({
  email: '',
  role: 'member',
})

function submit() {
  form.post(route('admin.people.store'), {
    onSuccess: () => {
      dialogOpen.value = false
      form.reset()
    },
  })
}

function copyLink() {
  navigator.clipboard.writeText(inviteUrl.value).then(() => {
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  })
}

function revokeDevice(id) {
  router.delete(route('admin.people.revoke-device', id), { preserveScroll: true })
}

function revoke(id) {
  router.delete(route('admin.people.revoke', id), { preserveScroll: true })
}

function reissue(id) {
  router.post(route('admin.people.reissue', id), {}, { preserveScroll: true })
}

function cell(row, index) {
  return row.cells[index]
}

function isPending(row) {
  return cell(row, 2).display === 'Pending'
}
</script>

<template>
  <AdminLayout title="People" subtitle="Who can reach the panel, and who has been asked to.">
    <Head title="People" />

    <div class="flex flex-col gap-8">
      <Message v-if="inviteUrl" severity="info" :closable="false">
        <div class="flex flex-col gap-2">
          <span class="text-sm">Send this link. It works once and expires in 48 hours.</span>
          <div class="flex items-center gap-2">
            <code class="min-w-0 flex-1 truncate font-mono text-[11px]">{{ inviteUrl }}</code>
            <Button :label="copied ? 'Copied' : 'Copy'" size="small" severity="secondary" outlined @click="copyLink" />
          </div>
        </div>
      </Message>

      <section>
        <header class="mb-3 flex items-center justify-between">
          <h2 class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase" lang="en">
            Accounts
          </h2>
        </header>

        <DataTable :value="users.rows.data" data-key="id" size="small">
          <Column v-for="(column, index) in users.schema.columns" :key="column.key" :header="column.label">
            <template #body="{ data }">{{ cell(data, index).display }}</template>
          </Column>
        </DataTable>
      </section>

      <section>
        <header class="mb-3 flex items-center justify-between">
          <h2 class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase" lang="en">
            Invitations
          </h2>
          <Button label="Invite someone" icon="pi pi-plus" size="small" @click="dialogOpen = true" />
        </header>

        <DataTable :value="invites.rows.data" data-key="id" size="small">
          <Column v-for="(column, index) in invites.schema.columns" :key="column.key" :header="column.label">
            <template #body="{ data }">
              <Tag
                v-if="column.type === 'badge'"
                :value="cell(data, index).display"
                :severity="cell(data, index).variant ?? 'secondary'"
              />
              <span v-else>{{ cell(data, index).display }}</span>
            </template>
          </Column>

          <Column header="">
            <template #body="{ data }">
              <div v-if="isPending(data)" class="flex justify-end gap-2">
                <Button label="Reissue" size="small" severity="secondary" outlined @click="reissue(data.id)" />
                <Button label="Revoke" size="small" severity="danger" outlined @click="revoke(data.id)" />
              </div>
            </template>
          </Column>
        </DataTable>
      </section>
      <section>
        <header class="mb-3 flex items-center justify-between">
          <h2 class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase" lang="en">
            Devices
          </h2>
        </header>

        <DataTable :value="devices" data-key="id" size="small">
          <Column field="name" header="Device" />
          <Column field="owner" header="Owner" />
          <Column field="createdAt" header="Added" />
          <Column field="lastUsedAt" header="Last used" />
          <Column header="">
            <template #body="{ data }">
              <div class="flex justify-end">
                <Button label="Revoke" size="small" severity="danger" outlined @click="revokeDevice(data.id)" />
              </div>
            </template>
          </Column>
        </DataTable>
      </section>
    </div>

    <Dialog v-model:visible="dialogOpen" modal header="Invite someone" :style="{ width: '24rem' }">
      <form class="flex flex-col gap-4" @submit.prevent="submit">
        <div class="flex flex-col gap-1.5">
          <label
            for="email"
            class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
            lang="en"
            >Email</label
          >
          <InputText id="email" v-model="form.email" type="email" fluid :invalid="Boolean(form.errors.email)" />
          <small v-if="form.errors.email" class="text-[#D95757]">{{ form.errors.email }}</small>
        </div>

        <div class="flex flex-col gap-1.5">
          <label
            for="role"
            class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
            lang="en"
            >Role</label
          >
          <Select
            id="role"
            v-model="form.role"
            :options="roles"
            option-label="label"
            option-value="value"
            fluid
            :invalid="Boolean(form.errors.role)"
          />
          <small v-if="form.errors.role" class="text-[#D95757]">{{ form.errors.role }}</small>
        </div>

        <div class="flex justify-end gap-2">
          <Button type="button" label="Cancel" severity="secondary" outlined @click="dialogOpen = false" />
          <Button type="submit" label="Send invitation" :loading="form.processing" />
        </div>
      </form>
    </Dialog>
  </AdminLayout>
</template>
