<template>
    <div>
        <AdminHeader title="Galeries" subtitle="Gérez les galeries photos de vos clients">
            <template #actions>
                <Button @click="openCreateModal">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nouvelle galerie
                </Button>
            </template>
        </AdminHeader>

        <div class="p-6">
            <!-- Loading State -->
            <div v-if="isLoadingGalleries" class="flex items-center justify-center py-24">
                <div class="flex flex-col items-center gap-3">
                    <svg class="w-10 h-10 text-gold animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-gray-500 text-sm">Chargement...</span>
                </div>
            </div>

            <template v-else>
                <!-- Empty State -->
                <div v-if="galleries.length === 0" class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-gray-500 mb-4">Aucune galerie</p>
                    <Button @click="openCreateModal">Creer une galerie</Button>
                </div>

                <!-- Galleries Table -->
                <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Galerie</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Code</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Photos</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Vues</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Likes</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Téléchargées</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Derniere visite</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Liens</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="gallery in galleries" :key="gallery.id" class="hover:bg-gray-50">
                                <!-- Galerie (Nom + Description + Date) -->
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ gallery.title }}</div>
                                    <div v-if="gallery.description" class="text-sm text-gray-500 truncate max-w-xs">{{ gallery.description }}</div>
                                    <div class="text-xs text-gray-400 mt-1">Créée le {{ formatDate(gallery.created_at) }}</div>
                                </td>

                                <!-- Code -->
                                <td class="px-4 py-3 text-center">
                                    <div class="relative inline-block">
                                        <button
                                            @click="copyCode(gallery)"
                                            class="inline-flex items-center gap-1 px-2 py-1 font-mono text-sm font-bold bg-gold/10 text-gold rounded hover:bg-gold/20 transition-colors"
                                            title="Copier le code"
                                        >
                                            {{ gallery.share_code }}
                                        </button>
                                    </div>
                                </td>

                                <!-- Photos -->
                                <td class="px-4 py-3 text-center">
                                    <span class="text-gray-900 font-medium">{{ gallery.photos_count }}</span>
                                </td>

                                <!-- Vues -->
                                <td class="px-4 py-3 text-center">
                                    <span class="text-gray-600">{{ gallery.views_count || 0 }}</span>
                                </td>

                                <!-- Likes -->
                                <td class="px-4 py-3 text-center">
                                    <span v-if="gallery.liked_photos_count > 0" class="inline-flex items-center gap-1 text-red-500">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                                        </svg>
                                        {{ gallery.liked_photos_count }}
                                    </span>
                                    <span v-else class="text-gray-400">-</span>
                                </td>

                                <!-- DL -->
                                <td class="px-4 py-3 text-center">
                                    <div v-if="gallery.downloadable_count > 0" class="flex flex-col items-center gap-1">
                                        <!-- Status badge -->
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium',
                                                gallery.download_status === 'complete' ? 'bg-green-100 text-green-700' :
                                                gallery.download_status === 'partial' ? 'bg-yellow-100 text-yellow-700' :
                                                'bg-gray-100 text-gray-600'
                                            ]"
                                        >
                                            <svg v-if="gallery.download_status === 'complete'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            <svg v-else-if="gallery.download_status === 'partial'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            {{ gallery.download_status === 'complete' ? 'Complet' : gallery.download_status === 'partial' ? 'Partiel' : 'En attente' }}
                                        </span>
                                        <!-- Stats -->
                                        <span class="text-xs text-gray-500">
                                            {{ gallery.downloaded_photos_count }}/{{ gallery.downloadable_count }} photos
                                            <span v-if="gallery.total_downloads_count > 0" class="text-gray-400">
                                                ({{ gallery.total_downloads_count }} DL)
                                            </span>
                                        </span>
                                    </div>
                                    <span v-else class="text-gray-400">-</span>
                                </td>

                                <!-- Derniere visite -->
                                <td class="px-4 py-3">
                                    <span v-if="gallery.last_viewed_at" class="text-sm text-gray-600">
                                        {{ formatRelativeDate(gallery.last_viewed_at) }}
                                    </span>
                                    <span v-else class="text-gray-400 text-sm">Jamais</span>
                                </td>

                                <!-- Liens -->
                                <td class="px-4 py-3">
                                    <div class="flex gap-1">
                                        <button
                                            @click="copyShareLink(gallery)"
                                            class="p-1.5 text-gray-500 hover:text-gold hover:bg-gold/10 rounded transition-colors"
                                            :class="{ 'text-green-600 bg-green-50': copiedId === `share-${gallery.id}` }"
                                            title="Copier le lien de consultation"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="copyDownloadLink(gallery)"
                                            class="p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded transition-colors"
                                            :class="{ 'text-green-600 bg-green-50': copiedId === `dl-${gallery.id}` }"
                                            title="Copier le lien de téléchargement"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <button
                                            @click="openEmailModal(gallery)"
                                            class="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded transition-colors"
                                            title="Envoyer par email"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="openGallery(gallery)"
                                            class="p-1.5 text-gold hover:bg-gold/10 rounded transition-colors"
                                            title="Gerer les photos"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="openEditModal(gallery)"
                                            class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded transition-colors"
                                            title="Modifier"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="confirmDelete(gallery)"
                                            class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition-colors"
                                            title="Supprimer"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- Create/Edit Modal -->
        <GalleryFormModal
            v-model="showFormModal"
            :gallery="editingGallery"
            :clients="clients"
            @saved="fetchGalleries"
        />

        <!-- Gallery Photos Manager -->
        <PhotosManager
            v-model="showPhotosModal"
            :gallery-id="selectedGallery?.id || null"
            :gallery-title="selectedGallery?.title || 'Photos'"
            @photos-changed="fetchGalleries"
        />

        <!-- Delete Gallery Confirmation Modal -->
        <Modal v-model="showDeleteModal" title="Confirmer la suppression" size="sm">
            <div class="space-y-4">
                <p class="text-gray-600">
                    Êtes-vous sûr de vouloir supprimer la galerie <strong>{{ galleryToDelete?.title }}</strong> ?
                </p>

                <div
                    v-if="galleryToDelete && galleryToDelete.photos_count > 0"
                    class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1"
                >
                    <p class="font-medium flex items-center gap-1.5">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Cette action est irréversible
                    </p>
                    <p class="ml-5">{{ galleryToDelete.photos_count }} photo(s) seront définitivement supprimées.</p>
                </div>

                <div v-if="galleryToDelete && galleryToDelete.photos_count > 0">
                    <label class="block text-sm text-gray-600 mb-1.5">
                        Tapez <strong class="text-gray-900 select-all">{{ galleryToDelete?.title }}</strong> pour confirmer :
                    </label>
                    <input
                        v-model="deleteConfirmInput"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-red-500 focus:border-red-500"
                        placeholder="Nom de la galerie"
                        @keyup.enter="deleteGalleryConfirmMatches && deleteGallery()"
                    />
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showDeleteModal = false">Annuler</Button>
                <Button
                    variant="danger"
                    :loading="isDeleting"
                    :disabled="!!(galleryToDelete && galleryToDelete.photos_count > 0 && !deleteGalleryConfirmMatches)"
                    @click="deleteGallery"
                >
                    Supprimer
                </Button>
            </template>
        </Modal>

        <!-- Send Email Modal -->
        <Modal v-model="showEmailModal" title="Envoyer la galerie par email" size="sm">
            <div class="space-y-4">
                <p class="text-gray-600 text-sm">
                    Envoyez un email au client avec le lien et le code d'acces pour la galerie
                    <strong class="text-gold">{{ galleryForEmail?.title }}</strong>.
                </p>

                <div class="bg-gold/10 rounded-lg p-3 text-center">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Code d'acces</p>
                    <p class="text-2xl font-mono font-bold text-gold tracking-widest">{{ galleryForEmail?.share_code }}</p>
                </div>

                <FormField
                    v-model="emailForm.recipientName"
                    label="Nom du destinataire"
                    required
                    placeholder="Ex: Marie Dupont"
                />

                <FormField
                    v-model="emailForm.email"
                    type="email"
                    label="Adresse email"
                    required
                    placeholder="email@exemple.com"
                />
            </div>

            <template #footer>
                <Button variant="secondary" @click="showEmailModal = false">Annuler</Button>
                <Button
                    :loading="isSendingEmail"
                    :disabled="!emailForm.email || !emailForm.recipientName"
                    @click="sendGalleryEmail"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Envoyer
                </Button>
            </template>
        </Modal>
