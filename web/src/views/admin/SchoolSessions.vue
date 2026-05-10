<template>
    <div>
        <AdminHeader title="Photos Scolaires" subtitle="Shootings scolaires et galeries par enfant">
            <template #actions>
                <Button @click="openCreateModal">Nouveau shooting</Button>
            </template>
        </AdminHeader>

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
            <div v-if="sessions.length === 0" class="text-center py-16">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-gray-500 mb-4">Aucun shooting scolaire</p>
                <Button @click="openCreateModal">Creer le premier shooting</Button>
            </div>

            <DataTable
                v-else
                :columns="columns"
                :data="sessions"
                v-model:current-page="currentPage"
                :total-pages="totalPages"
                :total="total"
            >
                <template #cell-title="{ row }">
                    <button
                        @click="goToDetail(row.id)"
                        class="font-medium text-gray-900 hover:text-gold transition-colors text-left"
                    >
                        {{ row.title }}
                    </button>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :status="row.status" />
                </template>

                <template #cell-galleries_count="{ row }">
                    <span class="text-gray-700">{{ row.galleries_count ?? 0 }}</span>
                </template>

                <template #cell-event_date="{ row }">
                    <span class="text-gray-600">{{ row.event_date ? formatDate(row.event_date) : '-' }}</span>
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-gray-600">{{ formatDate(row.created_at) }}</span>
                </template>

                <template #actions="{ row }">
                    <div class="flex items-center gap-2">
                        <button
                            @click="goToDetail(row.id)"
                            class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                            title="Voir"
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

        <!-- Create Modal -->
        <Modal v-model="showCreateModal" title="Nouveau shooting scolaire" size="lg" :closable="!isCreating">
            <form @submit.prevent="createSession" class="space-y-4">
                <FormField
                    v-model="createForm.title"
                    label="Titre du shooting"
                    placeholder="ex: Ecole Dupont - Juin 2026"
                    required
                />
                <FormField
                    v-model="createForm.event_date"
                    type="date"
                    label="Date du shooting"
                />

                <!-- Pricing -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Tarifs impression scolaire</label>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">1 photo</label>
                            <div class="relative">
                                <input v-model.number="createForm.price1" type="number" step="0.5" min="0"
                                    class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-gold focus:border-gold" />
                                <span class="absolute right-3 top-2.5 text-xs text-gray-400">EUR</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">3 photos</label>
                            <div class="relative">
                                <input v-model.number="createForm.price3" type="number" step="0.5" min="0"
                                    class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-gold focus:border-gold" />
                                <span class="absolute right-3 top-2.5 text-xs text-gray-400">EUR</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">5 photos</label>
                            <div class="relative">
                                <input v-model.number="createForm.price5" type="number" step="0.5" min="0"
                                    class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-gold focus:border-gold" />
                                <span class="absolute right-3 top-2.5 text-xs text-gray-400">EUR</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom message -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message aux parents</label>
                    <textarea
                        v-model="createForm.gallery_message"
                        rows="4"
                        placeholder="ex: Bonjour, voici les photos de votre enfant. Vous pouvez selectionner les tirages que vous souhaitez commander avant le 30 juin..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-gold focus:border-gold resize-none"
                    ></textarea>
                    <p class="text-xs text-gray-400 mt-1">Affiche en haut de chaque galerie d'enfant a la place du bloc d'explication standard.</p>
                </div>

                <!-- ZIP Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fichier ZIP</label>
                    <div
                        class="border-2 border-dashed rounded-lg p-6 text-center transition-colors"
                        :class="zipFile ? 'border-gold bg-gold/5' : 'border-gray-300 hover:border-gray-400'"
                        @dragover.prevent
                        @drop.prevent="onDropZip"
                    >
                        <div v-if="!zipFile">
                            <svg class="w-10 h-10 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="text-gray-500 mb-2">Glissez votre ZIP ici ou</p>
                            <label class="cursor-pointer text-gold hover:text-gold/80 font-medium">
                                parcourir
                                <input type="file" accept=".zip" class="hidden" @change="onSelectZip" />
                            </label>
                        </div>
                        <div v-else class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8" />
                                </svg>
                                <div class="text-left">
                                    <p class="font-medium text-gray-900">{{ zipFile.name }}</p>
                                    <p class="text-sm text-gray-500">{{ formatFileSize(zipFile.size) }}</p>
                                </div>
                            </div>
                            <button v-if="!isCreating" type="button" @click="zipFile = null" class="text-gray-400 hover:text-red-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div v-if="uploadProgress !== null" class="bg-gold/5 border border-gold/20 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">Upload du ZIP</span>
                        <span class="text-sm text-gold font-medium">{{ uploadProgress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-gold rounded-full h-2 transition-all duration-300" :style="{ width: uploadProgress + '%' }"></div>
                    </div>
                    <p v-if="uploadEta.eta.value" class="text-xs text-gray-500 mt-1">
                        Temps restant : {{ uploadEta.eta.value }}
                    </p>
                </div>
            </form>

            <template #footer>
                <Button variant="secondary" :disabled="isCreating" @click="showCreateModal = false">Annuler</Button>
                <Button :loading="isCreating" :disabled="!createForm.title || !zipFile" @click="createSession">
                    Creer et lancer
                </Button>
            </template>
        </Modal>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import DataTable from '@/components/admin/ui/DataTable.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import { adminApi } from '@/services/adminApi'
import { AdminApiError } from '@/services/admin/baseAdmin'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useEta } from '@/composables/useEta'
import type { SchoolSession, TableColumn } from '@/types/admin'

const router = useRouter()
const toast = useToast()
const { confirm } = useConfirm()
const uploadEta = useEta()

const sessions = ref<SchoolSession[]>([])
const isLoading = ref(true)
const currentPage = ref(1)
const totalPages = ref(1)
const total = ref(0)

const columns: TableColumn<SchoolSession>[] = [
    { key: 'title', label: 'Titre' },
    { key: 'status', label: 'Statut' },
    { key: 'galleries_count', label: 'Galeries', align: 'center' },
    { key: 'event_date', label: 'Date shooting', sortable: true },
    { key: 'created_at', label: 'Crée le', sortable: true },
]

async function fetchSessions() {
    try {
        const response = await adminApi.getSchoolSessions(currentPage.value)
        sessions.value = response.data
        totalPages.value = response.last_page
        total.value = response.total
    } catch {
        toast.error('Erreur', 'Impossible de charger les sessions')
    } finally {
        isLoading.value = false
    }
}

function goToDetail(id: string) {
    router.push({ name: 'admin-school-session-detail', params: { id } })
}

// ==================== CREATE ====================
const showCreateModal = ref(false)
const isCreating = ref(false)
const zipFile = ref<File | null>(null)
const uploadProgress = ref<number | null>(null)

const createForm = ref({
    title: '',
    event_date: '',
    gallery_message: '',
    price1: 6,
    price3: 15,
    price5: 22,
})

function openCreateModal() {
    createForm.value = { title: '', event_date: '', gallery_message: '', price1: 6, price3: 15, price5: 22 }
    zipFile.value = null
    uploadProgress.value = null
    uploadEta.reset()
    showCreateModal.value = true
}

function onSelectZip(e: Event) {
    const input = e.target as HTMLInputElement
    if (input.files?.[0]) {
        zipFile.value = input.files[0]
    }
}

function onDropZip(e: DragEvent) {
    const file = e.dataTransfer?.files[0]
    if (file?.name.endsWith('.zip')) {
        zipFile.value = file
    }
}

const CHUNK_SIZE = 50 * 1024 * 1024

async function createSession() {
    if (!createForm.value.title || !zipFile.value) return

    isCreating.value = true
    try {
        const { data: session } = await adminApi.createSchoolSession({
            title: createForm.value.title,
            event_date: createForm.value.event_date,
            gallery_message: createForm.value.gallery_message || undefined,
            product_types: [{
                product_type: 'print_scolaire',
                is_enabled: true,
                price: createForm.value.price1,
                tiers: [
                    { min_quantity: 3, unit_price: +(createForm.value.price3 / 3).toFixed(2) },
                    { min_quantity: 5, unit_price: +(createForm.value.price5 / 5).toFixed(2) },
                ],
            }],
        })

        uploadProgress.value = 0
        uploadEta.reset()

        const file = zipFile.value!
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE)

        for (let i = 0; i < totalChunks; i++) {
            const start = i * CHUNK_SIZE
            const end = Math.min(start + CHUNK_SIZE, file.size)
            const chunk = file.slice(start, end)

            await adminApi.uploadZipChunk(session.id, chunk, i, totalChunks, file.name)
            uploadProgress.value = Math.round(((i + 1) / totalChunks) * 100)
            uploadEta.update(i + 1, totalChunks)
        }

        await adminApi.processSchoolSession(session.id)

        toast.success('Traitement lance', 'Suivi disponible sur la page detail')

        showCreateModal.value = false
        router.push({ name: 'admin-school-session-detail', params: { id: session.id } })

    } catch (e: unknown) {
        const msg = e instanceof AdminApiError
            ? e.message
            : "Une erreur s'est produite, veuillez réessayer plus tard. Si l'erreur persiste, n'hésitez pas à me contacter."
        toast.error('Erreur', msg)
        uploadProgress.value = null
    } finally {
        isCreating.value = false
    }
}

// ==================== ACTIONS ====================
async function confirmDelete(session: SchoolSession) {
    const confirmed = await confirm({
        title: 'Supprimer ce shooting ?',
        message: `"${session.title}" et toutes ses galeries (${session.total_galleries}) seront definitivement supprimes.`,
        variant: 'danger',
    })
    if (!confirmed) return

    try {
        await adminApi.deleteSchoolSession(session.id)
        toast.success('Supprime', 'Le shooting et ses galeries ont ete supprimes')
        fetchSessions()
    } catch {
        toast.error('Erreur', 'Impossible de supprimer le shooting')
    }
}

// ==================== UTILS ====================
function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' Ko'
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' Mo'
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' Go'
}

watch([currentPage], () => fetchSessions())
onMounted(() => fetchSessions())
</script>
