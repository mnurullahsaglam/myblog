<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Password from 'primevue/password'
import Mark from '@/Components/Brand/Mark.vue'

const form = useForm({ password: '' })

function submit() {
  form.post(route('password.confirm'), {
    onFinish: () => form.reset(),
  })
}
</script>

<template>
  <Head title="Confirm password" />

  <div
    class="bg-surface-50 text-surface-900 dark:text-surface-100 flex min-h-screen items-center justify-center px-4 dark:bg-[#0D0E11]"
  >
    <div class="w-full max-w-sm">
      <div class="mb-8 flex items-center gap-2.5">
        <Mark :size="30" />
        <span class="font-mono text-sm font-semibold tracking-tight">OP//SHELL</span>
      </div>

      <form
        class="border-surface-200 bg-surface-0 flex flex-col gap-4 rounded-lg border p-6 dark:border-[#272B35] dark:bg-[#15171C]"
        @submit.prevent="submit"
      >
        <p class="text-surface-500 dark:text-surface-400 text-sm">Confirm your password to continue.</p>

        <Password
          v-model="form.password"
          toggle-mask
          :feedback="false"
          autocomplete="current-password"
          autofocus
          fluid
          :invalid="Boolean(form.errors.password)"
        />
        <small v-if="form.errors.password" class="text-[#D95757]">{{ form.errors.password }}</small>

        <Button type="submit" label="Confirm" :loading="form.processing" />
      </form>
    </div>
  </div>
</template>
