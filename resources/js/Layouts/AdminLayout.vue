<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Menu from 'primevue/menu'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import Mark from '@/Components/Brand/Mark.vue'
import Spotlight from '@/Components/Spotlight.vue'

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
})

const page = usePage()
const toast = useToast()

const navigation = computed(() => page.props.navigation ?? [])
const user = computed(() => page.props.auth?.user ?? null)
const env = computed(() => page.props.env ?? { name: 'production', isProduction: true })

const clusterMenus = ref({})
const userMenu = ref()
const spotlight = ref()

function setClusterMenu(label, el) {
    if (el) {
        clusterMenus.value[label] = el
    }
}

function clusterItems(cluster) {
    return cluster.items.map((item) => ({
        label: item.label,
        icon: item.icon,
        command: () => router.visit(route(item.route)),
    }))
}

/** A cluster is active when the current URL matches one of its routes. */
function isActiveCluster(cluster) {
    return cluster.items.some((item) => {
        try {
            return page.url.startsWith(new URL(route(item.route)).pathname)
        } catch {
            return false
        }
    })
}

const userMenuItems = computed(() => [
    { label: 'Profile', icon: 'pi pi-user', command: () => router.visit(route('admin.profile')) },
    { separator: true },
    { label: 'Sign out', icon: 'pi pi-sign-out', command: () => router.post(route('logout')) },
])

watch(
    () => page.props.flash?.notification,
    (notification) => {
        if (!notification) {
            return
        }

        toast.add({
            severity: notification.variant === 'danger' ? 'error' : notification.variant,
            summary: notification.title,
            detail: notification.body ?? undefined,
            life: 5000,
        })
    },
    { immediate: true },
)
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen flex-col bg-surface-50 text-surface-900 dark:bg-[#0D0E11] dark:text-surface-100">
        <header class="border-b border-surface-200 bg-surface-0 dark:border-[#272B35] dark:bg-[#15171C]">
            <div class="mx-auto flex h-14 max-w-[1280px] items-center gap-6 px-4">
                <Link
                    :href="route('admin.dashboard')"
                    class="flex items-center gap-2.5 outline-none focus-visible:text-primary"
                >
                    <Mark />
                    <span class="font-mono text-sm font-semibold tracking-tight">OP//SHELL</span>
                </Link>

                <nav class="flex items-center gap-0.5">
                    <template v-for="cluster in navigation" :key="cluster.label">
                        <template v-if="cluster.items.length">
                            <Button
                                :label="cluster.label"
                                text
                                size="small"
                                :severity="isActiveCluster(cluster) ? 'primary' : 'secondary'"
                                :class="isActiveCluster(cluster) ? 'bg-primary/10' : ''"
                                @click="clusterMenus[cluster.label]?.toggle($event)"
                            />
                            <Menu
                                :ref="(el) => setClusterMenu(cluster.label, el)"
                                :model="clusterItems(cluster)"
                                popup
                            />
                        </template>
                    </template>
                </nav>

                <div class="ml-auto flex items-center gap-1">
                    <slot name="topbar" />

                    <button
                        type="button"
                        class="flex items-center gap-2 rounded border border-surface-200 px-2.5 py-1.5 text-surface-500 transition-colors hover:border-surface-300 hover:text-surface-700 dark:border-[#272B35] dark:hover:border-[#9096A2] dark:hover:text-surface-200"
                        @click="spotlight.open()"
                    >
                        <i class="pi pi-search" style="font-size: 0.75rem" />
                        <span class="hidden text-[13px] sm:inline">Search</span>
                        <kbd class="hidden font-mono text-[10px] sm:inline">⌘K</kbd>
                    </button>

                    <Button
                        v-if="user"
                        :label="user.name"
                        icon="pi pi-user"
                        text
                        size="small"
                        severity="secondary"
                        @click="userMenu.toggle($event)"
                    />
                    <Menu ref="userMenu" :model="userMenuItems" popup />
                </div>
            </div>
        </header>

        <div
            v-if="!env.isProduction"
            class="border-b border-surface-200 bg-primary/10 py-1 text-center font-mono text-[11px] uppercase tracking-[0.06em] text-primary dark:border-[#272B35]"
        >
            {{ env.name }}
        </div>

        <main class="mx-auto w-full max-w-[1280px] flex-1 px-4 py-8">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-[-0.015em]">{{ title }}</h1>
                    <p v-if="subtitle" class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        {{ subtitle }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <slot name="actions" />
                </div>
            </div>

            <slot name="subheader" />

            <slot />
        </main>

        <footer class="border-t border-surface-200 py-5 dark:border-[#272B35]">
            <div
                class="mx-auto flex max-w-[1280px] items-center justify-between px-4 font-mono text-[11px] text-surface-500"
            >
                <span>OP//SYSTEM · {{ env.name }}</span>
                <a
                    href="https://github.com/mnurullahsaglam"
                    rel="noopener"
                    class="hover:text-primary"
                >GitHub</a>
            </div>
        </footer>

        <Spotlight ref="spotlight" />
        <Toast position="bottom-right" />
        <ConfirmDialog />
    </div>
</template>
