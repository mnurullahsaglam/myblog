<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import FileUpload from 'primevue/fileupload'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'

const props = defineProps({
    debt: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const visible = ref(false)

const form = useForm({
    payment_amount: null,
    payment_description: '',
    receipt: null,
})

watch(
    () => props.debt,
    (debt) => {
        visible.value = Boolean(debt)

        if (debt) {
            form.reset()
            form.clearErrors()
            form.payment_amount = debt.amount
            form.payment_description = `Payment to ${debt.creditorName}`
        }
    },
)

function submit() {
    form.post(route('admin.debts.pay', props.debt.id), {
        forceFormData: true,
        onSuccess: () => {
            visible.value = false
            emit('close')
        },
    })
}

const labelClass = 'font-mono text-[11px] font-semibold uppercase tracking-[0.06em] text-surface-500'
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        header="Record a payment"
        :style="{ width: '30rem' }"
        @hide="emit('close')"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <p v-if="debt" class="text-sm text-surface-500 dark:text-surface-400">
                Owed to <span class="text-surface-900 dark:text-surface-100">{{ debt.creditorName }}</span>,
                currently <span class="font-mono">{{ debt.formattedAmount }}</span>.
            </p>

            <div class="flex flex-col gap-1.5">
                <label for="payment_amount" :class="labelClass">Payment amount</label>
                <InputNumber
                    id="payment_amount"
                    v-model="form.payment_amount"
                    :min="0.01"
                    :max="debt?.amount"
                    :max-fraction-digits="2"
                    :invalid="Boolean(form.errors.payment_amount)"
                    fluid
                />
                <small v-if="form.errors.payment_amount" class="text-[#D95757]">
                    {{ form.errors.payment_amount }}
                </small>
                <small v-else class="text-surface-500">Pay less than the full amount to record a partial payment.</small>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="payment_description" :class="labelClass">Description</label>
                <Textarea
                    id="payment_description"
                    v-model="form.payment_description"
                    :rows="2"
                    :invalid="Boolean(form.errors.payment_description)"
                />
                <small v-if="form.errors.payment_description" class="text-[#D95757]">
                    {{ form.errors.payment_description }}
                </small>
            </div>

            <div class="flex flex-col gap-1.5">
                <label :class="labelClass">Receipt</label>
                <FileUpload
                    mode="basic"
                    accept="image/*,application/pdf"
                    :max-file-size="5242880"
                    choose-label="Choose file"
                    custom-upload
                    auto
                    @uploader="form.receipt = $event.files[0]"
                />
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <Button type="button" label="Cancel" severity="secondary" text @click="visible = false" />
                <Button type="submit" label="Record payment" :loading="form.processing" />
            </div>
        </form>
    </Dialog>
</template>
