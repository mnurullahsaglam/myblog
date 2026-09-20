<script setup>
import { computed } from 'vue'
import Button from 'primevue/button'
import Chip from 'primevue/chip'
import DatePicker from 'primevue/datepicker'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'

const props = defineProps({
  filters: { type: Array, required: true },
  modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue'])

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

function toIsoDate(date) {
  if (!date) {
    return null
  }

  // Local date, not UTC: toISOString() would shift the day either side of midnight.
  const offset = date.getTimezoneOffset() * 60000

  return new Date(date.getTime() - offset).toISOString().slice(0, 10)
}

function labelFor(filter, value) {
  return filter.options.find((option) => String(option.value) === String(value))?.label ?? value
}

/** Only filters that actually constrain the query get a chip. */
const activeChips = computed(() =>
  props.filters.flatMap((filter) => {
    if (filter.displayOnly) {
      return []
    }

    const value = props.modelValue[filter.key]

    if (!value || (Array.isArray(value) && !value.length)) {
      return []
    }

    if (filter.type === 'dateRange') {
      const parts = [value.from && `from ${value.from}`, value.to && `until ${value.to}`].filter(Boolean)

      return parts.length ? [{ key: filter.key, label: `${filter.label}: ${parts.join(' ')}` }] : []
    }

    if (Array.isArray(value)) {
      return [{ key: filter.key, label: `${filter.label}: ${value.map((v) => labelFor(filter, v)).join(', ')}` }]
    }

    return [{ key: filter.key, label: `${filter.label}: ${labelFor(filter, value)}` }]
  }),
)

function clearOne(key) {
  const filter = props.filters.find((candidate) => candidate.key === key)

  update(key, filter?.multiple ? [] : null)
}

function clearAll() {
  const cleared = { ...props.modelValue }

  props.filters.forEach((filter) => {
    if (filter.displayOnly) {
      return
    }

    cleared[filter.key] = filter.multiple ? [] : null
  })

  emit('update:modelValue', cleared)
}
</script>

<template>
  <div v-if="filters.length" class="mb-4 flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-2">
      <template v-for="filter in filters" :key="filter.key">
        <MultiSelect
          v-if="filter.type === 'select' && filter.multiple"
          :model-value="modelValue[filter.key]"
          :options="filter.options"
          option-label="label"
          option-value="value"
          :placeholder="filter.label"
          :max-selected-labels="2"
          size="small"
          filter
          class="min-w-48"
          @update:model-value="update(filter.key, $event)"
        />

        <Select
          v-else-if="filter.type === 'select' || filter.type === 'boolean'"
          :model-value="modelValue[filter.key]"
          :options="filter.options"
          option-label="label"
          option-value="value"
          :placeholder="filter.label"
          size="small"
          :show-clear="!filter.displayOnly"
          class="min-w-40"
          @update:model-value="update(filter.key, $event)"
        />

        <div v-else-if="filter.type === 'dateRange'" class="flex items-center gap-1">
          <DatePicker
            :model-value="modelValue[filter.key]?.from ? new Date(modelValue[filter.key].from) : null"
            date-format="yy-mm-dd"
            :placeholder="`${filter.label} from`"
            size="small"
            show-icon
            icon-display="input"
            @update:model-value="
              update(filter.key, {
                ...(modelValue[filter.key] ?? {}),
                from: toIsoDate($event),
              })
            "
          />
          <DatePicker
            :model-value="modelValue[filter.key]?.to ? new Date(modelValue[filter.key].to) : null"
            date-format="yy-mm-dd"
            :placeholder="`${filter.label} until`"
            size="small"
            show-icon
            icon-display="input"
            @update:model-value="
              update(filter.key, {
                ...(modelValue[filter.key] ?? {}),
                to: toIsoDate($event),
              })
            "
          />
        </div>
      </template>
    </div>

    <div v-if="activeChips.length" class="flex flex-wrap items-center gap-2">
      <span class="text-surface-500 font-mono text-[11px] tracking-[0.06em] uppercase" lang="en">Active filters</span>

      <Chip
        v-for="chip in activeChips"
        :key="chip.key"
        :label="chip.label"
        removable
        class="!py-0.5 !text-[11px]"
        @remove="clearOne(chip.key)"
      />

      <Button label="Reset" text size="small" severity="secondary" @click="clearAll" />
    </div>
  </div>
</template>
