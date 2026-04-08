<template>
    <!-- Photos Modal -->
    <Modal v-model="visible" :title="galleryTitle || 'Photos'" size="full">
        <div class="space-y-4">
            <!-- Upload Zone -->
            <div
                v-if="!hideUpload && !isUploading"
                @dragover.prevent="isDragging = true"
                @dragleave="isDragging = false"
                @drop.prevent="handleDrop"
                :class="[
                    'border-2 border-dashed rounded-xl p-6 text-center transition-colors',
                    isDragging ? 'border-gold bg-gold/5' : 'border-gray-300 hover:border-gray-400'
                ]"
            >
                <input
                    type="file"
                    ref="fileInput"
                    multiple
                    accept="image/*"
                    class="hidden"
                    @change="handleFileSelect"
                />
                <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <p class="text-gray-600 mb-2 text-sm">Glissez vos photos ici ou</p>
                <Button variant="secondary" size="sm" @click="triggerFileInput">
                    Parcourir
                </Button>
            </div>

            <!-- Upload Progress -->
            <UploadProgress
                v-if="isUploading || (uploadProgress && !uploadProgress.isComplete)"
                :progress="uploadProgress"
                :show-file-list="true"
                :show-cancel-button="true"
                @cancel="handleCancelUpload"
            />

            <!-- Loading State for Photos -->
            <div v-if="isLoadingPhotos" class="flex items-center justify-center py-16">
                <svg class="w-10 h-10 text-gold animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <!-- Filter Tabs & Actions -->
            <div v-if="!isLoadingPhotos && galleryPhotos.length > 0" class="flex flex-wrap items-center justify-between gap-4 p-4 bg-gray-50 rounded-xl">
                <!-- Filter tabs (client galleries only) -->
                <div v-if="!isEventGallery" class="flex gap-2">
                    <button
                        @click="photoFilter = 'all'"
                        :class="['px-4 py-2 text-sm font-medium rounded-lg transition-colors', photoFilter === 'all' ? 'bg-gold text-white' : 'bg-white text-gray-700 hover:bg-gray-100']"
                    >
                        Toutes ({{ galleryPhotos.length }})
                    </button>
                    <button
                        @click="photoFilter = 'liked'"
                        :class="['px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-1', photoFilter === 'liked' ? 'bg-red-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100']"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                        </svg>
                        Likées ({{ likedPhotos.length }})
                    </button>
                    <button
                        @click="photoFilter = 'downloadable'"
                        :class="['px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-1', photoFilter === 'downloadable' ? 'bg-green-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100']"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Téléchargées ({{ downloadablePhotos.length }})
                    </button>
                </div>

                <!-- Event gallery: simple count -->
                <span v-else class="text-sm text-gray-600">{{ galleryPhotos.length }} photo(s)</span>

                <div class="flex gap-2">
                    <button
                        @click="toggleSelectionMode"
                        :class="['px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-1', selectionMode ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200']"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        {{ selectionMode ? 'Annuler' : 'Sélection' }}
                    </button>
                    <button
                        v-if="!isEventGallery && likedPhotos.length > 0 && !selectionMode"
                        @click="makeAllLikedDownloadable"
                        class="px-4 py-2 text-sm font-medium bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors"
                        title="Rendre toutes les photos likées téléchargeables"
                    >
                        Likes → DL
                    </button>
                </div>
            </div>

            <!-- Selection Actions Bar -->
            <Transition name="slide-down">
                <div v-if="selectionMode && selectedPhotos.length > 0" class="flex flex-wrap items-center justify-between gap-4 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-blue-800">
                            {{ selectedPhotos.length }} photo(s) sélectionnée(s)
                        </span>
                        <button
                            @click="selectAllFiltered"
                            class="text-sm text-blue-600 hover:text-blue-800 underline"
                        >
                            Tout sélectionner ({{ displayedPhotos.length }})
                        </button>
                        <button
                            @click="clearSelection"
                            class="text-sm text-gray-500 hover:text-gray-700 underline"
                        >
                            Désélectionner
                        </button>
                    </div>
                    <div class="flex gap-2">
                        <!-- Bulk downloadable actions (client galleries only) -->
                        <template v-if="!isEventGallery">
                            <button
                                @click="bulkSetDownloadable(true)"
                                class="px-4 py-2 text-sm font-medium bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors flex items-center gap-1"
                                :disabled="isBulkProcessing"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Téléchargeables
                            </button>
                            <button
                                @click="bulkSetDownloadable(false)"
                                class="px-4 py-2 text-sm font-medium bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
                                :disabled="isBulkProcessing"
                            >
                                Non DL
                            </button>
                        </template>
                        <button
                            @click="confirmBulkDelete"
                            class="px-4 py-2 text-sm font-medium bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors flex items-center gap-1"
                            :disabled="isBulkProcessing"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Supprimer
                        </button>
                    </div>
                </div>
            </Transition>

            <!-- Photos Grid -->
            <div v-if="!isLoadingPhotos" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <div
                    v-for="(photo, index) in displayedPhotos"
                    :key="photo.id"
                    class="relative group aspect-square rounded-lg overflow-hidden bg-gray-100 cursor-pointer"
                    :class="{
                        'ring-2 ring-green-500': !isEventGallery && !selectionMode && photo.is_downloadable,
                        'ring-2 ring-red-400': !isEventGallery && !selectionMode && !photo.is_downloadable && photo.is_liked,
                        'ring-3 ring-blue-500': selectionMode && selectedPhotos.includes(photo.id)
                    }"
                    @click="selectionMode ? togglePhotoSelection(photo.id) : openLightbox(index)"
                >
                    <img
                        :src="photo.preview_url || photo.display_url || photo.file_path"
                        :alt="photo.title"
                        class="w-full h-full object-cover"
                    />

                    <!-- Selection checkbox -->
                    <div
                        v-if="selectionMode"
                        class="absolute top-2 left-2 z-10"
                        @click.stop="togglePhotoSelection(photo.id)"
                    >
                        <div
                            :class="[
                                'w-6 h-6 rounded-md border-2 flex items-center justify-center transition-all',
                                selectedPhotos.includes(photo.id)
                                    ? 'bg-blue-500 border-blue-500'
                                    : 'bg-white/80 border-gray-300 hover:border-blue-400'
                            ]"
                        >
                            <svg v-if="selectedPhotos.includes(photo.id)" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>

                    <!-- Current thumbnail indicator (event galleries) -->
                    <div
                        v-if="isEventGallery && thumbnailPhotoId === photo.id"
                        class="absolute top-2 right-2 px-2 py-1 bg-gold text-white text-xs font-medium rounded-lg flex items-center gap-1 z-10"
                    >
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                        Miniature
                    </div>

                    <!-- Hover actions (only in normal mode) -->
                    <div v-if="!selectionMode" class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100">
                        <!-- Toggle downloadable (client galleries) -->
                        <button
                            v-if="!isEventGallery"
                            @click.stop="toggleDownloadable(photo)"
                            :class="['p-2 rounded-lg text-white', photo.is_downloadable ? 'bg-green-500 hover:bg-green-600' : 'bg-gray-500 hover:bg-gray-600']"
                            :title="photo.is_downloadable ? 'Retirer du téléchargement' : 'Rendre téléchargeable'"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </button>
                        <!-- Set as thumbnail (event galleries) -->
                        <button
                            v-if="isEventGallery"
                            @click.stop="setAsThumbnail(photo.id)"
                            class="p-2 bg-gold rounded-lg text-white hover:bg-gold/80"
                            :title="thumbnailPhotoId === photo.id ? 'Retirer comme miniature' : 'Définir comme miniature'"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                        </button>
                        <button
                            @click.stop="confirmDeletePhoto(photo.id)"
                            class="p-2 bg-red-500 rounded-lg text-white hover:bg-red-600"
                            title="Supprimer"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>

                    <!-- Selection overlay -->
                    <div v-if="selectionMode && selectedPhotos.includes(photo.id)" class="absolute inset-0 bg-blue-500/20 pointer-events-none" />

                    <!-- Status indicators (client galleries only) -->
                    <div v-if="!isEventGallery" class="absolute bottom-2 left-2 right-2 flex justify-between">
                        <div class="flex gap-1">
                            <div v-if="photo.is_liked" class="flex items-center gap-1 text-white text-xs bg-red-500/90 rounded px-2 py-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                                </svg>
                            </div>
                            <div v-if="photo.downloads_count > 0" class="flex items-center gap-1 text-white text-xs bg-blue-500/90 rounded px-2 py-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                {{ photo.downloads_count }}
                            </div>
                        </div>
                        <div v-if="photo.is_downloadable" class="flex items-center gap-1 text-white text-xs bg-green-500/90 rounded px-2 py-1 ml-auto">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            DL
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!isLoadingPhotos && galleryPhotos.length === 0 && !hideUpload" class="py-12 text-center text-gray-500">
                Aucune photo dans cette galerie
            </div>

            <!-- Parent gallery info (event galleries) -->
            <div v-if="hideUpload" class="py-12 text-center text-gray-500">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <p class="mb-2">Cette galerie est un conteneur de sous-galeries.</p>
                <p class="text-sm">Les photos se trouvent dans les sous-galeries. Fermez cette modale et cliquez sur "Sous-galeries" pour y accéder.</p>
            </div>
        </div>

        <template #footer>
            <Button variant="secondary" @click="visible = false">Fermer</Button>
        </template>
    </Modal>

    <!-- Single Photo Delete Confirmation Modal -->
    <Modal v-model="showDeletePhotoModal" title="Supprimer la photo" size="sm">
        <div class="space-y-4">
            <div v-if="photoToDelete" class="flex justify-center">
                <img
                    :src="photoToDelete.thumbnail_url || photoToDelete.preview_url || photoToDelete.display_url || photoToDelete.file_path"
                    :alt="photoToDelete.title || 'Photo'"
                    class="h-32 rounded-lg object-cover"
                />
            </div>
            <p class="text-gray-600 text-center">
                Êtes-vous sûr de vouloir supprimer cette photo ?
                Cette action est irréversible.
            </p>
        </div>

        <template #footer>
            <Button variant="secondary" @click="showDeletePhotoModal = false">Annuler</Button>
            <Button variant="danger" :loading="isDeletingPhoto" @click="deletePhoto">Supprimer</Button>
        </template>
    </Modal>

    <!-- Bulk Delete Confirmation Modal -->
    <Modal v-model="showBulkDeleteModal" title="Supprimer les photos sélectionnées" size="sm">
        <div class="space-y-4">
            <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
                <p class="font-medium flex items-center gap-1.5">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Cette action est irréversible
                </p>
                <p class="ml-5">{{ selectedPhotos.length }} photo(s) seront définitivement supprimées.</p>
            </div>

            <div v-if="selectedPhotos.length >= 5">
                <label class="block text-sm text-gray-600 mb-1.5">
                    Tapez <strong class="text-gray-900">{{ selectedPhotos.length }}</strong> pour confirmer :
                </label>
                <input
                    v-model="bulkDeleteConfirmInput"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-red-500 focus:border-red-500"
                    placeholder="Nombre de photos"
                    @keyup.enter="bulkDeleteConfirmMatches && bulkDeletePhotos()"
                />
            </div>
        </div>

        <template #footer>
            <Button variant="secondary" @click="showBulkDeleteModal = false">Annuler</Button>
            <Button
                variant="danger"
                :loading="isBulkProcessing"
                :disabled="selectedPhotos.length >= 5 && !bulkDeleteConfirmMatches"
                @click="bulkDeletePhotos"
            >
                Supprimer ({{ selectedPhotos.length }})
            </Button>
        </template>
    </Modal>

    <!-- Lightbox -->
    <Teleport to="body">
        <div
            v-if="lightboxOpen"
            class="fixed inset-0 z-[100] bg-black/95 flex items-center justify-center"
            @click="lightboxOpen = false"
        >
            <button @click="lightboxOpen = false" class="absolute top-4 right-4 p-2 text-white/70 hover:text-white z-10">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <button v-if="lightboxIndex > 0" @click.stop="lightboxIndex--" class="absolute left-4 p-3 text-white/70 hover:text-white bg-black/30 hover:bg-black/50 rounded-full">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <button v-if="lightboxIndex < displayedPhotos.length - 1" @click.stop="lightboxIndex++" class="absolute right-4 p-3 text-white/70 hover:text-white bg-black/30 hover:bg-black/50 rounded-full">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            <img
                v-if="currentLightboxPhoto"
                :src="currentLightboxPhoto.preview_url || currentLightboxPhoto.display_url || currentLightboxPhoto.file_path"
                :alt="currentLightboxPhoto.title || 'Photo'"
                class="max-h-[90vh] max-w-[90vw] object-contain"
                @click.stop
            />

            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-4 px-4 py-2 bg-black/50 rounded-full text-white text-sm">
                <span>{{ lightboxIndex + 1 }} / {{ displayedPhotos.length }}</span>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import {ref, computed, watch} from 'vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import UploadProgress from '@/components/admin/ui/UploadProgress.vue'