</div>
</template>

<script setup lang="ts">
import {ref, reactive, computed, onMounted} from 'vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import GalleryFormModal from '@/components/admin/GalleryFormModal.vue'
import PhotosManager from '@/components/admin/PhotosManager.vue'
import {adminApi} from '@/services/adminApi'
import {useToast} from '@/composables/useToast'
import type {AdminGallery, Client} from '@/types/admin'

const toast = useToast()

const galleries = ref<AdminGallery[]>([])
const clients = ref<Client[]>([])
const isLoadingGalleries = ref(true)
const showFormModal = ref(false)
const showPhotosModal = ref(false)
const showDeleteModal = ref(false)
const isDeleting = ref(false)
const editingGallery = ref<AdminGallery | null>(null)
const selectedGallery = ref<AdminGallery | null>(null)
const galleryToDelete = ref<AdminGallery | null>(null)
const copiedId = ref<string | null>(null)
const deleteConfirmInput = ref('')
const showEmailModal = ref(false)
const galleryForEmail = ref<AdminGallery | null>(null)
const isSendingEmail = ref(false)
const emailForm = reactive({
    email: '',
    recipientName: '',
})


const deleteGalleryConfirmMatches = computed(() => {
    if (!galleryToDelete.value) return false
    return deleteConfirmInput.value.trim() === galleryToDelete.value.title.trim()
})

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {day: 'numeric', month: 'short', year: 'numeric'})
}

