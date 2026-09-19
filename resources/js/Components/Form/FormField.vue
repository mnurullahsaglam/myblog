<script setup>
import { computed, ref } from 'vue'
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import FileUpload from 'primevue/fileupload'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { default: null },
    error: { type: String, default: null },
    placeholderValue: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])
const toast = useToast()

function update(value) {
    emit('update:modelValue', value)
}

function toIsoDate(date, withTime) {
    if (!date) {
        return null
    }

    const offset = date.getTimezoneOffset() * 60000
    const local = new Date(date.getTime() - offset).toISOString()

    return withTime ? local.slice(0, 19).replace('T', ' ') : local.slice(0, 10)
}

const markdownMode = ref('write')

const renderedMarkdown = computed(() => {
    const source = String(props.modelValue ?? '')

    // Deliberately minimal: a structural preview, not a Markdown engine.
    return source
        .replace(/[&<>]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[char])
        .replace(/^### (.*)$/gm, '<h3>$1</h3>')
        .replace(/^## (.*)$/gm, '<h2>$1</h2>')
        .replace(/^# (.*)$/gm, '<h1>$1</h1>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/`(.+?)`/g, '<code>$1</code>')
        .replace(/\n{2,}/g, '</p><p>')
        .replace(/^/, '<p>')
        .concat('</p>')
})

async function copyValue() {
    try {
        await navigator.clipboard.writeText(String(props.modelValue ?? ''))
        toast.add({ severity: 'success', summary: 'Copied', life: 2000 })
    } catch {
        toast.add({ severity: 'error', summary: 'Could not copy', life: 3000 })
    }
}

const labelClass =
    'font-mono text-[11px] font-semibold uppercase tracking-[0.06em] text-surface-500'
</script>

<template>
    <div
        v-if="field.type !== 'hidden'"
        class="flex flex-col gap-1.5"
        :class="field.columnSpan === 2 ? 'sm:col-span-2' : ''"
    >
        <label v-if="field.type !== 'toggle'" :for="field.key" :class="labelClass">
            {{ field.label }}
            <span v-if="field.required" class="text-[#D95757]">*</span>
        </label>

        <p v-if="field.type === 'placeholder'" class="font-mono text-[13px] text-surface-500">
            {{ placeholderValue ?? '—' }}
        </p>

        <div
            v-else-if="field.type === 'readonlyCode'"
            class="flex items-center gap-2 rounded border border-surface-200 bg-surface-100 px-3 py-2 dark:border-[#272B35] dark:bg-[#121317]"
        >
            <i class="pi pi-lock text-surface-500" style="font-size: 0.75rem" />
            <code class="flex-1 truncate font-mono text-[13px] text-surface-600 dark:text-surface-300">
                {{ modelValue || '—' }}
            </code>
            <Button
                icon="pi pi-copy"
                text
                rounded
                size="small"
                severity="secondary"
                aria-label="Copy"
                @click="copyValue"
            />
        </div>

        <div v-else-if="field.type === 'markdown'" class="flex flex-col gap-2">
            <div class="flex gap-1">
                <Button
                    v-for="mode in ['write', 'preview', 'split']"
                    :key="mode"
                    :label="mode[0].toUpperCase() + mode.slice(1)"
                    text
                    size="small"
                    :severity="markdownMode === mode ? 'primary' : 'secondary'"
                    :class="markdownMode === mode ? 'bg-primary/10' : ''"
                    @click="markdownMode = mode"
                />
            </div>

            <div :class="markdownMode === 'split' ? 'grid gap-3 lg:grid-cols-2' : ''">
                <Textarea
                    v-if="markdownMode !== 'preview'"
                    :id="field.key"
                    :model-value="modelValue"
                    :rows="field.meta.rows ?? 20"
                    :disabled="field.disabled"
                    :invalid="Boolean(error)"
                    class="w-full font-mono !text-[13px]"
                    @update:model-value="update"
                />

                <article
                    v-if="markdownMode !== 'write'"
                    class="prose-sm max-w-none overflow-auto rounded border border-surface-200 p-3 text-[13px] dark:border-[#272B35]"
                    v-html="renderedMarkdown"
                />
            </div>
        </div>

        <Textarea
            v-else-if="field.type === 'textarea'"
            :id="field.key"
            :model-value="modelValue"
            :rows="field.meta.rows ?? 3"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            auto-resize
            @update:model-value="update"
        />

        <InputNumber
            v-else-if="field.type === 'number' || field.type === 'money'"
            :id="field.key"
            :model-value="modelValue"
            :min="field.meta.min"
            :max="field.meta.max"
            :step="field.meta.step ?? 1"
            :max-fraction-digits="field.type === 'money' ? 2 : 0"
            :prefix="field.meta.prefix ? `${field.meta.prefix} ` : undefined"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            fluid
            @update:model-value="update"
        />

        <MultiSelect
            v-else-if="field.type === 'multiselect'"
            :id="field.key"
            :model-value="Array.isArray(modelValue) ? modelValue : []"
            :options="field.options"
            option-label="label"
            option-value="value"
            :filter="field.options.length > 8"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            :placeholder="field.placeholder ?? 'Select'"
            display="chip"
            fluid
            @update:model-value="update"
        />

        <Select
            v-else-if="field.type === 'select'"
            :id="field.key"
            :model-value="modelValue"
            :options="field.options"
            option-label="label"
            option-value="value"
            :filter="field.meta.searchable !== false && field.options.length > 8"
            :show-clear="!field.required"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            :placeholder="field.placeholder ?? 'Select'"
            fluid
            @update:model-value="update"
        />

        <DatePicker
            v-else-if="field.type === 'date' || field.type === 'datetime'"
            :id="field.key"
            :model-value="modelValue ? new Date(modelValue) : null"
            :show-time="field.type === 'datetime'"
            date-format="yy-mm-dd"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            show-icon
            icon-display="input"
            fluid
            @update:model-value="update(toIsoDate($event, field.type === 'datetime'))"
        />

        <div v-else-if="field.type === 'toggle'" class="flex items-center gap-3 pt-1">
            <ToggleSwitch
                :model-value="Boolean(modelValue)"
                :disabled="field.disabled"
                @update:model-value="update"
            />
            <label :for="field.key" class="text-sm">{{ field.label }}</label>
        </div>

        <div v-else-if="field.type === 'file' || field.type === 'image'" class="flex flex-col gap-2">
            <img
                v-if="field.type === 'image' && typeof modelValue === 'string' && modelValue"
                :src="`/storage/${modelValue}`"
                alt=""
                class="h-24 w-24 rounded border border-surface-200 object-cover dark:border-[#272B35]"
            >
            <p v-else-if="typeof modelValue === 'string' && modelValue" class="font-mono text-[11px] text-surface-500">
                {{ modelValue }}
            </p>

            <FileUpload
                mode="basic"
                :accept="(field.meta.accept ?? []).join(',') || undefined"
                :max-file-size="5242880"
                choose-label="Choose file"
                custom-upload
                auto
                @uploader="update($event.files[0])"
            />
        </div>

        <AutoComplete
            v-else-if="field.type === 'tags'"
            :model-value="Array.isArray(modelValue) ? modelValue : []"
            multiple
            :typeahead="false"
            fluid
            @update:model-value="update"
        />

        <InputText
            v-else
            :id="field.key"
            :model-value="modelValue"
            :disabled="field.disabled"
            :invalid="Boolean(error)"
            :placeholder="field.placeholder ?? undefined"
            fluid
            @update:model-value="update"
        />

        <small v-if="field.help" class="text-surface-500">{{ field.help }}</small>
        <small v-if="error" class="text-[#D95757]">{{ error }}</small>
    </div>
</template>