import {adminApi} from '@/services/adminApi'
import {useChunkedUpload} from '@/composables/useChunkedUpload'
import {useToast} from '@/composables/useToast'
import type {AdminPhoto} from '@/types/admin'

const props = withDefaults(defineProps<{
    modelValue: boolean
    galleryId: string | null
    galleryTitle: string
    isEventGallery?: boolean
    /** Hide the upload zone (e.g. parent event galleries) */
    hideUpload?: boolean
    /** Current thumbnail photo id (event galleries) */
    thumbnailPhotoId?: string | null
}>(), {
    isEventGallery: false,
    hideUpload: false,
    thumbnailPhotoId: null,
})

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
    (e: 'photos-changed'): void
    (e: 'thumbnail-changed', photoId: string | null): void
}>()

const toast = useToast()

// Visibility v-model
const visible = computed({
    get: () => props.modelValue,
    set: (v: boolean) => emit('update:modelValue', v),
})

// Upload
const {
    isUploading,
    progress: uploadProgress,
    upload: chunkedUpload,
    cancel: cancelUpload,
    reset: resetUpload,
} = useChunkedUpload()

const fileInput = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)

// Photos state
const galleryPhotos = ref<AdminPhoto[]>([])
const isLoadingPhotos = ref(false)
const photoFilter = ref<'all' | 'liked' | 'downloadable'>('all')

