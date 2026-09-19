<script setup>
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Button from 'primevue/button'
import OverlayBadge from 'primevue/overlaybadge'
import Popover from 'primevue/popover'

const page = usePage()
const panel = ref()

const notifications = computed(() => page.props.notifications ?? { unreadCount: 0, items: [] })

const VARIANT_COLOR = {
  success: 'bg-[#529E72]',
  danger: 'bg-[#D95757]',
  warning: 'bg-primary',
  info: 'bg-[#9096A2]',
}

function dotClass(variant) {
  return VARIANT_COLOR[variant] ?? VARIANT_COLOR.info
}

function markRead(notification) {
  if (notification.readAt) {
    return
  }

  router.patch(route('admin.notifications.read', notification.id), {}, { preserveScroll: true })
}

function markAllRead() {
  router.patch(route('admin.notifications.read-all'), {}, { preserveScroll: true })
}

function remove(notification) {
  router.delete(route('admin.notifications.destroy', notification.id), { preserveScroll: true })
}
</script>

<template>
  <div>
    <OverlayBadge
      v-if="notifications.unreadCount > 0"
      :value="notifications.unreadCount"
      severity="danger"
      size="small"
    >
      <Button
        icon="pi pi-bell"
        text
        size="small"
        severity="secondary"
        aria-label="Notifications"
        @click="panel.toggle($event)"
      />
    </OverlayBadge>

    <Button
      v-else
      icon="pi pi-bell"
      text
      size="small"
      severity="secondary"
      aria-label="Notifications"
      @click="panel.toggle($event)"
    />

    <Popover ref="panel" :pt="{ content: { class: '!p-0' } }">
      <div class="w-80">
        <header class="border-surface-200 flex items-center justify-between border-b px-3 py-2 dark:border-[#272B35]">
          <span class="text-surface-500 font-mono text-[11px] font-semibold tracking-[0.06em] uppercase">
            Notifications
          </span>
          <Button
            v-if="notifications.unreadCount > 0"
            label="Mark all read"
            text
            size="small"
            severity="secondary"
            @click="markAllRead"
          />
        </header>

        <ul class="max-h-80 overflow-y-auto">
          <li v-if="!notifications.items.length" class="text-surface-500 px-3 py-8 text-center text-sm">
            Nothing yet.
          </li>

          <li
            v-for="notification in notifications.items"
            :key="notification.id"
            class="group border-surface-100 hover:bg-surface-100 flex cursor-pointer items-start gap-2.5 border-b px-3 py-2.5 last:border-b-0 dark:border-[#20232B] dark:hover:bg-[#1A1D24]"
            :class="notification.readAt ? 'opacity-60' : ''"
            @click="markRead(notification)"
          >
            <span
              class="mt-1.5 inline-block h-1.5 w-1.5 shrink-0 rounded-full"
              :class="dotClass(notification.variant)"
            />

            <div class="min-w-0 flex-1">
              <p class="text-[13px] leading-snug font-medium">{{ notification.title }}</p>
              <p v-if="notification.body" class="text-surface-500 mt-0.5 text-[12px]">
                {{ notification.body }}
              </p>
              <p class="text-surface-500 mt-1 font-mono text-[11px]">{{ notification.createdAt }}</p>
            </div>

            <Button
              icon="pi pi-times"
              text
              rounded
              size="small"
              severity="secondary"
              aria-label="Dismiss"
              class="shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
              @click.stop="remove(notification)"
            />
          </li>
        </ul>
      </div>
    </Popover>
  </div>
</template>
