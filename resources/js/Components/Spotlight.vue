<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'

const page = usePage()

const visible = ref(false)
const query = ref('')
const highlighted = ref(0)
const input = ref(null)
const listEl = ref(null)
const remote = ref([])
const searching = ref(false)

/**
 * Built from the same navigation array that drives the top bar, so the two can
 * never drift apart.
 */
const navTargets = computed(() =>
    (page.props.navigation ?? []).flatMap((cluster) =>
        cluster.items.map((item) => ({
            id: `nav:${item.route}`,
            label: item.label,
            group: cluster.label,
            icon: item.icon,
            href: route(item.route),
        })),
    ),
)

const staticTargets = computed(() =>
    [
        { label: 'Dashboard', route: 'admin.dashboard', icon: 'pi pi-home' },
        { label: 'Settings', route: 'admin.settings', icon: 'pi pi-cog' },
        { label: 'Profile', route: 'admin.profile', icon: 'pi pi-user' },
    ]
        // Routes arrive over the life of the migration; skip any not yet defined.
        .filter((target) => {
            try {
                route(target.route)

                return true
            } catch {
                return false
            }
        })
        .map((target) => ({
            id: `nav:${target.route}`,
            label: target.label,
            group: 'Go to',
            icon: target.icon,
            href: route(target.route),
        })),
)

function fuzzyScore(haystack, needle) {
    const text = haystack.toLowerCase()
    const term = needle.toLowerCase()

    if (text.startsWith(term)) {
        return 3
    }

    if (text.includes(term)) {
        return 2
    }

    let index = 0

    for (const character of term) {
        index = text.indexOf(character, index)

        if (index === -1) {
            return -1
        }

        index += 1
    }

    return 1
}

const navResults = computed(() => {
    const all = [...navTargets.value, ...staticTargets.value]

    if (!query.value) {
        return all
    }

    return all
        .map((target) => ({ target, score: fuzzyScore(target.label, query.value) }))
        .filter((entry) => entry.score >= 0)
        .sort((a, b) => b.score - a.score)
        .map((entry) => entry.target)
})

const results = computed(() => [...navResults.value, ...remote.value])

watch(results, () => (highlighted.value = 0))

let searchTimer = null
let requestToken = 0

watch(query, (term) => {
    clearTimeout(searchTimer)

    if (term.trim().length < 2) {
        remote.value = []
        searching.value = false

        return
    }

    searching.value = true

    searchTimer = setTimeout(async () => {
        const token = ++requestToken

        try {
            const response = await fetch(`${route('admin.search')}?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })

            const data = await response.json()

            // Ignore a slow reply that a newer keystroke has superseded.
            if (token !== requestToken) {
                return
            }

            remote.value = data.results.map((result, index) => ({
                id: `record:${index}`,
                label: result.label,
                group: result.group,
                icon: 'pi pi-arrow-right',
                href: result.url,
            }))
        } catch {
            remote.value = []
        } finally {
            if (token === requestToken) {
                searching.value = false
            }
        }
    }, 250)
})

function open() {
    visible.value = true
    query.value = ''
    remote.value = []
    highlighted.value = 0

    nextTick(() => input.value?.$el?.focus())
}

function select(target) {
    visible.value = false
    router.visit(target.href)
}

function scrollHighlightedIntoView() {
    nextTick(() => {
        listEl.value?.querySelector('[data-highlighted="true"]')?.scrollIntoView({ block: 'nearest' })
    })
}

function onKeydown(event) {
    const isToggle = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k'

    if (isToggle) {
        event.preventDefault()
        visible.value ? (visible.value = false) : open()

        return
    }

    if (!visible.value) {
        return
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault()
        highlighted.value = Math.min(highlighted.value + 1, results.value.length - 1)
        scrollHighlightedIntoView()
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault()
        highlighted.value = Math.max(highlighted.value - 1, 0)
        scrollHighlightedIntoView()
    }

    if (event.key === 'Enter' && results.value[highlighted.value]) {
        event.preventDefault()
        select(results.value[highlighted.value])
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown)
    clearTimeout(searchTimer)
})

defineExpose({ open })
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :show-header="false"
        dismissable-mask
        :style="{ width: '34rem' }"
        content-class="!p-0"
    >
        <div class="flex items-center gap-2 border-b border-surface-200 px-3 dark:border-[#272B35]">
            <i class="pi pi-search text-surface-500" style="font-size: 0.8rem" />
            <InputText
                ref="input"
                v-model="query"
                placeholder="Jump to, or search records"
                autofocus
                unstyled
                class="w-full bg-transparent py-3 text-sm outline-none"
            />
            <i v-if="searching" class="pi pi-spin pi-spinner text-surface-500" style="font-size: 0.75rem" />
            <kbd class="rounded border border-surface-200 px-1.5 py-0.5 font-mono text-[10px] text-surface-500 dark:border-[#272B35]">esc</kbd>
        </div>

        <ul ref="listEl" class="max-h-80 overflow-y-auto p-2">
            <li v-if="!results.length" class="px-3 py-8 text-center text-sm text-surface-500">
                {{ query ? `Nothing matches “${query}”.` : 'Start typing.' }}
            </li>

            <li
                v-for="(target, index) in results"
                :key="target.id"
                :data-highlighted="index === highlighted"
                class="flex cursor-pointer items-center gap-3 rounded px-3 py-2 text-sm"
                :class="index === highlighted
                    ? 'bg-primary/10 text-primary'
                    : 'hover:bg-surface-100 dark:hover:bg-[#1A1D24]'"
                @mouseenter="highlighted = index"
                @click="select(target)"
            >
                <i :class="target.icon" style="font-size: 0.8rem" />
                <span class="flex-1 truncate">{{ target.label }}</span>
                <span class="font-mono text-[11px] text-surface-500">{{ target.group }}</span>
            </li>
        </ul>
    </Dialog>
</template>