// Selection
const selectionMode = ref(false)
const selectedPhotos = ref<string[]>([])
const isBulkProcessing = ref(false)
const showBulkDeleteModal = ref(false)
const bulkDeleteConfirmInput = ref('')

// Single photo delete
const showDeletePhotoModal = ref(false)
const photoToDelete = ref<AdminPhoto | null>(null)
const isDeletingPhoto = ref(false)

// Lightbox
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)

// Computed
const likedPhotos = computed(() => galleryPhotos.value.filter(p => p.is_liked))
const downloadablePhotos = computed(() => galleryPhotos.value.filter(p => p.is_downloadable))

const filteredPhotos = computed(() => {
    if (props.isEventGallery) return galleryPhotos.value
    switch (photoFilter.value) {
        case 'liked':
            return likedPhotos.value
        case 'downloadable':
            return downloadablePhotos.value
        default:
            return galleryPhotos.value
    }
})

/** The photos currently displayed (filtered for client galleries, all for event galleries) */
const displayedPhotos = computed(() => filteredPhotos.value)

const currentLightboxPhoto = computed(() => displayedPhotos.value[lightboxIndex.value] || null)

const bulkDeleteConfirmMatches = computed(() => {
    return bulkDeleteConfirmInput.value.trim() === String(selectedPhotos.value.length)
})

