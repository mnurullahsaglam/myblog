<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import Mark from '@/Components/Brand/Mark.vue'
import { usePasskeys } from '@/composables/usePasskeys'

defineProps({
  status: { type: String, default: null },
})

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const passkeys = usePasskeys()

function submit() {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  })
}

function signInWithPasskey() {
  passkeys.login(form.remember)
}
</script>

<template>
  <Head title="Sign in" />

  <div
    class="bg-surface-50 text-surface-900 dark:text-surface-100 flex min-h-screen items-center justify-center px-4 dark:bg-[#0D0E11]"
  >
    <div class="w-full max-w-sm">
      <div class="mb-8 flex items-center gap-2.5">
        <Mark :size="30" />
        <span class="font-mono text-sm font-semibold tracking-tight">OP//SHELL</span>
      </div>

      <Message v-if="status" severity="info" class="mb-4">{{ status }}</Message>

      <form
        class="border-surface-200 bg-surface-0 flex flex-col gap-4 rounded-lg border p-6 dark:border-[#272B35] dark:bg-[#15171C]"
        @submit.prevent="submit"
      >
        <div class="flex flex-col gap-1.5">
          <label for="email" class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
            >Email</label
          >
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
          <label for="password" class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
            >Password</label
          >
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

      <div class="my-5 flex items-center gap-3">
        <span class="bg-surface-200 h-px flex-1 dark:bg-[#272B35]" />
        <span class="text-surface-500 font-mono text-[11px] tracking-[0.06em] uppercase">or</span>
        <span class="bg-surface-200 h-px flex-1 dark:bg-[#272B35]" />
      </div>

      <Button
        type="button"
        label="Sign in with a passkey"
        icon="pi pi-lock"
        severity="secondary"
        outlined
        fluid
        :loading="passkeys.busy.value"
        @click="signInWithPasskey"
      />

      <small v-if="passkeys.error.value" class="mt-2 block text-[#D95757]">
        {{ passkeys.error.value }}
      </small>
    </div>
  </div>
</template>
