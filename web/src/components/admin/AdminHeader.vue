<template>
  <header class="sticky top-0 z-40 flex items-center justify-between h-16 px-6 bg-white border-b border-gray-200">
    <!-- Title -->
    <div>
      <h1 class="text-xl font-semibold text-gray-900">{{ title }}</h1>
      <p v-if="subtitle" class="text-sm text-gray-500">{{ subtitle }}</p>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-4">
      <!-- Notifications -->
      <div class="relative">
        <button
          @click="showNotifications = !showNotifications"
          class="relative p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
            />
          </svg>
          <span
            v-if="unreadCount > 0"
            class="absolute -top-1 -right-1 flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full"
          >
            {{ unreadCount > 9 ? '9+' : unreadCount }}
          </span>
        </button>

        <!-- Notifications dropdown -->
        <div
          v-if="showNotifications"
          class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
        >
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Notifications</h3>
            <button
              v-if="notifications.length > 0"
              @click="markAllRead"
              class="text-sm text-gold hover:text-gold/80"
            >
              Tout marquer lu
            </button>
          </div>
          <div class="max-h-80 overflow-y-auto">
            <div
              v-for="notification in notifications"
              :key="notification.id"
              :class="[
                'px-4 py-3 border-b border-gray-100 cursor-pointer hover:bg-gray-50 transition-colors',
                !notification.is_read && 'bg-gold/5'
              ]"
              @click="openNotification(notification)"
            >
              <p class="text-sm font-medium text-gray-900">{{ notification.title }}</p>
              <p class="text-sm text-gray-500 line-clamp-2">{{ notification.message }}</p>
              <p class="text-xs text-gray-400 mt-1">{{ formatDate(notification.created_at) }}</p>
            </div>
            <div v-if="notifications.length === 0" class="px-4 py-8 text-center text-gray-500">
              Aucune notification
            </div>
          </div>
        </div>
      </div>

      <!-- Primary action slot -->
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import type { Notification } from '@/types/admin'
import { adminApi } from '@/services/adminApi'

defineProps<{
  title: string
  subtitle?: string
}>()

const showNotifications = ref(false)
const notifications = ref<Notification[]>([])

const unreadCount = computed(() => notifications.value.filter(n => !n.is_read).length)

async function fetchNotifications() {
  try {
    const response = await adminApi.getNotifications()
    if (response.success) {
      notifications.value = response.data
    }
  } catch {
    // Silently fail
  }
}

async function markAllRead() {
  try {
    await adminApi.markAllNotificationsRead()
    notifications.value = notifications.value.map(n => ({ ...n, is_read: true }))
  } catch {
    // Silently fail
  }
}

async function openNotification(notification: Notification) {
  if (!notification.is_read) {
    try {
      await adminApi.markNotificationRead(notification.id)
      notification.is_read = true
    } catch {
      // Silently fail
    }
  }
  showNotifications.value = false
}

function formatDate(dateStr: string): string {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return "À l'instant"
  if (diffMins < 60) return `Il y a ${diffMins} min`
  if (diffHours < 24) return `Il y a ${diffHours}h`
  if (diffDays < 7) return `Il y a ${diffDays}j`

  return date.toLocaleDateString('fr-FR')
}

function handleClickOutside(event: MouseEvent) {
  const target = event.target as HTMLElement
  if (!target.closest('.relative')) {
    showNotifications.value = false
  }
}

onMounted(() => {
  fetchNotifications()
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>