// Load photos when modal opens
watch(() => props.modelValue, async (open) => {
    if (open && props.galleryId) {
        galleryPhotos.value = []
        photoFilter.value = 'all'
        selectionMode.value = false
        selectedPhotos.value = []
        isLoadingPhotos.value = true

        try {
            if (props.isEventGallery) {
                const response = await adminApi.getEventGallery(props.galleryId)
                if (response.success && response.data) {
                    galleryPhotos.value = response.data.photos || []
                }
            } else {
                const response = await adminApi.getGallery(props.galleryId)
                if (response.success && response.data) {
                    galleryPhotos.value = response.data.photos || []
                }
            }
        } catch {
            // Failed to load photos
        } finally {
            isLoadingPhotos.value = false
        }
    }
})

// Upload functions
function triggerFileInput() {
    fileInput.value?.click()
}

function handleDrop(event: DragEvent) {
    isDragging.value = false
    const files = Array.from(event.dataTransfer?.files || [])
    uploadPhotos(files)
}

function handleFileSelect(event: Event) {
    const target = event.target as HTMLInputElement
    const files = Array.from(target.files || [])
    uploadPhotos(files)
}

async function uploadPhotos(files: File[]) {
    if (!props.galleryId || files.length === 0) return

    try {
        const uploadOptions = props.isEventGallery ? {} : {endpoint: 'galleries' as const}
        const result = await chunkedUpload(props.galleryId, files, uploadOptions)

        if (props.isEventGallery) {
            if (result.completed > 0) {
                await refreshPhotos()
            }
        } else {
            // Reload photos after upload
            await refreshPhotos()
            toast.success('Photos ajoutées', `${files.length} photo(s) uploadée(s)`)
        }
    } catch {
        toast.error('Erreur', 'Échec de l\'upload')
    } finally {
        setTimeout(() => {
            resetUpload()
        }, 2000)
    }

    emit('photos-changed')
}

