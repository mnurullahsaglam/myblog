<script setup>
import { useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Password from 'primevue/password'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
    twoFactorEnabled: { type: Boolean, default: false },
    twoFactorPending: { type: Boolean, default: false },
})

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
            <section class="rounded-lg border border-surface-200 bg-surface-0 p-4 dark:border-[#272B35] dark:bg-[#15171C]">
                <h2 class="mb-4 text-lg font-medium tracking-[-0.01em]">Change password</h2>

                <form class="flex flex-col gap-4" @submit.prevent="updatePassword">
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="current_password"
                            class="font-mono text-[11px] font-semibold uppercase tracking-[0.06em] text-surface-500"
                        >Current password</label>
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
                            class="font-mono text-[11px] font-semibold uppercase tracking-[0.06em] text-surface-500"
                        >New password</label>
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
                            class="font-mono text-[11px] font-semibold uppercase tracking-[0.06em] text-surface-500"
                        >Confirm new password</label>
                        <Password
                            id="password_confirmation"
                            v-model="passwordForm.password_confirmation"
                            toggle-mask
                            :feedback="false"
                            fluid
                        />
                    </div>

                    <Button
                        type="submit"
                        label="Update password"
                        :loading="passwordForm.processing"
                        class="self-start"
                    />
                </form>
            </section>

            <section class="rounded-lg border border-surface-200 bg-surface-0 p-4 dark:border-[#272B35] dark:bg-[#15171C]">
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
        </div>
    </AdminLayout>
</template>
