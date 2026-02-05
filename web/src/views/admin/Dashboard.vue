<template>
    <div>
        <AdminHeader title="Tableau de bord" subtitle="Vue d'ensemble de votre activité" />

        <!-- Loading State -->
        <div v-if="isLoading" class="p-6 flex items-center justify-center min-h-[400px]">
            <div class="flex flex-col items-center gap-3">
                <svg class="animate-spin h-8 w-8 text-gold" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-gray-500">Chargement...</span>
            </div>
        </div>

        <div v-else class="p-6 space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <StatCard
                    label="Réservations ce mois"
                    :value="stats.reservations.thisMonth"
                    :change="calculateChange(stats.reservations.thisMonth, stats.reservations.lastMonth)"
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
                    label="Total clients"
                    :value="stats.clients.total"
                    icon-bg-class="bg-blue-100"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </template>
                </StatCard>
            </div>

            <!-- Second Row Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <StatCard
                    label="Galeries clients"
                    :value="stats.galleries.clients"
                    icon-bg-class="bg-purple-100"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </template>
                </StatCard>

                <StatCard
                    label="Galeries événements"
                    :value="stats.galleries.events"
                    icon-bg-class="bg-pink-100"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </template>
                </StatCard>

                <StatCard
                    label="Commandes payées"
                    :value="stats.orders.paid"
                    icon-bg-class="bg-emerald-100"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </template>
                </StatCard>

                <StatCard
                    label="Bons cadeaux actifs"
                    :value="stats.giftCards.active"
                    icon-bg-class="bg-amber-100"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
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
                                    <p class="font-medium text-gray-900">{{ reservation.client_name || 'N/A' }}</p>
                                    <p class="text-sm text-gray-500">{{ reservation.prestation?.title || 'N/A' }}</p>
                                </div>
                                <div class="text-right">
                                    <StatusBadge :status="reservation.status" />
                                    <p class="text-sm text-gray-500 mt-1">{{ reservation.date ? formatDate(reservation.date) : 'A définir' }}</p>
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
                                <p class="text-sm text-gray-500">{{ stats.galleries.clients + stats.galleries.events }} galeries</p>
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

                        <router-link
                            to="/admin/orders"
                            class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <div class="p-2 bg-emerald-100 rounded-lg">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">Voir les commandes</p>
                                <p class="text-sm text-gray-500">{{ stats.orders.total }} commandes</p>
                            </div>
                        </router-link>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Orders -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Commandes récentes</h2>
                        <router-link
                            to="/admin/orders"
                            class="text-sm text-gold hover:text-gold/80"
                        >
                            Voir tout
                        </router-link>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="order in recentOrders"
                            :key="order.id"
                            class="px-6 py-4 hover:bg-gray-50 transition-colors"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-gray-900">{{ order.customer_name || order.customer_email }}</p>
                                    <p class="text-sm text-gray-500">{{ order.order_number }} - {{ order.items?.length || 0 }} article(s)</p>
                                </div>
                                <div class="text-right">
                                    <span :class="getOrderStatusClass(order.status)">
                                        {{ getOrderStatusLabel(order.status) }}
                                    </span>
                                    <p class="text-sm font-medium text-gray-900 mt-1">{{ formatCurrency(order.total) }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-if="recentOrders.length === 0" class="px-6 py-8 text-center text-gray-500">
                            Aucune commande récente
                        </div>
                    </div>
                </div>

                <!-- Recent Galleries -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Galeries récentes</h2>
                        <router-link
                            to="/admin/galleries"
                            class="text-sm text-gold hover:text-gold/80"
                        >
                            Voir tout
                        </router-link>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="gallery in recentGalleries"
                            :key="gallery.id"
                            class="px-6 py-4 hover:bg-gray-50 transition-colors"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-gray-900">{{ gallery.title }}</p>
                                    <p class="text-sm text-gray-500">{{ gallery.photos_count }} photos</p>
                                </div>
                                <div class="text-right">
                                    <span v-if="gallery.views_count > 0" class="text-sm text-gray-500">
                                        {{ gallery.views_count }} vues
                                    </span>
                                    <span v-else class="text-sm text-gray-400">Pas encore vue</span>
                                    <p class="text-xs text-gray-400 mt-1">{{ formatRelativeDate(gallery.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-if="recentGalleries.length === 0" class="px-6 py-8 text-center text-gray-500">
                            Aucune galerie récente
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import {ref, reactive, onMounted} from 'vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import StatCard from '@/components/admin/ui/StatCard.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import {adminApi} from '@/services/adminApi'
import type {Reservation, AdminGallery, AdminOrder} from '@/types/admin'

const isLoading = ref(true)

// Stats calculées à partir des vraies données
const stats = reactive({
    reservations: {
        total: 0,
        pending: 0,
        confirmed: 0,
        thisMonth: 0,
        lastMonth: 0,
    },
    clients: {
        total: 0,
    },
    revenue: {
        total: 0,
        thisMonth: 0,
        lastMonth: 0,
    },
    galleries: {
        clients: 0,
        events: 0,
    },
    orders: {
        total: 0,
        paid: 0,
        pending: 0,
    },
    giftCards: {
        active: 0,
        total: 0,
    },
})

const recentReservations = ref<Reservation[]>([])
const recentOrders = ref<AdminOrder[]>([])
const recentGalleries = ref<AdminGallery[]>([])

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
    if (diffMins < 60) return `Il y a ${diffMins}min`
    if (diffHours < 24) return `Il y a ${diffHours}h`
    if (diffDays < 7) return `Il y a ${diffDays}j`

    return formatDate(dateStr)
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
    }).format(amount)
}

function getOrderStatusClass(status: string): string {
    const classes: Record<string, string> = {
        pending: 'inline-flex px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800',
        paid: 'inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800',
        failed: 'inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800',
        refunded: 'inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800',
        expired: 'inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600',
    }
    return classes[status] || classes.pending
}

function getOrderStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        pending: 'En attente',
        paid: 'Payée',
        failed: 'Échouée',
        refunded: 'Remboursée',
        expired: 'Expirée',
    }
    return labels[status] || status
}

