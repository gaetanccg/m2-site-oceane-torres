<template>
    <AdminLayout>
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
                                    <span v-if="gallery.downloadable_count > 0" class="inline-flex items-center gap-1 text-green-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        {{ gallery.downloadable_count }}
                                    </span>
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
        <Modal v-model="showFormModal" :title="isEditing ? 'Modifier la galerie' : 'Nouvelle galerie'" size="md">
            <form @submit.prevent="saveGallery" class="space-y-4">
                <FormField
                    v-model="form.title"
                    label="Titre"
                    required
                    placeholder="Ex: Mariage de Marie et Pierre"
                />

                <FormField
                    v-model="form.description"
                    type="textarea"
                    label="Description"
                    :rows="3"
                    placeholder="Description optionnelle de la galerie"
                />

                <FormField
                    v-model="form.client_id"
                    type="select"
                    label="Client existant (optionnel)"
                    :options="clientOptions"
                    placeholder="Selectionner un client"
                    :disabled="!!form.assigned_email"
                />

                <div class="relative flex items-center">
                    <div class="flex-grow border-t border-gray-200"></div>
                    <span class="mx-4 text-xs text-gray-400 uppercase">ou</span>
                    <div class="flex-grow border-t border-gray-200"></div>
                </div>

                <FormField
                    v-model="form.assigned_email"
                    type="email"
                    label="Email du futur client"
                    placeholder="email@exemple.com"
                    :disabled="!!form.client_id"
                />
                <p class="text-xs text-gray-500 -mt-2">
                    La galerie sera automatiquement liée au client lorsqu'il s'inscrira avec cet email.
                </p>
            </form>

            <template #footer>
                <Button variant="secondary" @click="showFormModal = false">Annuler</Button>
                <Button :loading="isSaving" @click="saveGallery">
                    {{ isEditing ? 'Enregistrer' : 'Creer' }}
                </Button>
            </template>
        </Modal>

        <!-- Gallery Photos Modal -->
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
                <div v-if="uploadProgress > 0 && uploadProgress < 100" class="space-y-2">
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Upload en cours...</span>
                        <span>{{ uploadProgress }}%</span>
                    </div>
                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-gold transition-all duration-300" :style="{ width: `${uploadProgress}%` }" />
                    </div>
                </div>

                <!-- Loading State for Photos -->
                <div v-if="isLoadingPhotos" class="flex items-center justify-center py-16">
                    <svg class="w-10 h-10 text-gold animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <!-- Filter Tabs & Actions -->
                <div v-if="!isLoadingPhotos && galleryPhotos.length > 0" class="flex flex-wrap items-center justify-between gap-4 p-4 bg-gray-50 rounded-xl">
                    <div class="flex gap-2">
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
                            v-if="likedPhotos.length > 0 && !selectionMode"
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
                                Tout sélectionner ({{ filteredPhotos.length }})
                            </button>
                            <button
                                @click="clearSelection"
                                class="text-sm text-gray-500 hover:text-gray-700 underline"
                            >
                                Désélectionner
                            </button>
                        </div>
                        <div class="flex gap-2">
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
                        v-for="(photo, index) in filteredPhotos"
                        :key="photo.id"
                        class="relative group aspect-square rounded-lg overflow-hidden bg-gray-100 cursor-pointer"
                        :class="{
                            'ring-2 ring-green-500': !selectionMode && photo.is_downloadable,
                            'ring-2 ring-red-400': !selectionMode && !photo.is_downloadable && photo.is_liked,
                            'ring-3 ring-blue-500': selectionMode && selectedPhotos.includes(photo.id)
                        }"
                        @click="selectionMode ? togglePhotoSelection(photo.id) : openLightbox(index)"
                    >
                        <img
                            :src="photo.display_url || photo.file_path"
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

                        <!-- Hover actions (only in normal mode) -->
                        <div v-if="!selectionMode" class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100">
                            <button
                                @click.stop="toggleDownloadable(photo)"
                                :class="['p-2 rounded-lg text-white', photo.is_downloadable ? 'bg-green-500 hover:bg-green-600' : 'bg-gray-500 hover:bg-gray-600']"
                                :title="photo.is_downloadable ? 'Retirer du téléchargement' : 'Rendre téléchargeable'"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
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

                        <!-- Status indicators -->
                        <div class="absolute bottom-2 left-2 right-2 flex justify-between">
                            <div v-if="photo.is_liked" class="flex items-center gap-1 text-white text-xs bg-red-500/90 rounded px-2 py-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                                </svg>
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
                Êtes-vous sûr de vouloir supprimer la galerie <strong>{{ galleryToDelete?.title }}</strong> ?
                Toutes les photos seront également supprimées.
            </p>

            <template #footer>
                <Button variant="secondary" @click="showDeleteModal = false">Annuler</Button>
                <Button variant="danger" :loading="isDeleting" @click="deleteGallery">Supprimer</Button>
            </template>
        </Modal>

        <!-- Bulk Delete Confirmation Modal -->
        <Modal v-model="showBulkDeleteModal" title="Supprimer les photos sélectionnées" size="sm">
            <p class="text-gray-600">
                Êtes-vous sûr de vouloir supprimer <strong>{{ selectedPhotos.length }} photo(s)</strong> ?
                Cette action est irréversible.
            </p>

            <template #footer>
                <Button variant="secondary" @click="showBulkDeleteModal = false">Annuler</Button>
                <Button variant="danger" :loading="isBulkProcessing" @click="bulkDeletePhotos">Supprimer</Button>
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
                <button v-if="lightboxIndex < filteredPhotos.length - 1" @click.stop="lightboxIndex++" class="absolute right-4 p-3 text-white/70 hover:text-white bg-black/30 hover:bg-black/50 rounded-full">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <img
                    v-if="currentLightboxPhoto"
                    :src="currentLightboxPhoto.display_url || currentLightboxPhoto.file_path"
                    :alt="currentLightboxPhoto.title || 'Photo'"
                    class="max-h-[90vh] max-w-[90vw] object-contain"
                    @click.stop
                />

                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-4 px-4 py-2 bg-black/50 rounded-full text-white text-sm">
                    <span>{{ lightboxIndex + 1 }} / {{ filteredPhotos.length }}</span>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<script setup lang="ts">
