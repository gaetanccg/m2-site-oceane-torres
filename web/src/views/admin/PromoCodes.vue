<template>
    <div>
        <AdminHeader title="Codes Promo" subtitle="Créez et gérez les codes de réduction">
            <template #actions>
                <Button @click="openCreateModal">Nouveau code</Button>
            </template>
        </AdminHeader>

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
                <StatCard label="Codes actifs" :value="stats.active">
                    <template #icon>
                        <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                    </template>
                </StatCard>
                <StatCard label="Utilisations payées" :value="stats.paidUses" icon-bg-class="bg-blue-100">
                    <template #icon>
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </StatCard>
                <StatCard label="Désactivés" :value="stats.inactive" icon-bg-class="bg-gray-100">
                    <template #icon>
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                    </template>
                </StatCard>
                <StatCard label="Expirés / épuisés" :value="stats.spent" icon-bg-class="bg-red-100">
                    <template #icon>
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </StatCard>
            </div>

            <!-- Table -->
            <DataTable
                :columns="columns"
                :data="giftCodes"
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
                        <option value="">Tous</option>
                        <option value="active">Actifs</option>
                        <option value="inactive">Désactivés</option>
                    </select>
                </template>

                <template #cell-code="{ row }">
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-medium text-gray-900 bg-gray-100 px-2 py-1 rounded">{{ row.code }}</span>
                        <button @click="copyCode(row.code)" class="p-1 text-gray-400 hover:text-gray-600" title="Copier le code">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                    </div>
                </template>

                <template #cell-reduction="{ row }">
                    <span class="font-semibold text-gray-900">{{ formatReduction(row) }}</span>
                </template>

                <template #cell-validity="{ row }">
                    <span class="text-gray-600 text-sm">{{ formatValidity(row) }}</span>
                </template>

                <template #cell-uses="{ row }">
                    <span class="text-gray-700">
                        {{ row.paid_count }}<template v-if="row.max_uses"> / {{ row.max_uses }}</template>
                        <span v-if="row.pending_count > 0" class="text-xs text-gray-400 ml-1">
                            ({{ row.pending_count }} en cours)
                        </span>
                    </span>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :status="row.status" />
                </template>

                <template #cell-created_at="{ value }">
                    <span class="text-gray-600">{{ formatDate(value as string) }}</span>
                </template>

                <template #actions="{ row }">
                    <div class="flex items-center gap-1">
                        <button @click="openDetailModal(row)" class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100" title="Voir détails">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                        <button @click="openEditModal(row)" class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100" title="Modifier">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button @click="toggleCode(row)" class="p-2 rounded-lg hover:bg-gray-100" :class="row.is_active ? 'text-amber-500 hover:text-amber-700' : 'text-green-500 hover:text-green-700'" :title="row.is_active ? 'Désactiver' : 'Activer'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                        <button v-if="!row.is_active" @click="deleteCode(row)" class="p-2 text-gray-500 hover:text-red-600 rounded-lg hover:bg-gray-100" title="Supprimer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Create / Edit Modal -->
        <Modal v-model="showFormModal" :title="isEditing ? 'Modifier le code promo' : 'Nouveau code promo'" size="md">
            <form @submit.prevent="saveCode" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Code</label>
                    <div class="flex gap-2">
                        <input
                            v-model="form.code"
                            type="text"
                            maxlength="24"
                            placeholder="Laisser vide pour générer"
                            class="flex-1 min-w-0 px-4 py-2.5 border border-gray-300 rounded-lg uppercase focus:ring-2 focus:ring-gold focus:border-gold"
                        />
                        <Button type="button" variant="secondary" @click="generate">Générer</Button>
                    </div>
                </div>

                <FormField
                    v-model="form.type"
                    type="select"
                    label="Type de remise"
                    required
                    :options="typeOptions"
                />

                <FormField
                    v-model="form.value"
                    type="number"
                    :label="form.type === 'percent' ? 'Pourcentage (%)' : 'Montant (€)'"
                    required
                    :min="form.type === 'percent' ? 1 : 0.01"
                    :max="form.type === 'percent' ? 100 : undefined"
                    step="0.01"
                />

                <FormField
                    v-if="form.type === 'percent'"
                    v-model="form.max_discount_amount"
                    type="number"
                    label="Plafond de remise (€) — optionnel"
                    min="0.01"
                    step="0.01"
                    helper="Remise maximale en euros pour un code en pourcentage."
                />

                <div class="grid grid-cols-2 gap-4">
                    <FormField v-model="form.valid_from" type="date" label="Valide à partir du" />
                    <FormField v-model="form.valid_until" type="date" label="Valide jusqu'au" />
                </div>

                <FormField
                    v-model="form.max_uses"
                    type="number"
                    label="Nombre max d'utilisations"
                    min="1"
                    step="1"
                    helper="1 par défaut. Laisser vide pour un usage illimité."
                />

                <FormField
                    v-model="form.note"
                    type="textarea"
                    label="Note interne — optionnel"
                    :rows="3"
                    placeholder="Ex : code créé pour le salon de la photo 2026"
                    helper="Visible uniquement dans l'admin, jamais affichée au client."
                />

                <FormField v-model="form.is_active" type="checkbox" checkbox-label="Code actif" />
            </form>

            <template #footer>
                <Button variant="secondary" @click="showFormModal = false">Annuler</Button>
                <Button :loading="isSaving" @click="saveCode">Enregistrer</Button>
            </template>
        </Modal>

        <!-- Detail Modal -->
        <Modal v-model="showDetailModal" :title="`Code ${selected?.code ?? ''}`" size="lg">
            <div v-if="selected" class="space-y-6">
                <div class="bg-gold/10 rounded-xl p-6 text-center">
                    <p class="text-sm text-gold/80 mb-1">Remise</p>
                    <p class="text-3xl font-bold text-gold">{{ formatReduction(selected) }}</p>
                </div>

                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-500">Statut</span>
                        <StatusBadge :status="selected.status" />
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-500">Validité</span>
                        <span class="text-gray-900">{{ formatValidity(selected) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-500">Utilisations</span>
                        <span class="text-gray-900">
                            {{ selected.paid_count }} payée(s)<template v-if="selected.max_uses"> / {{ selected.max_uses }}</template>
                        </span>
                    </div>
                </div>

                <!-- Note interne admin -->
                <div v-if="selected.note" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <p class="text-xs font-medium text-amber-800 mb-1">Note interne</p>
                    <p class="text-sm text-amber-900 whitespace-pre-line">{{ selected.note }}</p>
                </div>

                <!-- Linked orders -->
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Commandes liées</h3>
                    <div v-if="detailLoading" class="text-sm text-gray-500 py-4">Chargement...</div>
                    <div v-else-if="!detailOrders.length" class="text-sm text-gray-500 py-4">Aucune commande pour ce code.</div>
                    <div v-else class="divide-y divide-gray-100 border border-gray-100 rounded-lg">
                        <router-link
                            v-for="order in detailOrders"
                            :key="order.id"
                            :to="{ path: '/admin/orders', query: { search: order.order_number } }"
                            class="flex items-center justify-between px-3 py-2.5 hover:bg-gray-50"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ order.order_number }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ order.customer_email }} · {{ formatDate(order.created_at) }}</p>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <span class="text-xs text-green-700">-{{ formatCurrency(order.discount_amount) }}</span>
                                <span class="text-sm font-medium text-gray-900">{{ formatCurrency(order.total) }}</span>
                                <StatusBadge :status="order.status" />
                            </div>
                        </router-link>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showDetailModal = false">Fermer</Button>
            </template>
        </Modal>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import DataTable from '@/components/admin/ui/DataTable.vue'
