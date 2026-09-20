<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Menu from 'primevue/menu'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import Mark from '@/Components/Brand/Mark.vue'
import NotificationsPanel from '@/Components/NotificationsPanel.vue'
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

function isActiveCluster(cluster) {
  return cluster.items.some((item) => {
    try {
      return page.url.startsWith(new URL(route(item.route)).pathname)
    } catch {
      return false
    }
  })
}

const preview = computed(() => page.props.preview ?? { active: false, role: null, available: false })

function startPreview() {
  router.post(route('admin.preview.store'), { role: 'member' }, { preserveScroll: true })
}

function stopPreview() {
  router.delete(route('admin.preview.destroy'), { preserveScroll: true })
}

const userMenuItems = computed(() => {
  const items = [{ label: 'Profile', icon: 'pi pi-user', command: () => router.visit(route('admin.profile')) }]

  if (preview.value.available) {
    items.push({ label: 'View as member', icon: 'pi pi-eye', command: startPreview })
  }

  items.push({ separator: true })
  items.push({ label: 'Sign out', icon: 'pi pi-sign-out', command: () => router.post(route('logout')) })

  return items
})

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

  <div class="bg-surface-50 text-surface-900 dark:text-surface-100 flex min-h-screen flex-col dark:bg-[#0D0E11]">
    <div
      v-if="preview.active"
      class="flex items-center justify-center gap-3 bg-amber-400 px-4 py-2 text-center text-sm font-medium text-black"
    >
      <span>Viewing as {{ preview.role }}. Nothing can be changed from here.</span>
      <button type="button" class="underline underline-offset-2" @click="stopPreview">Leave preview</button>
    </div>

    <header class="border-surface-200 bg-surface-0 border-b dark:border-[#272B35] dark:bg-[#15171C]">
      <div class="mx-auto flex h-14 max-w-[1280px] items-center gap-6 px-4">
        <Link
          :href="route('admin.dashboard')"
          class="focus-visible:text-primary flex items-center gap-2.5 outline-none"
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
              <Menu :ref="(el) => setClusterMenu(cluster.label, el)" :model="clusterItems(cluster)" popup />
            </template>
          </template>
        </nav>

        <div class="ml-auto flex items-center gap-1">
          <slot name="topbar" />

          <button
            type="button"
            class="border-surface-200 text-surface-500 hover:border-surface-300 hover:text-surface-700 dark:hover:text-surface-200 flex items-center gap-2 rounded border px-2.5 py-1.5 transition-colors dark:border-[#272B35] dark:hover:border-[#9096A2]"
            @click="spotlight.open()"
          >
            <i class="pi pi-search" style="font-size: 0.75rem" />
            <span class="hidden text-[13px] sm:inline">Search</span>
            <kbd class="hidden font-mono text-[10px] sm:inline">⌘K</kbd>
          </button>

          <NotificationsPanel v-if="user" />

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
      class="border-surface-200 bg-primary/10 text-primary border-b py-1 text-center font-mono text-[11px] tracking-[0.06em] uppercase dark:border-[#272B35]"
      lang="en"
    >
      {{ env.name }}
    </div>

    <main class="mx-auto w-full max-w-[1280px] flex-1 px-4 py-8">
      <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold tracking-[-0.015em]">{{ title }}</h1>
          <p v-if="subtitle" class="text-surface-500 dark:text-surface-400 mt-1 text-sm">
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

    <footer class="border-surface-200 border-t py-5 dark:border-[#272B35]">
      <div class="text-surface-500 mx-auto flex max-w-[1280px] items-center justify-between px-4 font-mono text-[11px]">
        <span>OP//SYSTEM · {{ env.name }}</span>
        <a href="https://github.com/mnurullahsaglam" rel="noopener" class="hover:text-primary">GitHub</a>
      </div>
    </footer>

    <Spotlight ref="spotlight" />
    <Toast position="bottom-right" />
    <ConfirmDialog />
  </div>
</template>
