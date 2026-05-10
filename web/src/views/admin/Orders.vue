<template>
    <div>
        <AdminHeader title="Commandes" subtitle="Consultez les commandes de photos" />

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

        <div v-else class="p-6">
            <!-- Stats Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <StatCard
                    label="Total commandes"
                    :value="stats.total"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </template>
                </StatCard>
                <StatCard
                    label="Payées"
                    :value="stats.paid"
                    icon-bg-class="bg-green-100"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </StatCard>
                <StatCard
                    label="À expédier"
                    :value="stats.toShip"
                    icon-bg-class="bg-amber-100"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                    </template>
                </StatCard>
                <StatCard
                    label="Revenus"
                    :value="stats.revenue"
                    format="currency"
                    icon-bg-class="bg-blue-100"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </StatCard>
            </div>

            <!-- Orders Table -->
            <DataTable
                :columns="columns"
                :data="orders"
                searchable
                v-model:search-query="searchQuery"
                v-model:current-page="currentPage"
                :total-pages="totalPages"
                :total="total"
                :from="from"
                :to="to"
            >
                <template #filters>
                    <select
                        v-model="statusFilter"
                        @change="fetchOrders"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold"
                    >
                        <option value="">Tous les statuts</option>
                        <option value="paid">Payées</option>
                        <option value="pending">En attente</option>
                        <option value="failed">Échouées</option>
                    </select>
                </template>

                <template #cell-order_number="{ row }">
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-medium text-gray-900 bg-gray-100 px-2 py-1 rounded">
                            {{ row.order_number }}
                        </span>
                        <span
                            v-if="row.has_prints"
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800"
                            title="Contient des tirages papier"
                        >
                            Tirage
                        </span>
                    </div>
                </template>

                <template #cell-customer_email="{ row }">
                    <div>
                        <p class="text-gray-900">{{ row.customer_email }}</p>
                        <p v-if="row.customer_name" class="text-sm text-gray-500">
                            {{ row.customer_name }}
                        </p>
                    </div>
                </template>

                <template #cell-items="{ row }">
                    <div class="text-sm">
                        <p class="text-gray-900">{{ row.items.length }} article(s)</p>
                        <p class="text-gray-500">
                            {{ countDigital(row) }} num. / {{ countPrints(row) }} tirage(s)
                        </p>
                    </div>
                </template>

                <template #cell-total="{ row }">
                    <span class="font-semibold text-gray-900">{{ formatCurrency(row.total) }}</span>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :status="row.detailed_status" />
                </template>

                <template #cell-created_at="{ value }">
                    <span class="text-gray-600">{{ formatDate(value as string) }}</span>
                </template>

                <template #actions="{ row }">
                    <div class="flex items-center gap-1">
                        <button
                            @click="openDetailModal(row)"
                            class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                            title="Voir détails"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                        <button
                            @click="confirmDelete(row)"
                            class="p-2 text-gray-500 hover:text-red-600 rounded-lg hover:bg-red-50"
                            title="Supprimer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Detail Modal -->
        <Modal v-model="showDetailModal" :title="`Commande ${selectedOrder?.order_number}`" size="lg">
            <div v-if="selectedOrder" class="space-y-6">
                <!-- Status & Total -->
                <div class="flex items-center justify-between bg-gray-50 rounded-xl p-4">
                    <div>
                        <StatusBadge :status="selectedOrder.detailed_status" />
                        <p v-if="selectedOrder.paid_at" class="text-sm text-gray-500 mt-1">
                            Payée le {{ formatDate(selectedOrder.paid_at) }}
                        </p>
                        <p v-if="selectedOrder.shipped_at" class="text-sm text-gray-500 mt-1">
                            Expédiée le {{ formatDate(selectedOrder.shipped_at) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-gold">{{ formatCurrency(selectedOrder.total) }}</p>
                        <p v-if="selectedOrder.shipping_fee > 0" class="text-xs text-gray-500 mt-1">
                            dont {{ formatCurrency(selectedOrder.shipping_fee) }} de frais de port
                        </p>
                    </div>
                </div>

                <!-- Print Notice / Shipping Section -->
                <div v-if="selectedOrder.has_prints && selectedOrder.status === 'paid'" class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-amber-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            <div>
                                <p class="font-medium text-amber-800">
                                    {{ selectedOrder.print_status === 'shipped' ? 'Tirages expédiés' : 'Tirages à expédier' }}
                                </p>
                                <p class="text-sm text-amber-700 mt-1">
                                    {{ countPrints(selectedOrder) }} tirage(s) papier dans cette commande.
                                </p>
                            </div>
                        </div>
                        <Button
                            v-if="selectedOrder.print_status !== 'shipped'"
                            variant="primary"
                            size="sm"
                            :loading="isMarkingShipped"
                            @click="markAsShipped"
                        >
                            Marquer expédié
                        </Button>
                        <span
                            v-else
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Expédié
                        </span>
                    </div>
                </div>

                <!-- Print Notice for unpaid orders -->
                <div v-else-if="selectedOrder.has_prints" class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-gray-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <div>
                            <p class="font-medium text-gray-700">Commande avec tirages papier</p>
                            <p class="text-sm text-gray-500 mt-1">
                                {{ countPrints(selectedOrder) }} tirage(s) - En attente de paiement.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="space-y-3">
                    <h4 class="font-medium text-gray-900">Client</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500">Email</p>
                            <p class="text-gray-900">{{ selectedOrder.customer_email }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Nom</p>
                            <p class="text-gray-900">{{ selectedOrder.customer_name || '-' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Shipping Address -->
                <div v-if="selectedOrder.shipping" class="space-y-3 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <h4 class="font-medium text-amber-900">Adresse de livraison</h4>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-amber-800/70">Adresse</p>
                            <address class="not-italic text-gray-900 leading-relaxed">
                                <div>{{ selectedOrder.shipping.address_line1 }}</div>
                                <div v-if="selectedOrder.shipping.address_line2">{{ selectedOrder.shipping.address_line2 }}</div>
                                <div>{{ selectedOrder.shipping.postal_code }} {{ selectedOrder.shipping.city }}</div>
                                <div class="text-gray-500">France</div>
                            </address>
                        </div>
                        <div>
                            <p class="text-amber-800/70">Téléphone</p>
                            <p class="text-gray-900">
                                <a
                                    v-if="selectedOrder.shipping.phone"
                                    :href="`tel:${selectedOrder.shipping.phone}`"
                                    class="hover:underline"
                                >
                                    {{ selectedOrder.shipping.phone }}
                                </a>
                                <span v-else>-</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Items -->
                <div class="space-y-3">
                    <h4 class="font-medium text-gray-900">Articles</h4>
                    <div class="border border-gray-200 rounded-lg divide-y divide-gray-200">
                        <div
                            v-for="item in selectedOrder.items"
                            :key="item.id"
                            class="flex items-center gap-4 p-3"
                        >
                            <div class="w-16 h-16 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                                <img
                                    v-if="item.thumbnail_url || item.display_url"
                                    :src="item.thumbnail_url || item.display_url"
                                    :alt="item.photo_title || 'Photo'"
                                    class="w-full h-full object-cover"
                                />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">
                                    {{ item.photo_title || 'Photo' }}
                                </p>
                                <p class="text-sm text-gray-500">{{ item.gallery_title }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                            item.is_print
                                                ? 'bg-amber-100 text-amber-800'
                                                : 'bg-blue-100 text-blue-800'
                                        ]"
                                    >
                                        {{ item.product_type_label }}
                                    </span>
                                    <span
                                        v-if="item.is_downloaded && !item.is_print"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800"
                                    >
                                        Téléchargé
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-gray-900">{{ formatCurrency(item.price) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-between w-full">
                    <Button
                        variant="danger"
                        @click="deleteFromModal"
                    >
                        Supprimer
                    </Button>
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="selectedOrder?.status === 'paid'"
                            variant="secondary"
                            size="sm"
                            @click="copyDownloadLink"
                            title="Copier le lien de téléchargement client"
                        >
                            Copier lien
                        </Button>
                        <Button
                            v-if="selectedOrder?.status === 'pending' && selectedOrder?.sumup_checkout_id"
                            variant="primary"
                            size="sm"
                            :loading="isRetryingPayment"
                            @click="retryPayment"
                        >
                            Re-verifier paiement
                        </Button>
                        <Button variant="secondary" @click="showDetailModal = false">Fermer</Button>
                    </div>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script setup lang="ts">
import {ref, computed, watch, onMounted} from 'vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import DataTable from '@/components/admin/ui/DataTable.vue'
import StatCard from '@/components/admin/ui/StatCard.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import {adminApi} from '@/services/adminApi'
import {useToast} from '@/composables/useToast'
import {useConfirm} from '@/composables/useConfirm'
import type {AdminOrder, TableColumn} from '@/types/admin'

const toast = useToast()
const { confirm } = useConfirm()
const orders = ref<AdminOrder[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const total = ref(0)
const from = ref(0)
const to = ref(0)

const showDetailModal = ref(false)
const selectedOrder = ref<AdminOrder | null>(null)
const isMarkingShipped = ref(false)
const isRetryingPayment = ref(false)

const stats = computed(() => {
    const paid = orders.value.filter(o => o.status === 'paid')
    return {
        total: total.value,
        paid: paid.length,
        toShip: orders.value.filter(o => o.detailed_status === 'to_ship').length,
        revenue: paid.reduce((sum, o) => sum + o.total, 0),
    }
})

const columns: TableColumn<AdminOrder>[] = [
    {key: 'order_number', label: 'Commande'},
    {key: 'customer_email', label: 'Client'},
    {key: 'items', label: 'Articles'},
    {key: 'total', label: 'Total', align: 'right'},
    {key: 'status', label: 'Statut'},
    {key: 'created_at', label: 'Date', sortable: true},
]

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
    }).format(amount)
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

function countDigital(order: AdminOrder): number {
    return order.items.filter(i => !i.is_print).length
}

function countPrints(order: AdminOrder): number {
    return order.items.filter(i => i.is_print).length
}

function openDetailModal(order: AdminOrder) {
    selectedOrder.value = order
    showDetailModal.value = true
}

async function markAsShipped() {
    if (!selectedOrder.value) return

    isMarkingShipped.value = true
    try {
        const response = await adminApi.markOrderShipped(selectedOrder.value.id)
        // Update the order in the list and in the modal
        const index = orders.value.findIndex(o => o.id === selectedOrder.value?.id)
        if (index !== -1) {
            orders.value[index] = response.order
        }
        selectedOrder.value = response.order
        toast.success('Commande marquée comme expédiée')
    } catch {
        toast.error('Erreur', 'Impossible de marquer comme expédié')
    } finally {
        isMarkingShipped.value = false
    }
}

async function copyDownloadLink() {
    if (!selectedOrder.value) return
    try {
        const response = await adminApi.getOrderDownloadLink(selectedOrder.value.id)
        await navigator.clipboard.writeText(response.download_link)
        toast.success('Lien copié dans le presse-papier')
    } catch {
        toast.error('Erreur', 'Impossible de recuperer le lien')
    }
}

async function retryPayment() {
    if (!selectedOrder.value) return
    isRetryingPayment.value = true
    try {
        const response = await adminApi.retryOrderPayment(selectedOrder.value.id)
        const index = orders.value.findIndex(o => o.id === selectedOrder.value?.id)
        if (index !== -1) {
            orders.value[index] = response.order
        }
        selectedOrder.value = response.order
        toast.success(response.message)
    } catch {
        toast.error('Erreur', 'Echec de la re-verification du paiement')
    } finally {
        isRetryingPayment.value = false
    }
}

async function deleteFromModal() {
    if (!selectedOrder.value) return
    await confirmDelete(selectedOrder.value)
    showDetailModal.value = false
}

async function confirmDelete(order: AdminOrder) {
    const isPaid = order.status === 'paid'

    const firstMessage = isPaid
        ? `ATTENTION : La commande ${order.order_number} est PAYEE (${formatCurrency(order.total)}).\n\nEtes-vous sur de vouloir la supprimer definitivement ?\n\nCette action est irreversible.`
        : `Supprimer la commande ${order.order_number} ?`

    if (!await confirm({
        title: 'Supprimer la commande',
        message: firstMessage,
        confirmLabel: 'Supprimer',
        variant: 'danger',
    })) {
        return
    }

    // Double confirmation pour les commandes payées
    if (isPaid) {
        if (!await confirm({
            title: 'Confirmation finale',
            message: `Supprimer definitivement la commande payée ${order.order_number} ?`,
            confirmLabel: 'Supprimer definitivement',
            variant: 'danger',
        })) {
            return
        }
    }

    try {
        await adminApi.deleteOrder(order.id)
        toast.success('Commande supprimée')
        fetchOrders()
    } catch {
        toast.error('Erreur', 'Impossible de supprimer la commande')
    }
}

async function fetchOrders() {
    isLoading.value = true
    try {
        const response = await adminApi.getOrders(
            currentPage.value,
            20,
            statusFilter.value || undefined,
            searchQuery.value || undefined
        )
        orders.value = response.orders
        totalPages.value = response.pagination.last_page
        total.value = response.pagination.total
        from.value = (response.pagination.current_page - 1) * response.pagination.per_page + 1
        to.value = Math.min(from.value + response.pagination.per_page - 1, total.value)
    } catch {
        toast.error('Erreur', 'Impossible de charger les commandes')
    } finally {
        isLoading.value = false
    }
}

watch([currentPage], () => {
    fetchOrders()
})

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout>
watch(searchQuery, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        currentPage.value = 1
        fetchOrders()
    }, 300)
})

onMounted(() => {
    fetchOrders()
})
</script>
