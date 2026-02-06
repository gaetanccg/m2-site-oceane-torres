<template>
    <div>
        <AdminHeader title="Galeries d'événements" subtitle="Gerez les galeries publiques de vos événements">
            <template #actions>
                <Button @click="openCreateModal">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nouvel événement
                </Button>
            </template>
        </AdminHeader>

        <div class="p-6">
            <!-- Loading State -->
            <div v-if="isLoading" class="flex items-center justify-center py-24">
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-gray-500 mb-4">Aucune galerie d'événement</p>
                    <Button @click="openCreateModal">Creer une galerie</Button>
                </div>

                <!-- Galleries Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="gallery in galleries"
                        :key="gallery.id"
                        class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow"
                    >
                        <!-- Cover Image -->
                        <div
                            class="aspect-video bg-gray-100 relative cursor-pointer"
                            @click="openGallery(gallery)"
                        >
                            <img
                                v-if="gallery.cover_photo"
                                :src="gallery.cover_photo.thumbnail_url || gallery.cover_photo.preview_url || gallery.cover_photo.display_url || gallery.cover_photo.file_path"
                                :alt="gallery.title"
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <!-- Photos count badge -->
                            <div class="absolute bottom-2 right-2 px-2 py-1 bg-black/70 text-white text-xs rounded-lg">
                                {{ gallery.photos_count }} photo(s)
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 mb-1">{{ gallery.title }}</h3>
                            <p v-if="gallery.description" class="text-sm text-gray-500 line-clamp-2 mb-2">
                                {{ gallery.description }}
                            </p>

                            <div class="flex items-center gap-3 text-sm text-gray-400 mb-1">
                                <span v-if="gallery.event_date" class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ formatDate(gallery.event_date) }}
                                </span>
                                <a v-if="gallery.event_link" :href="gallery.event_link" target="_blank" @click.stop class="flex items-center gap-1 text-gold hover:text-gold/80">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                    Site
                                </a>
                            </div>

                            <div class="flex items-center justify-between text-xs text-gray-400">
                                <span>Crée le {{ formatDate(gallery.created_at) }}</span>
                                <span v-if="gallery.views_count" class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    {{ gallery.views_count }}
                                </span>
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-2 mt-4 pt-4 border-t border-gray-100">
                                <button
                                    @click="openGallery(gallery)"
                                    class="flex-1 px-3 py-2 text-sm font-medium text-gold bg-gold/10 rounded-lg hover:bg-gold/20 transition-colors flex items-center justify-center gap-1"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Photos
                                </button>
                                <button
                                    @click="openEditModal(gallery)"
                                    class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                                    title="Modifier"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    @click="confirmDelete(gallery)"
                                    class="px-3 py-2 text-sm text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                                    title="Supprimer"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Create/Edit Modal -->
        <Modal v-model="showFormModal" :title="isEditing ? 'Modifier l\'événement' : 'Nouvel événement'" size="md">
            <form @submit.prevent="saveGallery" class="space-y-4">
                <FormField
                    v-model="form.title"
                    label="Nom de l'événement"
                    required
                    placeholder="Ex: Mariage Julie & Thomas"
                />

                <FormField
                    v-model="form.event_date"
                    type="date"
                    label="Date de l'événement"
                    placeholder="Date de l'événement"
                />

                <FormField
                    v-model="form.description"
                    type="textarea"
                    label="Description (optionnel)"
                    :rows="3"
                    placeholder="Une courte description de l'événement..."
                />

                <FormField
                    v-model="form.event_link"
                    label="Lien du site (optionnel)"
                    placeholder="https://www.exemple.com"
                />
            </form>

            <template #footer>
                <Button variant="secondary" @click="showFormModal = false">Annuler</Button>
                <Button :loading="isSaving" @click="saveGallery">
                    {{ isEditing ? 'Enregistrer' : 'Creer' }}
                </Button>
            </template>
        </Modal>

        <!-- Photos Modal -->
        <Modal v-model="showPhotosModal" :title="selectedGallery?.title || 'Photos'" size="full">
            <div v-if="selectedGallery" class="space-y-4">
                <!-- Upload Zone -->
                <div
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

                <!-- Selection Mode Toggle -->
                <div v-if="!isLoadingPhotos && galleryPhotos.length > 0" class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <span class="text-sm text-gray-600">{{ galleryPhotos.length }} photo(s)</span>
                    <button
                        @click="toggleSelectionMode"
                        :class="['px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-1', selectionMode ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200']"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        {{ selectionMode ? 'Annuler' : 'Selection' }}
                    </button>
                </div>

                <!-- Selection Actions Bar -->
                <Transition name="slide-down">
                    <div v-if="selectionMode && selectedPhotos.length > 0" class="flex flex-wrap items-center justify-between gap-4 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-medium text-blue-800">
                                {{ selectedPhotos.length }} photo(s) selectionnee(s)
                            </span>
                            <button
                                @click="selectAllPhotos"
                                class="text-sm text-blue-600 hover:text-blue-800 underline"
                            >
                                Tout selectionner ({{ galleryPhotos.length }})
                            </button>
                            <button
                                @click="clearSelection"
                                class="text-sm text-gray-500 hover:text-gray-700 underline"
                            >
                                Deselectionner
                            </button>
                        </div>
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
                </Transition>

                <!-- Photos Grid -->
                <div v-if="!isLoadingPhotos" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <div
                        v-for="(photo, index) in galleryPhotos"
                        :key="photo.id"
                        class="relative group aspect-square rounded-lg overflow-hidden bg-gray-100 cursor-pointer"
                        :class="{
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

                        <!-- Current thumbnail indicator -->
                        <div
                            v-if="selectedGallery?.thumbnail_photo_id === photo.id"
                            class="absolute top-2 right-2 px-2 py-1 bg-gold text-white text-xs font-medium rounded-lg flex items-center gap-1 z-10"
                        >
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                            </svg>
                            Miniature
                        </div>

                        <!-- Hover actions (only in normal mode) -->
                        <div v-if="!selectionMode" class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100">
                            <button
                                @click.stop="setAsThumbnail(photo.id)"
                                class="p-2 bg-gold rounded-lg text-white hover:bg-gold/80"
                                :title="selectedGallery?.thumbnail_photo_id === photo.id ? 'Retirer comme miniature' : 'Definir comme miniature'"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg>
                            </button>
                            <button
                                @click.stop="deletePhoto(photo.id)"
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
                    </div>
                </div>

                <div v-if="!isLoadingPhotos && galleryPhotos.length === 0" class="py-12 text-center text-gray-500">
                    Aucune photo dans cette galerie
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showPhotosModal = false">Fermer</Button>
            </template>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal v-model="showDeleteModal" title="Confirmer la suppression" size="sm">
            <p class="text-gray-600">
                Etes-vous sur de vouloir supprimer la galerie <strong>{{ galleryToDelete?.title }}</strong> ?
                Toutes les photos seront egalement supprimées.
            </p>

            <template #footer>
                <Button variant="secondary" @click="showDeleteModal = false">Annuler</Button>
                <Button variant="danger" :loading="isDeleting" @click="deleteGallery">Supprimer</Button>
            </template>
        </Modal>

        <!-- Bulk Delete Confirmation Modal -->
        <Modal v-model="showBulkDeleteModal" title="Supprimer les photos selectionnées" size="sm">
            <p class="text-gray-600">
                Etes-vous sur de vouloir supprimer <strong>{{ selectedPhotos.length }} photo(s)</strong> ?
                Cette action est irreversible.
            </p>

            <template #footer>
                <Button variant="secondary" @click="showBulkDeleteModal = false">Annuler</Button>
                <Button variant="danger" :loading="isBulkProcessing" @click="bulkDeletePhotos">Supprimer</Button>
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
                <button v-if="lightboxIndex < galleryPhotos.length - 1" @click.stop="lightboxIndex++" class="absolute right-4 p-3 text-white/70 hover:text-white bg-black/30 hover:bg-black/50 rounded-full">
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
                    <span>{{ lightboxIndex + 1 }} / {{ galleryPhotos.length }}</span>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import {ref, reactive, computed, onMounted} from 'vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import UploadProgress from '@/components/admin/ui/UploadProgress.vue'