import StatCard from '@/components/admin/ui/StatCard.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import { adminApi } from '@/services/adminApi'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import type { AdminGiftCode, GiftCodeFormData, GiftCodeOrder, GiftCodeType, SelectOption, TableColumn } from '@/types/admin'

const toast = useToast()
const { confirm } = useConfirm()

const giftCodes = ref<AdminGiftCode[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const total = ref(0)
const from = ref(0)
const to = ref(0)

const showFormModal = ref(false)
const showDetailModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const selected = ref<AdminGiftCode | null>(null)
const detailOrders = ref<GiftCodeOrder[]>([])
const detailLoading = ref(false)

const typeOptions: SelectOption[] = [
    { value: 'fixed', label: 'Montant fixe (€)' },
    { value: 'percent', label: 'Pourcentage (%)' },
]

const form = reactive({
    id: '',
    code: '',
    type: 'fixed' as GiftCodeType,
    value: '' as string | number,
    max_discount_amount: '' as string | number,
    valid_from: '',
    valid_until: '',
    max_uses: '' as string | number,
    is_active: true,
    note: '',
})

const columns: TableColumn<AdminGiftCode>[] = [
    { key: 'code', label: 'Code' },
    { key: 'reduction', label: 'Réduction' },
    { key: 'validity', label: 'Validité' },
    { key: 'uses', label: 'Utilisations' },
    { key: 'status', label: 'Statut' },
    { key: 'created_at', label: 'Créé le', sortable: true },
]

const stats = computed(() => ({
    active: giftCodes.value.filter(c => c.status === 'active').length,
    paidUses: giftCodes.value.reduce((sum, c) => sum + c.paid_count, 0),
    inactive: giftCodes.value.filter(c => !c.is_active).length,
    spent: giftCodes.value.filter(c => c.status === 'expired' || c.status === 'used_up').length,
}))

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(amount)
}