function formatRelativeDate(dateStr: string): string {
    const date = new Date(dateStr)
    const now = new Date()
    const diffMs = now.getTime() - date.getTime()
    const diffMins = Math.floor(diffMs / 60000)
    const diffHours = Math.floor(diffMs / 3600000)
    const diffDays = Math.floor(diffMs / 86400000)

    if (diffMins < 1) return "A l'instant"
    if (diffMins < 60) return `Il y a ${diffMins}min`
    if (diffHours < 24) return `Il y a ${diffHours}h`
    if (diffDays < 7) return `Il y a ${diffDays}j`
    return formatDate(dateStr)
}

function getShareUrl(code: string): string {
    return `${window.location.origin}/gallery/${code}`
}

function getDownloadUrl(token: string): string {
    return `${window.location.origin}/gallery/download/${token}`
}

async function copyToClipboard(text: string, id: string, message = 'Copié !') {
    try {
        await navigator.clipboard.writeText(text)
        copiedId.value = id
        toast.success(message)
        setTimeout(() => {
            copiedId.value = null
        }, 2000)
    } catch {
        toast.error('Erreur lors de la copie')
    }
}

function copyCode(gallery: AdminGallery) {
    if (gallery.share_code) {
        copyToClipboard(gallery.share_code, `code-${gallery.id}`, 'Code copié')
    }
}

function copyShareLink(gallery: AdminGallery) {
    if (gallery.share_code) {
        copyToClipboard(getShareUrl(gallery.share_code), `share-${gallery.id}`, 'Lien de consultation copié')
    }
}

function copyDownloadLink(gallery: AdminGallery) {
    if (gallery.access_token) {
        copyToClipboard(getDownloadUrl(gallery.access_token), `dl-${gallery.id}`, 'Lien de téléchargement copié')
    }
}

function openCreateModal() {
    editingGallery.value = null
    showFormModal.value = true
}

function openEditModal(gallery: AdminGallery) {
    editingGallery.value = gallery
    showFormModal.value = true
}

function openGallery(gallery: AdminGallery) {
    selectedGallery.value = gallery
    showPhotosModal.value = true
}

function confirmDelete(gallery: AdminGallery) {
    galleryToDelete.value = gallery
    deleteConfirmInput.value = ''
    showDeleteModal.value = true
}

function openEmailModal(gallery: AdminGallery) {
    galleryForEmail.value = gallery
    // Pre-fill the email form
    emailForm.email = gallery.assigned_email || gallery.user?.email || ''
    emailForm.recipientName = gallery.user?.name || ''
    showEmailModal.value = true
}

async function sendGalleryEmail() {
    if (!galleryForEmail.value || !emailForm.email || !emailForm.recipientName) return

    isSendingEmail.value = true
    try {
        await adminApi.sendGalleryAccessEmail(
            galleryForEmail.value.id,
            emailForm.email,
            emailForm.recipientName
        )
        showEmailModal.value = false
        galleryForEmail.value = null
        emailForm.email = ''
        emailForm.recipientName = ''
        toast.success('Email envoyé', 'Le client a reçu le lien de la galerie')
    } catch {
        toast.error('Erreur', 'Impossible d\'envoyer l\'email')
    } finally {
        isSendingEmail.value = false
    }
}

async function fetchGalleries() {
    isLoadingGalleries.value = true
    try {
        const response = await adminApi.getGalleries()
        galleries.value = response.data
    } catch { /* ignore */
    } finally {
        isLoadingGalleries.value = false
    }
}

async function fetchClients() {
    try {
        const response = await adminApi.getClients(1, 100)
        clients.value = response.data
    } catch { /* ignore */
    }
}

async function deleteGallery() {
    if (!galleryToDelete.value) return
    isDeleting.value = true
    try {
        await adminApi.deleteGallery(galleryToDelete.value.id)
        showDeleteModal.value = false
        toast.success('Galerie supprimée')
        galleryToDelete.value = null
        await fetchGalleries()
    } catch {
        toast.error('Erreur', 'Impossible de supprimer la galerie')
    } finally {
        isDeleting.value = false
    }
}

onMounted(() => {
    fetchGalleries()
    fetchClients()
})
</script>