async function refreshPhotos() {
    if (!props.galleryId) return

    try {
        if (props.isEventGallery) {
            const response = await adminApi.getEventGallery(props.galleryId)
            if (response.success && response.data) {
                galleryPhotos.value = response.data.photos || []
            }
        } else {
            const response = await adminApi.getGallery(props.galleryId)
            if (response.success && response.data) {
                galleryPhotos.value = response.data.photos || []
            }
        }
    } catch {
        toast.error('Erreur', 'Impossible de rafraîchir les photos')
    }
}

function handleCancelUpload() {
    cancelUpload()
    resetUpload()
}

// Downloadable toggle (client galleries)
async function toggleDownloadable(photo: AdminPhoto) {
    try {
        const response = await adminApi.togglePhotoDownloadable(photo.id)
        if (response.success) {
            photo.is_downloadable = response.data.is_downloadable
        }
    } catch { /* ignore */ }
}

async function makeAllLikedDownloadable() {
    const likedNotDownloadable = likedPhotos.value.filter(p => !p.is_downloadable)
    if (likedNotDownloadable.length === 0) {
        toast.info('Toutes les photos likées sont déjà téléchargeables')
        return
    }
    let count = 0
    for (const photo of likedNotDownloadable) {
        try {
            const response = await adminApi.togglePhotoDownloadable(photo.id)
            if (response.success) {
                photo.is_downloadable = response.data.is_downloadable
                count++
            }
        } catch { /* continue */ }
    }
    toast.success(`${count} photo(s) rendues téléchargeables`)
}