import {adminApi} from '@/services/adminApi'
import {useChunkedUpload} from '@/composables/useChunkedUpload'
import type {AdminGallery, AdminPhoto, EventGalleryFormData} from '@/types/admin'

interface EventGalleryWithCover extends AdminGallery {
    cover_photo?: AdminPhoto
    thumbnail_photo_id?: string | null
}

const galleries = ref<EventGalleryWithCover[]>([])
const isLoading = ref(true)
const isLoadingPhotos = ref(false)
const galleryPhotos = ref<AdminPhoto[]>([])
const showFormModal = ref(false)
const showPhotosModal = ref(false)
const showDeleteModal = ref(false)
const showBulkDeleteModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const isDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const editingId = ref<string | null>(null)
const selectedGallery = ref<EventGalleryWithCover | null>(null)
const galleryToDelete = ref<EventGalleryWithCover | null>(null)
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)
const selectionMode = ref(false)
const selectedPhotos = ref<string[]>([])
const isBulkProcessing = ref(false)

// Chunked upload
const {
    files: _uploadFiles,
    isUploading,
    progress: uploadProgress,
    completedPhotos: _completedPhotos,
    upload: chunkedUpload,
    cancel: cancelUpload,
    reset: resetUpload
} = useChunkedUpload()

const form = reactive<EventGalleryFormData>({
    title: '',
    description: '',
    event_date: '',
    event_link: '',
})

