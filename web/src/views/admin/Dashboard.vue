<template>
  <AdminLayout>
    <AdminHeader title="Tableau de bord" subtitle="Vue d'ensemble de votre activité" />

    <div class="p-6 space-y-6">
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard
          label="Réservations ce mois"
          :value="stats.reservations.thisMonth"
          :change="calculateChange(stats.reservations.thisMonth, stats.reservations.total / 12)"
        >
          <template #icon>
            <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </template>
        </StatCard>

        <StatCard
          label="Réservations en attente"
          :value="stats.reservations.pending"
          icon-bg-class="bg-yellow-100"
        >
          <template #icon>
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </template>
        </StatCard>

        <StatCard
          label="Revenus ce mois"
          :value="stats.revenue.thisMonth"
          format="currency"
          :change="calculateChange(stats.revenue.thisMonth, stats.revenue.lastMonth)"
          icon-bg-class="bg-green-100"
        >
          <template #icon>
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </template>
        </StatCard>

        <StatCard
          label="Nouveaux clients"
          :value="stats.clients.newThisMonth"
          icon-bg-class="bg-blue-100"
        >
          <template #icon>
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
          </template>
        </StatCard>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Reservations -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Réservations récentes</h2>
            <router-link
              to="/admin/reservations"
              class="text-sm text-gold hover:text-gold/80"
            >
              Voir tout
            </router-link>
          </div>
          <div class="divide-y divide-gray-100">
            <div
              v-for="reservation in recentReservations"
              :key="reservation.id"
              class="px-6 py-4 hover:bg-gray-50 transition-colors"
            >
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-medium text-gray-900">{{ reservation.client.name }}</p>
                  <p class="text-sm text-gray-500">{{ reservation.prestation.title }}</p>
                </div>
                <div class="text-right">
                  <StatusBadge :status="reservation.status" />
                  <p class="text-sm text-gray-500 mt-1">{{ formatDate(reservation.date) }}</p>
                </div>
              </div>
            </div>
            <div v-if="recentReservations.length === 0" class="px-6 py-8 text-center text-gray-500">
              Aucune réservation récente
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Actions rapides</h2>
          <div class="space-y-3">
            <router-link
              to="/admin/reservations"
              class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <div class="p-2 bg-gold/10 rounded-lg">
                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <p class="font-medium text-gray-900">Gérer les réservations</p>
                <p class="text-sm text-gray-500">{{ stats.reservations.pending }} en attente</p>
              </div>
            </router-link>

            <router-link
              to="/admin/galleries"
              class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <div class="p-2 bg-blue-100 rounded-lg">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <p class="font-medium text-gray-900">Gérer les galeries</p>
                <p class="text-sm text-gray-500">{{ stats.galleries.total }} galeries</p>
              </div>
            </router-link>

            <router-link
              to="/admin/factures"
              class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <div class="p-2 bg-green-100 rounded-lg">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                </svg>
              </div>
              <div>
                <p class="font-medium text-gray-900">Créer une facture</p>
                <p class="text-sm text-gray-500">Générer un PDF</p>
              </div>
            </router-link>

            <router-link
              to="/admin/clients"
              class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <div class="p-2 bg-purple-100 rounded-lg">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
              </div>
              <div>
                <p class="font-medium text-gray-900">Voir les clients</p>
                <p class="text-sm text-gray-500">{{ stats.clients.total }} clients</p>
              </div>
            </router-link>
          </div>
        </div>
      </div>

      <!-- Activity Feed -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Activité récente</h2>
        </div>
        <div class="divide-y divide-gray-100">
          <div
            v-for="activity in recentActivity"
            :key="activity.id"
            class="px-6 py-4 flex items-start gap-4"
          >
            <div
              :class="[
                'p-2 rounded-full',
                activityIconClasses[activity.type]?.bg || 'bg-gray-100'
              ]"
            >
              <svg
                :class="['w-4 h-4', activityIconClasses[activity.type]?.text || 'text-gray-600']"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  :d="activityIcons[activity.type] || activityIcons.default"
                />
              </svg>
            </div>
            <div class="flex-1">
              <p class="text-sm text-gray-900">{{ activity.message }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ formatRelativeDate(activity.date) }}</p>
            </div>
          </div>
          <div v-if="recentActivity.length === 0" class="px-6 py-8 text-center text-gray-500">
            Aucune activité récente
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AdminLayout from '@/components/admin/AdminLayout.vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import StatCard from '@/components/admin/ui/StatCard.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import { adminApi } from '@/services/adminApi'
import type { DashboardStats, RecentActivity, Reservation } from '@/types/admin'

const stats = reactive<DashboardStats>({
  reservations: { total: 0, pending: 0, confirmed: 0, thisMonth: 0 },
  clients: { total: 0, newThisMonth: 0 },
  revenue: { total: 0, thisMonth: 0, lastMonth: 0 },
  galleries: { total: 0, public: 0, private: 0 },
})

const recentReservations = ref<Reservation[]>([])
const recentActivity = ref<RecentActivity[]>([])

const activityIcons: Record<string, string> = {
  reservation: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
  payment: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  gallery: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
  contact: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  default: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
}

const activityIconClasses: Record<string, { bg: string; text: string }> = {
  reservation: { bg: 'bg-gold/10', text: 'text-gold' },
  payment: { bg: 'bg-green-100', text: 'text-green-600' },
  gallery: { bg: 'bg-blue-100', text: 'text-blue-600' },
  contact: { bg: 'bg-purple-100', text: 'text-purple-600' },
}

function calculateChange(current: number, previous: number): number {
  if (previous === 0) return current > 0 ? 100 : 0
  return Math.round(((current - previous) / previous) * 100)
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function formatRelativeDate(dateStr: string): string {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return "À l'instant"
  if (diffMins < 60) return `Il y a ${diffMins} minute${diffMins > 1 ? 's' : ''}`
  if (diffHours < 24) return `Il y a ${diffHours} heure${diffHours > 1 ? 's' : ''}`
  if (diffDays < 7) return `Il y a ${diffDays} jour${diffDays > 1 ? 's' : ''}`

  return formatDate(dateStr)
}

async function fetchDashboardData() {
  try {
    const [statsResponse, activityResponse] = await Promise.all([
      adminApi.getDashboardStats(),
      adminApi.getRecentActivity(),
    ])

    if (statsResponse.success) {
      Object.assign(stats, statsResponse.data)
    }

    if (activityResponse.success) {
      recentActivity.value = activityResponse.data
    }

    // Fetch recent reservations
    const reservationsResponse = await adminApi.getReservations(1, 5)
    if (reservationsResponse.data) {
      recentReservations.value = reservationsResponse.data
    }
  } catch {
    // Silently fail - show empty state
  }
}

onMounted(() => {
  fetchDashboardData()
})
</script>