// Thumbnail (event galleries)
async function setAsThumbnail(photoId: string) {
    if (!props.galleryId) return

    try {
        const newThumbnailId = props.thumbnailPhotoId === photoId ? null : photoId
        const response = await adminApi.setEventThumbnail(props.galleryId, newThumbnailId)

        if (response.success) {
            emit('thumbnail-changed', newThumbnailId)
        }
    } catch {
        toast.error('Erreur', 'Impossible de modifier la miniature')
    }
}

// Delete single photo
function confirmDeletePhoto(photoId: string) {
    photoToDelete.value = galleryPhotos.value.find(p => p.id === photoId) || null
    showDeletePhotoModal.value = true
}

async function deletePhoto() {
    if (!photoToDelete.value) return
    isDeletingPhoto.value = true
    const photoId = photoToDelete.value.id
    try {
        await adminApi.deletePhoto(photoId)
        galleryPhotos.value = galleryPhotos.value.filter(p => p.id !== photoId)
        showDeletePhotoModal.value = false
        photoToDelete.value = null
        toast.success('Photo supprimée')
        emit('photos-changed')
    } catch {
        toast.error('Erreur', 'Impossible de supprimer la photo')
    } finally {
        isDeletingPhoto.value = false
    }
}

// Selection mode
function toggleSelectionMode() {
    selectionMode.value = !selectionMode.value
    if (!selectionMode.value) {
        selectedPhotos.value = []
    }
}

function togglePhotoSelection(photoId: string) {
    const index = selectedPhotos.value.indexOf(photoId)
    if (index === -1) {
        selectedPhotos.value.push(photoId)
    } else {
        selectedPhotos.value.splice(index, 1)
    }
}

function selectAllFiltered() {
    selectedPhotos.value = displayedPhotos.value.map(p => p.id)
}

function clearSelection() {
    selectedPhotos.value = []
}

function openLightbox(index: number) {
    lightboxIndex.value = index
    lightboxOpen.value = true
}

// Bulk actions
async function bulkSetDownloadable(downloadable: boolean) {
    if (selectedPhotos.value.length === 0) return
    isBulkProcessing.value = true

    try {
        let count = 0
        for (const photoId of selectedPhotos.value) {
            const photo = galleryPhotos.value.find(p => p.id === photoId)
            if (photo && photo.is_downloadable !== downloadable) {
                const response = await adminApi.togglePhotoDownloadable(photoId)
                if (response.success) {
                    photo.is_downloadable = response.data.is_downloadable
                    count++
                }
            }
        }
        selectedPhotos.value = []
        selectionMode.value = false
        toast.success(downloadable
            ? `${count} photo(s) rendues téléchargeables`
            : `${count} photo(s) retirées du téléchargement`)
    } catch {
        toast.error('Erreur', 'Impossible de modifier les photos')
    } finally {
        isBulkProcessing.value = false
    }
}

function confirmBulkDelete() {
    if (selectedPhotos.value.length === 0) return
    bulkDeleteConfirmInput.value = ''
    showBulkDeleteModal.value = true
}

async function bulkDeletePhotos() {
    if (selectedPhotos.value.length === 0) return
    isBulkProcessing.value = true

    try {
        const count = selectedPhotos.value.length
        for (const photoId of selectedPhotos.value) {
            await adminApi.deletePhoto(photoId)
        }
        galleryPhotos.value = galleryPhotos.value.filter(p => !selectedPhotos.value.includes(p.id))
        selectedPhotos.value = []
        selectionMode.value = false
        showBulkDeleteModal.value = false
        toast.success(`${count} photo(s) supprimée(s)`)
        emit('photos-changed')
    } catch {
        toast.error('Erreur', 'Impossible de supprimer les photos')
    } finally {
        isBulkProcessing.value = false
    }
}
</script>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