const currentLightboxPhoto = computed(() => galleryPhotos.value[lightboxIndex.value] || null)

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {day: 'numeric', month: 'short', year: 'numeric'})
}

function resetForm() {
    form.title = ''
    form.description = ''
    form.event_date = ''
    form.event_link = ''
}

function openCreateModal() {
    resetForm()
    isEditing.value = false
    editingId.value = null
    showFormModal.value = true
}

function openEditModal(gallery: EventGalleryWithCover) {
    form.title = gallery.title
    form.description = gallery.description || ''
    form.event_date = gallery.event_date || ''
    form.event_link = gallery.event_link || ''
    isEditing.value = true
    editingId.value = gallery.id
    showFormModal.value = true
}

async function openGallery(gallery: EventGalleryWithCover) {
    selectedGallery.value = gallery
    galleryPhotos.value = []
    isLoadingPhotos.value = true
    showPhotosModal.value = true
    selectionMode.value = false
    selectedPhotos.value = []

    try {
        const response = await adminApi.getEventGallery(gallery.id)
        if (response.success && response.data) {
            selectedGallery.value = response.data as EventGalleryWithCover
            galleryPhotos.value = response.data.photos || []
        }
    } catch {
        // Failed to fetch gallery
    } finally {
        isLoadingPhotos.value = false
    }
}

function confirmDelete(gallery: EventGalleryWithCover) {
    galleryToDelete.value = gallery
    showDeleteModal.value = true
}

async function fetchGalleries() {
    isLoading.value = true
    try {
        const response = await adminApi.getEventGalleries()
        galleries.value = response.data as EventGalleryWithCover[]
    } catch {
        // Failed to fetch galleries
    } finally {
        isLoading.value = false
    }
}

function triggerFileInput() {
    fileInput.value?.click()
}

async function saveGallery() {
    isSaving.value = true
    try {
        if (isEditing.value && editingId.value) {
            await adminApi.updateEventGallery(editingId.value, form)
        } else {
            await adminApi.createEventGallery(form)
        }
        showFormModal.value = false
        await fetchGalleries()
    } catch {
        // Failed to save gallery
    } finally {
        isSaving.value = false
    }
}

