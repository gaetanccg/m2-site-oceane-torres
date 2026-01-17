<template>
    <AdminLayout>
        <AdminHeader title="Bons Cadeaux" subtitle="Gérez les bons cadeaux vendus" />

        <div class="p-6">
            <!-- Stats Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <StatCard
                    label="Bons actifs"
                    :value="stats.active"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                        </svg>
                    </template>
                </StatCard>
                <StatCard
                    label="Valeur totale active"
                    :value="stats.totalValue"
                    format="currency"
                    icon-bg-class="bg-green-100"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </StatCard>
                <StatCard
                    label="Utilisés"
                    :value="stats.used"
                    icon-bg-class="bg-blue-100"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </StatCard>
                <StatCard
                    label="Expirés"
                    :value="stats.expired"
                    icon-bg-class="bg-red-100"
                >
                    <template #icon>
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </StatCard>
            </div>

            <!-- Gift Cards Table -->
            <DataTable
                :columns="columns"
                :data="giftCards"
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
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold"
                    >
                        <option value="">Tous les statuts</option>
                        <option value="active">Actif</option>
                        <option value="used">Utilisé</option>
                        <option value="expired">Expiré</option>
                    </select>
                </template>

                <template #cell-code="{ row }">
                    <div class="flex items-center gap-2">
            <span class="font-mono font-medium text-gray-900 bg-gray-100 px-2 py-1 rounded">
              {{ row.code }}
            </span>
                        <button
                            @click="copyCode(row.code)"
                            class="p-1 text-gray-400 hover:text-gray-600"
                            title="Copier le code"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                    </div>
                </template>

                <template #cell-amount="{ row }">
                    <div>
                        <p class="font-semibold text-gray-900">{{ formatCurrency(row.amount) }}</p>
                        <p v-if="row.remaining_amount !== row.amount" class="text-sm text-gray-500">
                            Reste: {{ formatCurrency(row.remaining_amount) }}
                        </p>
                    </div>
                </template>

                <template #cell-purchaser_email="{ row }">
                    <div>
                        <p class="text-gray-900">{{ row.purchaser_email }}</p>
                        <p v-if="row.recipient_email" class="text-sm text-gray-500">
                            Pour: {{ row.recipient_email }}
                        </p>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :status="row.status" />
                </template>

                <template #cell-expires_at="{ row }">
                    <div>
                        <p :class="isExpiringSoon(row) ? 'text-orange-600 font-medium' : 'text-gray-900'">
                            {{ formatDate(row.expires_at) }}
                        </p>
                        <p v-if="isExpiringSoon(row) && row.status === 'active'" class="text-xs text-orange-500">
                            Expire bientôt
                        </p>
                    </div>
                </template>

                <template #cell-created_at="{ value }">
                    <span class="text-gray-600">{{ formatDate(value as string) }}</span>
                </template>

                <template #actions="{ row }">
                    <div class="flex items-center gap-2">
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
                            v-if="row.status === 'active'"
                            @click="openEditModal(row)"
                            class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                            title="Modifier"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Detail Modal -->
        <Modal v-model="showDetailModal" :title="`Bon cadeau #${selectedGiftCard?.code}`" size="md">
            <div v-if="selectedGiftCard" class="space-y-6">
                <!-- Amount Info -->
                <div class="bg-gold/10 rounded-xl p-6 text-center">
                    <p class="text-sm text-gold/80 mb-1">Valeur</p>
                    <p class="text-3xl font-bold text-gold">{{ formatCurrency(selectedGiftCard.amount) }}</p>
                    <p v-if="selectedGiftCard.remaining_amount !== selectedGiftCard.amount" class="text-sm text-gold/80 mt-2">
                        Solde restant: {{ formatCurrency(selectedGiftCard.remaining_amount) }}
                    </p>
                </div>

                <!-- Details -->
                <div class="space-y-4">
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-500">Statut</span>
                        <StatusBadge :status="selectedGiftCard.status" />
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-500">Acheté par</span>
                        <span class="text-gray-900">{{ selectedGiftCard.purchaser_email }}</span>
                    </div>
                    <div v-if="selectedGiftCard.recipient_email" class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-500">Destinataire</span>
                        <span class="text-gray-900">{{ selectedGiftCard.recipient_email }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-500">Date d'achat</span>
                        <span class="text-gray-900">{{ formatDate(selectedGiftCard.created_at) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-500">Date d'expiration</span>
                        <span class="text-gray-900">{{ formatDate(selectedGiftCard.expires_at) }}</span>
                    </div>
                    <div v-if="selectedGiftCard.used_at" class="flex justify-between py-2">
                        <span class="text-gray-500">Utilisé le</span>
                        <span class="text-gray-900">{{ formatDate(selectedGiftCard.used_at) }}</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showDetailModal = false">Fermer</Button>
            </template>
        </Modal>

        <!-- Edit Modal -->
        <Modal v-model="showEditModal" title="Modifier le bon cadeau" size="md">
            <form @submit.prevent="saveGiftCard" class="space-y-4">
                <FormField
                    v-model="editForm.recipient_email"
                    type="email"
                    label="Mail du destinataire"
                />

                <FormField
                    v-model="editForm.expires_at"
                    type="date"
                    label="Date d'expiration"
                    required
                />
            </form>

            <template #footer>
                <Button variant="secondary" @click="showEditModal = false">Annuler</Button>
                <Button :loading="isSaving" @click="saveGiftCard">Enregistrer</Button>
            </template>
        </Modal>
    </AdminLayout>
</template>

<script setup lang="ts">
import {ref, reactive, computed, watch, onMounted} from 'vue'
import AdminLayout from '@/components/admin/AdminLayout.vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import DataTable from '@/components/admin/ui/DataTable.vue'
import StatCard from '@/components/admin/ui/StatCard.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import {adminApi} from '@/services/adminApi'
import type {AdminGiftCard, TableColumn} from '@/types/admin'

const giftCards = ref<AdminGiftCard[]>([])
const searchQuery = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const total = ref(0)
const from = ref(0)
const to = ref(0)

const showDetailModal = ref(false)
const showEditModal = ref(false)
const selectedGiftCard = ref<AdminGiftCard | null>(null)
const isSaving = ref(false)

const editForm = reactive({
    id: '',
    recipient_email: '',
    expires_at: '',
})

const stats = computed(() => {
    const active = giftCards.value.filter(g => g.status === 'active')
    return {
        active: active.length,
        totalValue: active.reduce((sum, g) => sum + g.remaining_amount, 0),
        used: giftCards.value.filter(g => g.status === 'used').length,
        expired: giftCards.value.filter(g => g.status === 'expired').length,
    }
})

const columns: TableColumn<AdminGiftCard>[] = [
    {key: 'code', label: 'Code'},
    {key: 'amount', label: 'Valeur', align: 'right'},
    {key: 'purchaser_email', label: 'Acheteur'},
    {key: 'status', label: 'Statut'},
    {key: 'expires_at', label: 'Expiration', sortable: true},
    {key: 'created_at', label: 'Créé le', sortable: true},
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
    })
}

function isExpiringSoon(giftCard: AdminGiftCard): boolean {
    if (giftCard.status !== 'active') return false
    const expiresAt = new Date(giftCard.expires_at)
    const now = new Date()
    const daysUntilExpiry = Math.ceil((expiresAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
    return daysUntilExpiry <= 30 && daysUntilExpiry > 0
}

async function copyCode(code: string) {
    try {
        await navigator.clipboard.writeText(code)
    } catch {
        // Fallback
    }
}

function openDetailModal(giftCard: AdminGiftCard) {
    selectedGiftCard.value = giftCard
    showDetailModal.value = true
}

function openEditModal(giftCard: AdminGiftCard) {
    editForm.id = giftCard.id
    editForm.recipient_email = giftCard.recipient_email || ''
    editForm.expires_at = giftCard.expires_at.split('T')[0]
    showEditModal.value = true
}

async function fetchGiftCards() {
    try {
        const response = await adminApi.getGiftCards(currentPage.value, 20)
        giftCards.value = response.data
        totalPages.value = response.meta.last_page
        total.value = response.meta.total
        from.value = response.meta.from
        to.value = response.meta.to
    } catch {
        // Silently fail
    }
}

async function saveGiftCard() {
    isSaving.value = true
    try {
        await adminApi.updateGiftCard(editForm.id, {
            recipient_email: editForm.recipient_email || undefined,
            expires_at: editForm.expires_at,
        })
        showEditModal.value = false
        fetchGiftCards()
    } catch {
        // Handle error
    } finally {
        isSaving.value = false
    }
}

watch([currentPage, statusFilter], () => {
    fetchGiftCards()
})

onMounted(() => {
    fetchGiftCards()
})
</script>
