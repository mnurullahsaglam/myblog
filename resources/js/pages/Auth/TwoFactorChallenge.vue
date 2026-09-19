<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Mark from '@/Components/Brand/Mark.vue'

const usingRecoveryCode = ref(false)

const form = useForm({
  code: '',
  recovery_code: '',
})

function submit() {
  form.post(route('two-factor.login'))
}
</script>

<template>
  <Head title="Two-factor challenge" />

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
        <p class="text-surface-500 dark:text-surface-400 text-sm">
          {{ usingRecoveryCode ? 'Enter one of your recovery codes.' : 'Enter the code from your authenticator app.' }}
        </p>

        <InputText
          v-if="!usingRecoveryCode"
          v-model="form.code"
          inputmode="numeric"
          autocomplete="one-time-code"
          autofocus
          fluid
          class="font-mono"
          :invalid="Boolean(form.errors.code)"
        />
        <InputText
          v-else
          v-model="form.recovery_code"
          autocomplete="one-time-code"
          autofocus
          fluid
          class="font-mono"
          :invalid="Boolean(form.errors.recovery_code)"
        />

        <small v-if="form.errors.code" class="text-[#D95757]">{{ form.errors.code }}</small>
        <small v-if="form.errors.recovery_code" class="text-[#D95757]">{{ form.errors.recovery_code }}</small>

        <Button type="submit" label="Verify" :loading="form.processing" />

        <Button
          type="button"
          :label="usingRecoveryCode ? 'Use an authenticator code' : 'Use a recovery code'"
          text
          size="small"
          severity="secondary"
          @click="usingRecoveryCode = !usingRecoveryCode"
        />
      </form>
    </div>
  </div>
</template>