function isThisMonth(dateStr: string): boolean {
    const date = new Date(dateStr)
    const now = new Date()
    return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear()
}

function isLastMonth(dateStr: string): boolean {
    const date = new Date(dateStr)
    const now = new Date()
    const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    return date.getMonth() === lastMonth.getMonth() && date.getFullYear() === lastMonth.getFullYear()
}

async function fetchDashboardData() {
    isLoading.value = true

    try {
        // Fetch all data in parallel for better performance
        const [
            reservationsResponse,
            clientsResponse,
            galleriesResponse,
            eventsResponse,
            ordersResponse,
            giftCardsResponse,
        ] = await Promise.all([
            adminApi.getReservations(1, 100).catch(() => ({ data: [], meta: { total: 0 } })),
            adminApi.getClients(1, 100).catch(() => ({ data: [], meta: { total: 0 } })),
            adminApi.getGalleries(1, 100).catch(() => ({ data: [], meta: { total: 0 } })),
            adminApi.getEventGalleries(1, 100).catch(() => ({ data: [], meta: { total: 0 } })),
            adminApi.getOrders(1, 100).catch(() => ({ orders: [], pagination: { total: 0 } })),
            adminApi.getGiftCards(1, 100).catch(() => ({ data: [], meta: { total: 0 } })),
        ])

        // Process reservations
        const reservations = reservationsResponse.data || []
        stats.reservations.total = reservationsResponse.meta?.total || reservations.length
        stats.reservations.pending = reservations.filter(r => r.status === 'pending').length
        stats.reservations.confirmed = reservations.filter(r => r.status === 'confirmed').length
        stats.reservations.thisMonth = reservations.filter(r => isThisMonth(r.created_at)).length
        stats.reservations.lastMonth = reservations.filter(r => isLastMonth(r.created_at)).length
        recentReservations.value = reservations.slice(0, 5)

        // Process clients
        const clients = clientsResponse.data || []
        stats.clients.total = clientsResponse.meta?.total || clients.length

        // Process galleries
        const galleries = galleriesResponse.data || []
        stats.galleries.clients = galleriesResponse.meta?.total || galleries.length
        recentGalleries.value = galleries.slice(0, 5)

        // Process event galleries
        const events = eventsResponse.data || []
        stats.galleries.events = eventsResponse.meta?.total || events.length

        // Process orders
        const orders = ordersResponse.orders || []
        stats.orders.total = ordersResponse.pagination?.total || orders.length
        stats.orders.paid = orders.filter((o: AdminOrder) => o.status === 'paid').length
        stats.orders.pending = orders.filter((o: AdminOrder) => o.status === 'pending').length
        recentOrders.value = orders.slice(0, 5)

        // Calculate revenue from paid orders
        const paidOrders = orders.filter((o: AdminOrder) => o.status === 'paid')
        stats.revenue.total = paidOrders.reduce((sum: number, o: AdminOrder) => sum + (o.total || 0), 0)
        stats.revenue.thisMonth = paidOrders
            .filter((o: AdminOrder) => o.paid_at && isThisMonth(o.paid_at))
            .reduce((sum: number, o: AdminOrder) => sum + (o.total || 0), 0)
        stats.revenue.lastMonth = paidOrders
            .filter((o: AdminOrder) => o.paid_at && isLastMonth(o.paid_at))
            .reduce((sum: number, o: AdminOrder) => sum + (o.total || 0), 0)

        // Process gift cards
        const giftCards = giftCardsResponse.data || []
        stats.giftCards.total = giftCardsResponse.meta?.total || giftCards.length
        stats.giftCards.active = giftCards.filter((g: { status: string }) => g.status === 'active').length

    } catch (error) {
        console.error('Error fetching dashboard data:', error)
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    fetchDashboardData()
})
</script>