import {ref, reactive, computed, onMounted} from 'vue'
import AdminLayout from '@/components/admin/AdminLayout.vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import {adminApi} from '@/services/adminApi'
import type {AdminGallery, AdminPhoto, Client, GalleryFormData} from '@/types/admin'

const galleries = ref<AdminGallery[]>([])
const clients = ref<Client[]>([])
const isLoadingGalleries = ref(true)
const isLoadingPhotos = ref(false)
const galleryPhotos = ref<AdminPhoto[]>([])
const showFormModal = ref(false)
const showPhotosModal = ref(false)
const showDeleteModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const isDragging = ref(false)
const uploadProgress = ref(0)
const fileInput = ref<HTMLInputElement | null>(null)
const editingId = ref<string | null>(null)
const selectedGallery = ref<AdminGallery | null>(null)
const galleryToDelete = ref<AdminGallery | null>(null)
const photoFilter = ref<'all' | 'liked' | 'downloadable'>('all')
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)
const copiedId = ref<string | null>(null)
const selectionMode = ref(false)
const selectedPhotos = ref<string[]>([])
const isBulkProcessing = ref(false)
const showBulkDeleteModal = ref(false)
const showEmailModal = ref(false)
const galleryForEmail = ref<AdminGallery | null>(null)
const isSendingEmail = ref(false)
const emailForm = reactive({
    email: '',
    recipientName: '',
})

const form = reactive<GalleryFormData>({
    title: '',
    description: '',
    client_id: '',
    assigned_email: '',
})

const clientOptions = computed(() =>
    clients.value.map(c => ({value: c.id, label: c.name || c.email}))
)

const likedPhotos = computed(() => galleryPhotos.value.filter(p => p.is_liked))
const downloadablePhotos = computed(() => galleryPhotos.value.filter(p => p.is_downloadable))

const filteredPhotos = computed(() => {
    switch (photoFilter.value) {
        case 'liked':
            return likedPhotos.value
        case 'downloadable':
            return downloadablePhotos.value
        default:
            return galleryPhotos.value
    }
})

const currentLightboxPhoto = computed(() => filteredPhotos.value[lightboxIndex.value] || null)

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

async function copyToClipboard(text: string, id: string) {
    try {
        await navigator.clipboard.writeText(text)
        copiedId.value = id
        setTimeout(() => {
            copiedId.value = null
        }, 2000)
    } catch { /* ignore */
    }
}

function copyCode(gallery: AdminGallery) {
    if (gallery.share_code) {
        copyToClipboard(gallery.share_code, `code-${gallery.id}`)
    }
}

function copyShareLink(gallery: AdminGallery) {
    if (gallery.share_code) {
        copyToClipboard(getShareUrl(gallery.share_code), `share-${gallery.id}`)
    }
}

function copyDownloadLink(gallery: AdminGallery) {
    if (gallery.access_token) {
        copyToClipboard(getDownloadUrl(gallery.access_token), `dl-${gallery.id}`)
    }
}

function resetForm() {
    form.title = ''
    form.description = ''
    form.client_id = ''
    form.assigned_email = ''
}

