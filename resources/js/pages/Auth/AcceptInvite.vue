<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import Mark from '@/Components/Brand/Mark.vue'

const props = defineProps({
  email: { type: String, required: true },
  token: { type: String, required: true },
})

const form = useForm({
  name: '',
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post(route('invite.store', props.token), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <Head title="Accept your invitation" />

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
        <Message severity="info" class="mb-0">Setting up the account for {{ email }}</Message>

        <div class="flex flex-col gap-1.5">
          <label
            for="name"
            class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
            lang="en"
            >Name</label
          >
          <InputText
            id="name"
            v-model="form.name"
            autocomplete="name"
            autofocus
            fluid
            :invalid="Boolean(form.errors.name)"
          />
          <small v-if="form.errors.name" class="text-[#D95757]">{{ form.errors.name }}</small>
        </div>

        <div class="flex flex-col gap-1.5">
          <label
            for="password"
            class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
            lang="en"
            >Password</label
          >
          <Password
            id="password"
            v-model="form.password"
            toggle-mask
            :feedback="false"
            autocomplete="new-password"
            fluid
            :invalid="Boolean(form.errors.password)"
          />
          <small v-if="form.errors.password" class="text-[#D95757]">{{ form.errors.password }}</small>
        </div>

        <div class="flex flex-col gap-1.5">
          <label
            for="password_confirmation"
            class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase"
            lang="en"
            >Confirm password</label
          >
          <Password
            id="password_confirmation"
            v-model="form.password_confirmation"
            toggle-mask
            :feedback="false"
            autocomplete="new-password"
            fluid
          />
        </div>

        <Button type="submit" label="Create my account" :loading="form.processing" class="mt-1" />
      </form>
    </div>
  </div>
</template>