async function deleteGallery() {
    if (!galleryToDelete.value) return
    isDeleting.value = true
    try {
        await adminApi.deleteEventGallery(galleryToDelete.value.id)
        showDeleteModal.value = false
        galleryToDelete.value = null
        await fetchGalleries()
    } catch {
        // Failed to delete gallery
    } finally {
        isDeleting.value = false
    }
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
    if (!selectedGallery.value || files.length === 0) return

    try {
        const result = await chunkedUpload(selectedGallery.value.id, files)

        // Refresh gallery photos after upload completes
        if (result.completed > 0) {
            await refreshGalleryPhotos()
        }
    } catch {
        // Upload failed or cancelled
    }
}

async function refreshGalleryPhotos() {
    if (!selectedGallery.value) return

    try {
        const response = await adminApi.getEventGallery(selectedGallery.value.id)
        if (response.success && response.data) {
            galleryPhotos.value = response.data.photos || []
        }
    } catch {
        // Failed to refresh gallery photos
    }
}

function handleCancelUpload() {
    cancelUpload()
    resetUpload()
}

async function deletePhoto(photoId: string) {
    try {
        await adminApi.deletePhoto(photoId)
        galleryPhotos.value = galleryPhotos.value.filter(p => p.id !== photoId)
        // If deleted photo was the thumbnail, clear it
        if (selectedGallery.value?.thumbnail_photo_id === photoId) {
            selectedGallery.value.thumbnail_photo_id = null
        }
    } catch {
        // Failed to delete photo
    }
}

async function setAsThumbnail(photoId: string) {
    if (!selectedGallery.value) return

    try {
        // Toggle: if already thumbnail, remove it; otherwise set it
        const newThumbnailId = selectedGallery.value.thumbnail_photo_id === photoId ? null : photoId
        const response = await adminApi.setEventThumbnail(selectedGallery.value.id, newThumbnailId)

        if (response.success) {
            // Update local state
            selectedGallery.value.thumbnail_photo_id = newThumbnailId

            // Also update in galleries list
            const galleryIndex = galleries.value.findIndex(g => g.id === selectedGallery.value?.id)
            if (galleryIndex !== -1) {
                galleries.value[galleryIndex].thumbnail_photo_id = newThumbnailId
                // Update cover_photo to reflect the new thumbnail
                const thumbnailPhoto = newThumbnailId
                    ? galleryPhotos.value.find(p => p.id === newThumbnailId)
                    : galleryPhotos.value[0]
                if (thumbnailPhoto) {
                    galleries.value[galleryIndex].cover_photo = thumbnailPhoto
                }
            }
        }
    } catch {
        // Failed to set thumbnail
    }
}

function openLightbox(index: number) {
    lightboxIndex.value = index
    lightboxOpen.value = true
}

// Selection mode functions
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

function selectAllPhotos() {
    selectedPhotos.value = galleryPhotos.value.map(p => p.id)
}

function clearSelection() {
    selectedPhotos.value = []
}

function confirmBulkDelete() {
    if (selectedPhotos.value.length === 0) return
    showBulkDeleteModal.value = true
}

async function bulkDeletePhotos() {
    if (selectedPhotos.value.length === 0) return
    isBulkProcessing.value = true

    try {
        for (const photoId of selectedPhotos.value) {
            await adminApi.deletePhoto(photoId)
        }
        galleryPhotos.value = galleryPhotos.value.filter(p => !selectedPhotos.value.includes(p.id))
        selectedPhotos.value = []
        selectionMode.value = false
        showBulkDeleteModal.value = false
    } catch {
        // Failed to bulk delete photos
    } finally {
        isBulkProcessing.value = false
    }
}

onMounted(() => {
    fetchGalleries()
})
</script>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active{
    transition: all 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to{
    opacity: 0;
    transform: translateY(-10px);
}

.line-clamp-2{
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