function formatReduction(code: AdminGiftCode): string {
    if (code.type === 'percent') {
        const base = `${code.value} %`
        return code.max_discount_amount ? `${base} (max ${formatCurrency(code.max_discount_amount)})` : base
    }
    return formatCurrency(code.value)
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatValidity(code: AdminGiftCode): string {
    if (!code.valid_from && !code.valid_until) return 'Illimitée'
    return `${formatDate(code.valid_from)} → ${formatDate(code.valid_until)}`
}

async function copyCode(code: string) {
    try {
        await navigator.clipboard.writeText(code)
        toast.success('Code copié')
    } catch {
        // ignore
    }
}

function resetForm() {
    form.id = ''
    form.code = ''
    form.type = 'fixed'
    form.value = ''
    form.max_discount_amount = ''
    form.valid_from = ''
    form.valid_until = ''
    form.max_uses = 1
    form.is_active = true
    form.note = ''
}

function openCreateModal() {
    resetForm()
    isEditing.value = false
    showFormModal.value = true
}

function openEditModal(code: AdminGiftCode) {
    form.id = code.id
    form.code = code.code
    form.type = code.type
    form.value = code.value
    form.max_discount_amount = code.max_discount_amount ?? ''
    form.valid_from = code.valid_from ? code.valid_from.split('T')[0] : ''
    form.valid_until = code.valid_until ? code.valid_until.split('T')[0] : ''
    form.max_uses = code.max_uses ?? ''
    form.is_active = code.is_active
    form.note = code.note ?? ''
    isEditing.value = true
    showFormModal.value = true
}

async function generate() {
    try {
        const res = await adminApi.generateGiftCode()
        form.code = res.code
    } catch {
        toast.error('Erreur', 'Impossible de générer un code')
    }
}

function buildPayload(): GiftCodeFormData {
    return {
        code: typeof form.code === 'string' && form.code.trim() ? form.code.trim() : undefined,
        type: form.type,
        value: Number(form.value),
        max_discount_amount: form.type === 'percent' && form.max_discount_amount !== '' ? Number(form.max_discount_amount) : null,
        valid_from: form.valid_from || null,
        valid_until: form.valid_until || null,
        max_uses: form.max_uses !== '' ? Number(form.max_uses) : null,
        is_active: form.is_active,
        note: typeof form.note === 'string' && form.note.trim() ? form.note.trim() : null,
    }
}

async function saveCode() {
    if (form.value === '' || Number(form.value) <= 0) {
        toast.error('Erreur', 'Renseignez une valeur de remise valide.')
        return
    }

    isSaving.value = true
    try {
        const payload = buildPayload()
        if (isEditing.value) {
            await adminApi.updateGiftCode(form.id, payload)
            toast.success('Code promo modifié')
        } else {
            await adminApi.createGiftCode(payload)
            toast.success('Code promo créé')
        }
        showFormModal.value = false
        await fetchCodes()
    } catch (e) {
        const msg = e instanceof Error ? e.message : 'Impossible d\'enregistrer le code'
        toast.error('Erreur', msg)
    } finally {
        isSaving.value = false
    }
}

async function toggleCode(code: AdminGiftCode) {
    try {
        await adminApi.toggleGiftCode(code.id)
        await fetchCodes()
    } catch {
        toast.error('Erreur', 'Impossible de modifier le statut du code')
    }
}

async function deleteCode(code: AdminGiftCode) {
    if (!(await confirm(`Supprimer définitivement le code ${code.code} ?`))) return
    try {
        await adminApi.deleteGiftCode(code.id)
        toast.success('Code promo supprimé')
        await fetchCodes()
    } catch (e) {
        const msg = e instanceof Error ? e.message : 'Impossible de supprimer le code'
        toast.error('Erreur', msg)
    }
}

async function openDetailModal(code: AdminGiftCode) {
    selected.value = code
    detailOrders.value = []
    showDetailModal.value = true
    detailLoading.value = true
    try {
        const res = await adminApi.getGiftCode(code.id)
        selected.value = res.gift_code
        detailOrders.value = res.gift_code.orders ?? []
    } catch {
        toast.error('Erreur', 'Impossible de charger le détail du code')
    } finally {
        detailLoading.value = false
    }
}

async function fetchCodes() {
    isLoading.value = true
    try {
        const isActive = statusFilter.value === '' ? undefined : statusFilter.value === 'active'
        const response = await adminApi.getGiftCodes(currentPage.value, 20, searchQuery.value, isActive)
        giftCodes.value = response.data
        totalPages.value = response.last_page
        total.value = response.total
        from.value = response.from
        to.value = response.to
    } catch {
        toast.error('Erreur', 'Impossible de charger les codes promo')
    } finally {
        isLoading.value = false
    }
}

let searchDebounce: ReturnType<typeof setTimeout> | null = null
watch(searchQuery, () => {
    if (searchDebounce) clearTimeout(searchDebounce)
    searchDebounce = setTimeout(() => {
        currentPage.value = 1
        fetchCodes()
    }, 350)
})

watch([currentPage, statusFilter], () => {
    fetchCodes()
})

onMounted(() => {
    fetchCodes()
})
</script>
