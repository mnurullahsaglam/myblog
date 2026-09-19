<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import { useConfirm } from 'primevue/useconfirm'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePasskeys } from '@/composables/usePasskeys'

defineProps({
  twoFactorEnabled: { type: Boolean, default: false },
  twoFactorPending: { type: Boolean, default: false },
  passkeys: { type: Array, default: () => [] },
})

const confirm = useConfirm()
const webauthn = usePasskeys()

const passkeyName = ref(defaultPasskeyName())

function defaultPasskeyName() {
  const platform = navigator.userAgentData?.platform ?? navigator.platform ?? 'This device'

  return `${platform} · ${new Date().toISOString().slice(0, 10)}`
}

async function addPasskey() {
  const added = await webauthn.register(passkeyName.value.trim() || 'Passkey')

  if (added) {
    passkeyName.value = defaultPasskeyName()
  }
}

function removePasskey(passkey) {
  confirm.require({
    header: 'Remove this passkey?',
    message: `"${passkey.name}" will no longer be able to sign you in.`,
    icon: 'pi pi-exclamation-triangle',
    acceptProps: { label: 'Remove', severity: 'danger' },
    rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
    accept: () => router.delete(route('passkey.destroy', passkey.id), { preserveScroll: true }),
  })
}

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

function updatePassword() {
  passwordForm.put(route('user-password.update'), {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
  })
}

const twoFactorForm = useForm({})

function enableTwoFactor() {
  twoFactorForm.post(route('two-factor.enable'), { preserveScroll: true })
}

function disableTwoFactor() {
  twoFactorForm.delete(route('two-factor.disable'), { preserveScroll: true })
}
</script>

<template>
  <AdminLayout title="Profile" subtitle="Credentials and second factor.">
    <div class="flex max-w-2xl flex-col gap-5">
      <section class="border-surface-200 bg-surface-0 rounded-lg border p-4 dark:border-[#272B35] dark:bg-[#15171C]">
        <h2 class="mb-4 text-lg font-medium tracking-[-0.01em]">Change password</h2>

        <form class="flex flex-col gap-4" @submit.prevent="updatePassword">
          <div class="flex flex-col gap-1.5">
            <label
              for="current_password"
              class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
              >Current password</label
            >
            <Password
              id="current_password"
              v-model="passwordForm.current_password"
              toggle-mask
              :feedback="false"
              fluid
              :invalid="Boolean(passwordForm.errors.current_password)"
            />
            <small v-if="passwordForm.errors.current_password" class="text-[#D95757]">
              {{ passwordForm.errors.current_password }}
            </small>
          </div>

          <div class="flex flex-col gap-1.5">
            <label
              for="password"
              class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
              >New password</label
            >
            <Password
              id="password"
              v-model="passwordForm.password"
              toggle-mask
              fluid
              :invalid="Boolean(passwordForm.errors.password)"
            />
            <small v-if="passwordForm.errors.password" class="text-[#D95757]">
              {{ passwordForm.errors.password }}
            </small>
          </div>

          <div class="flex flex-col gap-1.5">
            <label
              for="password_confirmation"
              class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
              >Confirm new password</label
            >
            <Password
              id="password_confirmation"
              v-model="passwordForm.password_confirmation"
              toggle-mask
              :feedback="false"
              fluid
            />
          </div>

          <Button type="submit" label="Update password" :loading="passwordForm.processing" class="self-start" />
        </form>
      </section>

      <section class="border-surface-200 bg-surface-0 rounded-lg border p-4 dark:border-[#272B35] dark:bg-[#15171C]">
        <h2 class="mb-2 text-lg font-medium tracking-[-0.01em]">Two-factor authentication</h2>

        <div class="mb-4 flex items-center gap-2 font-mono text-[11px]">
          <span
            class="inline-block h-1.5 w-1.5 rounded-full"
            :class="twoFactorEnabled ? 'bg-[#529E72]' : 'bg-[#5A606E]'"
          />
          <span :class="twoFactorEnabled ? 'text-[#529E72]' : 'text-surface-500'">
            {{ twoFactorEnabled ? 'Enabled' : twoFactorPending ? 'Awaiting confirmation' : 'Disabled' }}
          </span>
        </div>

        <Button
          v-if="!twoFactorEnabled && !twoFactorPending"
          label="Enable"
          :loading="twoFactorForm.processing"
          @click="enableTwoFactor"
        />
        <Button
          v-else
          label="Disable"
          severity="danger"
          outlined
          :loading="twoFactorForm.processing"
          @click="disableTwoFactor"
        />
      </section>

      <section class="border-surface-200 bg-surface-0 rounded-lg border p-4 dark:border-[#272B35] dark:bg-[#15171C]">
        <h2 class="mb-1 text-lg font-medium tracking-[-0.01em]">Passkeys</h2>
        <p class="text-surface-500 dark:text-surface-400 mb-4 text-sm">Sign in with Touch ID instead of a password.</p>

        <ul v-if="passkeys.length" class="divide-surface-200 mb-4 flex flex-col divide-y dark:divide-[#20232B]">
          <li v-for="passkey in passkeys" :key="passkey.id" class="flex items-center gap-3 py-2.5">
            <i class="pi pi-lock text-surface-500" style="font-size: 0.8rem" />

            <div class="min-w-0 flex-1">
              <p class="truncate text-sm">{{ passkey.name }}</p>
              <p class="text-surface-500 font-mono text-[11px]">
                added {{ passkey.createdAt }}
                <template v-if="passkey.lastUsedAt"> · used {{ passkey.lastUsedAt }}</template>
                <template v-else> · never used</template>
              </p>
            </div>

            <Button
              icon="pi pi-trash"
              text
              rounded
              size="small"
              severity="danger"
              aria-label="Remove passkey"
              @click="removePasskey(passkey)"
            />
          </li>
        </ul>

        <p v-else class="text-surface-500 dark:text-surface-400 mb-4 text-sm">No passkeys yet.</p>

        <div class="flex flex-wrap items-center gap-2">
          <InputText
            v-model="passkeyName"
            placeholder="Name this device"
            class="min-w-56 flex-1"
            :disabled="!webauthn.isSupported()"
          />
          <Button
            label="Add passkey"
            icon="pi pi-plus"
            :loading="webauthn.busy.value"
            :disabled="!webauthn.isSupported()"
            @click="addPasskey"
          />
        </div>

        <small v-if="webauthn.error.value" class="mt-2 block text-[#D95757]">
          {{ webauthn.error.value }}
        </small>
        <small v-else-if="!webauthn.isSupported()" class="text-surface-500 mt-2 block">{{
          webauthn.unsupportedReason()
        }}</small>
      </section>
    </div>
  </AdminLayout>
</template>