function openCreateModal() {
    resetForm()
    isEditing.value = false
    editingId.value = null
    showFormModal.value = true
}

function openEditModal(gallery: AdminGallery) {
    form.title = gallery.title
    form.description = gallery.description || ''
    form.client_id = gallery.client_id || ''
    form.assigned_email = gallery.assigned_email || ''
    isEditing.value = true
    editingId.value = gallery.id
    showFormModal.value = true
}

async function openGallery(gallery: AdminGallery) {
    selectedGallery.value = gallery
    galleryPhotos.value = []
    photoFilter.value = 'all'
    isLoadingPhotos.value = true
    showPhotosModal.value = true

    try {
        const response = await adminApi.getGallery(gallery.id)
        if (response.success && response.data) {
            selectedGallery.value = response.data
            galleryPhotos.value = response.data.photos || []
        }
    } catch (error) {
        console.error('Error fetching gallery:', error)
    } finally {
        isLoadingPhotos.value = false
    }
}

function confirmDelete(gallery: AdminGallery) {
    galleryToDelete.value = gallery
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
        alert('Email envoye avec succes !')
    } catch (error) {
        console.error('Error sending email:', error)
        alert('Erreur lors de l\'envoi de l\'email')
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

function triggerFileInput() {
    fileInput.value?.click()
}

async function saveGallery() {
    isSaving.value = true
    try {
        if (isEditing.value && editingId.value) {
            await adminApi.updateGallery(editingId.value, form)
        } else {
            await adminApi.createGallery(form)
        }
        showFormModal.value = false
        await fetchGalleries()
    } catch (error) {
        console.error('Error saving gallery:', error)
    } finally {
        isSaving.value = false
    }
}

async function deleteGallery() {
    if (!galleryToDelete.value) return
    isDeleting.value = true
    try {
        await adminApi.deleteGallery(galleryToDelete.value.id)
        showDeleteModal.value = false
        galleryToDelete.value = null
        await fetchGalleries()
    } catch (error) {
        console.error('Error deleting gallery:', error)
    } finally {
        isDeleting.value = false
    }
}

async function toggleDownloadable(photo: AdminPhoto) {
    try {
        const response = await adminApi.togglePhotoDownloadable(photo.id)
        if (response.success) {
            photo.is_downloadable = response.data.is_downloadable
        }
    } catch { /* ignore */
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
    uploadProgress.value = 1
    try {
        const response = await adminApi.uploadPhotos(selectedGallery.value.id, files)
        if (response.success) {
            galleryPhotos.value.push(...response.data)
        }
    } catch { /* ignore */
    } finally {
        uploadProgress.value = 100
        setTimeout(() => {
            uploadProgress.value = 0
        }, 1000)
    }
}

async function deletePhoto(photoId: string) {
    try {
        await adminApi.deletePhoto(photoId)
        galleryPhotos.value = galleryPhotos.value.filter(p => p.id !== photoId)
    } catch { /* ignore */
    }
}

async function makeAllLikedDownloadable() {
    const likedNotDownloadable = likedPhotos.value.filter(p => !p.is_downloadable)
    for (const photo of likedNotDownloadable) {
        try {
            const response = await adminApi.togglePhotoDownloadable(photo.id)
            if (response.success) {
                photo.is_downloadable = response.data.is_downloadable
            }
        } catch { /* continue */
        }
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

function selectAllFiltered() {
    selectedPhotos.value = filteredPhotos.value.map(p => p.id)
}

function clearSelection() {
    selectedPhotos.value = []
}

async function bulkSetDownloadable(downloadable: boolean) {
    if (selectedPhotos.value.length === 0) return
    isBulkProcessing.value = true

    try {
        for (const photoId of selectedPhotos.value) {
            const photo = galleryPhotos.value.find(p => p.id === photoId)
            if (photo && photo.is_downloadable !== downloadable) {
                const response = await adminApi.togglePhotoDownloadable(photoId)
                if (response.success) {
                    photo.is_downloadable = response.data.is_downloadable
                }
            }
        }
        // Clear selection after bulk action
        selectedPhotos.value = []
        selectionMode.value = false
    } catch (error) {
        console.error('Error bulk updating photos:', error)
    } finally {
        isBulkProcessing.value = false
    }
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
        // Remove deleted photos from the list
        galleryPhotos.value = galleryPhotos.value.filter(p => !selectedPhotos.value.includes(p.id))
        // Clear selection and close modal
        selectedPhotos.value = []
        selectionMode.value = false
        showBulkDeleteModal.value = false
    } catch (error) {
        console.error('Error bulk deleting photos:', error)
    } finally {
        isBulkProcessing.value = false
    }
}

onMounted(() => {
    fetchGalleries()
    fetchClients()
})
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
