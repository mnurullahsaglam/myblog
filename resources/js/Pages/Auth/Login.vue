<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import Mark from '@/Components/Brand/Mark.vue'

defineProps({
    status: { type: String, default: null },
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Sign in" />

    <div
        class="flex min-h-screen items-center justify-center bg-surface-50 px-4 text-surface-900 dark:bg-[#0D0E11] dark:text-surface-100"
    >
        <div class="w-full max-w-sm">
            <div class="mb-8 flex items-center gap-2.5">
                <Mark :size="30" />
                <span class="font-mono text-sm font-semibold tracking-tight">OP//SHELL</span>
            </div>

            <Message v-if="status" severity="info" class="mb-4">{{ status }}</Message>

            <form
                class="flex flex-col gap-4 rounded-lg border border-surface-200 bg-surface-0 p-6 dark:border-[#272B35] dark:bg-[#15171C]"
                @submit.prevent="submit"
            >
                <div class="flex flex-col gap-1.5">
                    <label
                        for="email"
                        class="font-mono text-[11px] font-semibold uppercase tracking-[0.06em] text-surface-500"
                    >Email</label>
                    <InputText
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        autofocus
                        fluid
                        :invalid="Boolean(form.errors.email)"
                    />
                    <small v-if="form.errors.email" class="text-[#D95757]">{{ form.errors.email }}</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label
                        for="password"
                        class="font-mono text-[11px] font-semibold uppercase tracking-[0.06em] text-surface-500"
                    >Password</label>
                    <Password
                        id="password"
                        v-model="form.password"
                        toggle-mask
                        :feedback="false"
                        autocomplete="current-password"
                        fluid
                        :invalid="Boolean(form.errors.password)"
                    />
                    <small v-if="form.errors.password" class="text-[#D95757]">{{ form.errors.password }}</small>
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.remember" input-id="remember" binary />
                    <label for="remember" class="text-sm">Keep me signed in</label>
                </div>

                <Button type="submit" label="Sign in" :loading="form.processing" class="mt-1" />
            </form>
        </div>
    </div>
</template>
