<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Mark from '@/Components/Brand/Mark.vue'

defineProps({
  status: { type: String, default: null },
})

const form = useForm({
  email: '',
})

function submit() {
  form.post(route('password.email'))
}
</script>

<template>
  <Head title="Forgot your password" />

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
        <p class="text-surface-500 text-sm">Enter your address and we will send you a link to choose a new password.</p>

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

        <Button type="submit" label="Send the link" :loading="form.processing" class="mt-1" />
      </form>

      <Link
        :href="route('login')"
        class="text-surface-500 hover:text-primary mt-5 block text-center font-mono text-[11px]"
      >
        back to sign in
      </Link>
    </div>
  </div>
</template>
