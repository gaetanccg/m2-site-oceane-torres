<template>
    <div>
        <AdminHeader
            title="RGPD / Données personnelles"
            subtitle="Rechercher, monitorer et tracer les données d'une personne"
        />

        <div class="p-6 space-y-6">
            <!-- Onglets -->
            <div class="flex gap-2 border-b border-gray-200">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
                    :class="activeTab === t.key ? 'border-gold text-gold' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="switchTab(t.key)"
                >
                    {{ t.label }}
                </button>
            </div>

            <!-- ================= Onglet Recherche ================= -->
            <div v-if="activeTab === 'search'" class="space-y-6">
                <!-- Export global -->
                <div class="bg-white rounded-xl border border-gray-100 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Export global</h3>
                        <p class="text-xs text-gray-500">
                            Télécharge toutes les données (un JSON par table) + les PDF de factures dans un ZIP.
                        </p>
                        <p v-if="exportInfo && exportInfo.status !== 'completed'" class="text-xs text-gray-600 mt-1">
                            Statut : {{ exportStatusLabel }}
                            <span v-if="exportInfo.total_items">({{ exportInfo.processed_items }}/{{ exportInfo.total_items }})</span>
                        </p>
                    </div>
                    <Button :disabled="isExporting" @click="startGlobalExport">
                        {{ isExporting ? 'Génération…' : 'Exporter tout (ZIP)' }}
                    </Button>
                </div>

                <form class="flex flex-col sm:flex-row gap-3 sm:items-end" @submit.prevent="runSearch">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rechercher par</label>
                        <select v-model="searchType" class="rounded-lg border-gray-300 text-sm focus:border-gold focus:ring-gold">
                            <option value="email">E-mail</option>
                            <option value="phone">Téléphone</option>
                            <option value="order_number">N° de commande</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valeur</label>
                        <input
                            v-model="searchValue"
                            type="text"
                            required
                            :placeholder="placeholder"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-gold focus:ring-gold"
                        />
                    </div>
                    <Button type="submit" :disabled="isLoading || !searchValue">
                        {{ isLoading ? 'Recherche…' : 'Rechercher' }}
                    </Button>
                </form>

                <div v-if="result" class="space-y-6">
                    <!-- Résumé + export ciblé -->
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-sm font-semibold text-gray-900">Résultats</h3>
                        <div v-if="!isEmptyResult" class="flex gap-2">
                            <Button variant="secondary" :disabled="isExporting" @click="exportSubject">
                                {{ isExporting ? 'Génération…' : 'Exporter cette personne (ZIP)' }}
                            </Button>
                            <Button variant="danger" @click="openErasure">
                                Supprimer / anonymiser
                            </Button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        <StatCard
                            v-for="(count, key) in result.summary"
                            :key="key"
                            :label="categoryLabel(key)"
                            :value="count"
                        />
                    </div>

                    <p v-if="isEmptyResult" class="text-sm text-gray-500">
                        Aucune donnée trouvée pour cette recherche.
                    </p>

                    <!-- Détail par catégorie -->
                    <div
                        v-for="(rows, category) in nonEmptyCategories"
                        :key="category"
                        class="bg-white rounded-xl border border-gray-100 overflow-hidden"
                    >
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900">{{ categoryLabel(category) }}</h3>
                            <span class="text-xs text-gray-500">{{ rows.length }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-500">
                                    <tr>
                                        <th
                                            v-for="col in columnsOf(rows)"
                                            :key="col"
                                            class="text-left font-medium px-4 py-2 whitespace-nowrap"
                                        >
                                            {{ col }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, i) in rows" :key="i" class="border-t border-gray-50">
                                        <td
                                            v-for="col in columnsOf(rows)"
                                            :key="col"
                                            class="px-4 py-2 text-gray-700 whitespace-nowrap"
                                        >
                                            {{ formatCell(row[col]) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= Onglet Journal d'audit ================= -->
            <div v-else class="space-y-4">
                <p class="text-sm text-gray-500">
                    Traçabilité de toutes les actions RGPD (recherches, exports, suppressions).
                </p>
                <div class="bg-white rounded-xl border border-gray-100 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left font-medium px-4 py-2">Date</th>
                                <th class="text-left font-medium px-4 py-2">Action</th>
                                <th class="text-left font-medium px-4 py-2">Cible</th>
                                <th class="text-left font-medium px-4 py-2">Par</th>
                                <th class="text-left font-medium px-4 py-2">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!auditLogs.length">
                                <td colspan="5" class="px-4 py-6 text-center text-gray-400">Aucune entrée.</td>
                            </tr>
                            <tr v-for="log in auditLogs" :key="log.id" class="border-t border-gray-50">
                                <td class="px-4 py-2 text-gray-700 whitespace-nowrap">{{ formatCell(log.created_at) }}</td>
                                <td class="px-4 py-2"><StatusBadge :status="log.action" /></td>
                                <td class="px-4 py-2 text-gray-700">
                                    <span class="text-gray-400">{{ log.subject_type }}</span> {{ log.subject_value }}
                                </td>
                                <td class="px-4 py-2 text-gray-700">{{ log.actor?.name || '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ log.ip_address || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modale d'effacement / anonymisation -->
            <Modal v-model="showErasureModal" title="Supprimer / anonymiser les données" size="lg">
                <div v-if="erasurePreview" class="space-y-4 text-sm">
                    <p class="text-gray-600">
                        Cible : <span class="font-medium font-mono">{{ erasurePreview.query.value }}</span>
                    </p>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <div class="rounded-lg border border-red-100 bg-red-50 p-3">
                            <p class="font-semibold text-red-700 mb-1">Supprimé</p>
                            <ul class="text-red-700/80 space-y-0.5">
                                <li v-for="(n, k) in erasurePreview.to_delete" :key="k">{{ categoryLabel(k) }} : {{ n }}</li>
                            </ul>
                        </div>
                        <div class="rounded-lg border border-amber-100 bg-amber-50 p-3">
                            <p class="font-semibold text-amber-700 mb-1">Anonymisé</p>
                            <ul class="text-amber-700/80 space-y-0.5">
                                <li v-for="(n, k) in erasurePreview.to_anonymize" :key="k">{{ categoryLabel(k) }} : {{ n }}</li>
                            </ul>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <p class="font-semibold text-gray-700 mb-1">Conservé (légal)</p>
                            <ul class="text-gray-600 space-y-0.5">
                                <li v-for="(n, k) in erasurePreview.retained_legal" :key="k">{{ categoryLabel(k) }} : {{ n }}</li>
                            </ul>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">
                        Les photos de galeries ne sont pas supprimées (accès anonymisé). Les factures/paiements
                        sont conservés au titre de l'obligation comptable. Action irréversible.
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Pour confirmer, tapez : <span class="font-mono">{{ erasurePreview.query.value }}</span>
                        </label>
                        <input
                            v-model="confirmText"
                            type="text"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-red-500"
                        />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button variant="ghost" @click="showErasureModal = false">Annuler</Button>
                        <Button variant="danger" :disabled="!canConfirmErase || isErasing" @click="confirmErase">
                            {{ isErasing ? 'Suppression…' : 'Confirmer' }}
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import Button from '@/components/admin/ui/Button.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import StatCard from '@/components/admin/ui/StatCard.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import { adminApi } from '@/services/adminApi'
import { useToast } from '@/composables/useToast'
import type { PrivacyAuditEntry, PrivacyErasurePreview, PrivacyExportInfo, PrivacySearchResult } from '@/types/admin'

const toast = useToast()

const tabs = [
    { key: 'search', label: 'Recherche' },
    { key: 'audit', label: "Journal d'audit" },
] as const
type TabKey = (typeof tabs)[number]['key']
const activeTab = ref<TabKey>('search')

const searchType = ref<'email' | 'phone' | 'order_number'>('email')
const searchValue = ref('')
const isLoading = ref(false)
const result = ref<PrivacySearchResult | null>(null)

const auditLogs = ref<PrivacyAuditEntry[]>([])

const exportInfo = ref<PrivacyExportInfo | null>(null)
let exportTimer: ReturnType<typeof setInterval> | null = null

const isExporting = computed(
    () => !!exportInfo.value && ['pending', 'processing'].includes(exportInfo.value.status),
)

const EXPORT_STATUS_LABELS: Record<string, string> = {
    pending: 'en attente',
    processing: 'en cours',
    completed: 'terminé',
    failed: 'échec',
}
const exportStatusLabel = computed(() =>
    exportInfo.value ? EXPORT_STATUS_LABELS[exportInfo.value.status] || exportInfo.value.status : '',
)

const CATEGORY_LABELS: Record<string, string> = {
    accounts: 'Comptes',
    users: 'Comptes',
    clients: 'Clients',
    orders: 'Commandes',
    order_items: 'Lignes de commande',
    payments: 'Paiements',
    invoices: 'Factures',
    reservations: 'Réservations',
    client_forms: 'Formulaires client',
    carts: 'Paniers',
    contact_messages: 'Messages contact',
    galleries: 'Galeries',
    gift_cards: 'Bons cadeaux',
    download_logs: 'Logs de téléchargement',
    photo_uploads: 'Uploads',
    notifications: 'Notifications',
}

const showErasureModal = ref(false)
const erasurePreview = ref<PrivacyErasurePreview | null>(null)
const confirmText = ref('')
const isErasing = ref(false)

const canConfirmErase = computed(
    () => !!erasurePreview.value && confirmText.value === erasurePreview.value.query.value,
)

const placeholder = computed(() => {
    if (searchType.value === 'email') return 'client@exemple.fr'
    if (searchType.value === 'phone') return '0612345678'
    return 'CMD-2026-000123'
})

const nonEmptyCategories = computed<Record<string, Array<Record<string, unknown>>>>(() => {
    if (!result.value) return {}
    return Object.fromEntries(
        Object.entries(result.value.categories).filter(([, rows]) => rows.length > 0),
    )
})

const isEmptyResult = computed(() => {
    if (!result.value) return false
    return Object.values(result.value.summary).every((n) => n === 0)
})

function categoryLabel(key: string): string {
    return CATEGORY_LABELS[key] || key
}

function columnsOf(rows: Array<Record<string, unknown>>): string[] {
    return rows.length ? Object.keys(rows[0]) : []
}

function formatCell(value: unknown): string {
    if (value === null || value === undefined) return '—'
    if (typeof value === 'boolean') return value ? 'oui' : 'non'
    return String(value)
}

async function runSearch() {
    if (!searchValue.value) return
    isLoading.value = true
    try {
        result.value = await adminApi.searchPrivacy(searchType.value, searchValue.value.trim())
    } catch {
        toast.error('Erreur', 'La recherche a échoué.')
    } finally {
        isLoading.value = false
    }
}

async function loadAudit() {
    try {
        const response = await adminApi.getPrivacyAudit(1, 50)
        auditLogs.value = response.logs
    } catch {
        toast.error('Erreur', "Impossible de charger le journal d'audit.")
    }
}

function switchTab(tab: TabKey) {
    activeTab.value = tab
    if (tab === 'audit') loadAudit()
}

function stopExportPolling() {
    if (exportTimer) {
        clearInterval(exportTimer)
        exportTimer = null
    }
}

async function beginExport(trigger: () => Promise<{ export: PrivacyExportInfo }>) {
    try {
        const response = await trigger()
        exportInfo.value = response.export
        stopExportPolling()
        exportTimer = setInterval(pollExport, 2000)
    } catch {
        toast.error('Erreur', "Impossible de lancer l'export.")
    }
}

function startGlobalExport() {
    beginExport(() => adminApi.exportAllPrivacy())
}

function exportSubject() {
    if (!searchValue.value) return
    beginExport(() => adminApi.exportSubjectPrivacy(searchType.value, searchValue.value.trim()))
}

async function pollExport() {
    if (!exportInfo.value) return
    try {
        const response = await adminApi.getPrivacyExport(exportInfo.value.id)
        exportInfo.value = response.export
        if (response.export.status === 'completed') {
            stopExportPolling()
            toast.success('Export prêt', 'Le téléchargement va démarrer.')
            window.location.href = adminApi.getPrivacyExportDownloadUrl(response.export.id)
        } else if (response.export.status === 'failed') {
            stopExportPolling()
            toast.error('Export échoué', response.export.error_message || 'La génération a échoué.')
        }
    } catch {
        stopExportPolling()
        toast.error('Erreur', "Le suivi de l'export a échoué.")
    }
}

async function openErasure() {
    if (!searchValue.value) return
    try {
        erasurePreview.value = await adminApi.erasurePreviewPrivacy(searchType.value, searchValue.value.trim())
        confirmText.value = ''
        showErasureModal.value = true
    } catch {
        toast.error('Erreur', "Impossible de charger l'aperçu de suppression.")
    }
}

async function confirmErase() {
    if (!erasurePreview.value || !canConfirmErase.value) return
    isErasing.value = true
    try {
        const { type, value } = erasurePreview.value.query
        await adminApi.erasePrivacy(type, value, confirmText.value)
        toast.success('Terminé', 'Les données ont été supprimées / anonymisées.')
        showErasureModal.value = false
        await runSearch()
    } catch {
        toast.error('Erreur', "L'effacement a échoué.")
    } finally {
        isErasing.value = false
    }
}

onBeforeUnmount(stopExportPolling)
</script>
