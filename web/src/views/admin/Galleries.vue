<template>
    <AdminLayout>
        <AdminHeader title="Galeries" subtitle="Gérez vos galeries photos pour vos clients">
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
                    <span class="text-gray-500 text-sm">Chargement des galeries...</span>
                </div>
            </div>

            <!-- Content -->
            <template v-else>
                <!-- Stats Header -->
                <div class="mb-6 p-4 bg-white rounded-xl border border-gray-200">
                    <p class="text-gray-600">
                        <span class="text-2xl font-semibold text-gray-900">{{ galleries.length }}</span>
                        galerie{{ galleries.length > 1 ? 's' : '' }}
                    </p>
                </div>

                <!-- Galleries Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="gallery in filteredGalleries"
                        :key="gallery.id"
                        class="bg-white rounded-xl border border-gray-200 overflow-hidden group"
                    >
                        <!-- Cover Image -->
                        <div class="relative h-48 bg-gray-100">
                            <img
                                v-if="gallery.cover_image"
                                :src="gallery.cover_image"
                                :alt="gallery.title"
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>

                            <!-- Status badges -->
                            <div class="absolute top-2 left-2 flex flex-wrap gap-1">
                            <span
                                :class="[
                                    'px-2 py-0.5 text-xs font-medium rounded-full',
                                    gallery.is_active ? 'bg-green-500 text-white' : 'bg-gray-400 text-white'
                                ]"
                            >
                                {{ gallery.is_active ? 'Active' : 'Inactive' }}
                            </span>
                            </div>

                            <!-- Views counter -->
                            <div v-if="gallery.views_count > 0" class="absolute top-2 right-2 flex items-center gap-1 bg-black/60 text-white text-xs px-2 py-1 rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                {{ gallery.views_count }}
                            </div>

                            <!-- Overlay -->
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
                                <div class="flex gap-2">
                                    <button
                                        @click="openGallery(gallery)"
                                        class="p-2 bg-white rounded-lg text-gray-700 hover:bg-gray-100"
                                        title="Gérer les photos"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="openEditModal(gallery)"
                                        class="p-2 bg-white rounded-lg text-gray-700 hover:bg-gray-100"
                                        title="Modifier"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                        </div>

                        <!-- Info -->
                        <div class="p-4">
                            <div class="flex items-start justify-between mb-2">
                                <h3 class="font-semibold text-gray-900">{{ gallery.title }}</h3>
                            </div>

                            <p v-if="gallery.description" class="text-sm text-gray-500 mb-3 line-clamp-2">
                                {{ gallery.description }}
                            </p>

                            <!-- Stats Grid -->
                            <div class="grid grid-cols-3 gap-2 mb-3 text-center">
                                <div class="bg-gray-50 rounded-lg p-2">
                                    <div class="text-lg font-semibold text-gray-900">{{ gallery.photos_count }}</div>
                                    <div class="text-xs text-gray-500">Photos</div>
                                </div>
                                <div class="bg-red-50 rounded-lg p-2">
                                    <div class="text-lg font-semibold text-red-600">{{ gallery.liked_photos_count || 0 }}</div>
                                    <div class="text-xs text-gray-500">Likees</div>
                                </div>
                                <div class="bg-green-50 rounded-lg p-2">
                                    <div class="text-lg font-semibold text-green-600">{{ gallery.downloadable_count }}</div>
                                    <div class="text-xs text-gray-500">DL</div>
                                </div>
                            </div>

                            <!-- Last viewed info -->
                            <div v-if="gallery.last_viewed_at" class="flex items-center gap-2 text-xs text-gray-500 mb-3">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Derniere visite: {{ formatRelativeDate(gallery.last_viewed_at) }}
                            </div>

                            <div v-if="gallery.client" class="flex items-center gap-1 text-sm text-gray-500 mb-3">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                {{ gallery.client.first_name }} {{ gallery.client.last_name }}
                            </div>

                            <!-- Gallery Links -->
                            <div class="pt-3 border-t border-gray-100 space-y-3">
                                <!-- Share Code -->
                                <div v-if="gallery.share_code">
                                    <label class="text-xs text-gray-500 block mb-1">Code de partage (galerie protegee)</label>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 px-3 py-1.5 text-sm font-mono font-bold bg-gold/10 border border-gold/20 rounded-lg text-center tracking-widest">
                                            {{ gallery.share_code }}
                                        </div>
                                        <button
                                            @click="copyToClipboard(gallery.share_code, `code-${gallery.id}`)"
                                            :class="[
                                                'p-1.5 rounded-lg transition-all duration-300',
                                                copiedId === `code-${gallery.id}`
                                                    ? 'text-green-600 bg-green-100'
                                                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
                                            ]"
                                            :title="copiedId === `code-${gallery.id}` ? 'Copié !' : 'Copier le code'"
                                        >
                                            <svg v-if="copiedId === `code-${gallery.id}`" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Download URL -->
                                <div v-if="gallery.access_token">
                                    <label class="text-xs text-gray-500 block mb-1">Lien de telechargement ({{ gallery.downloadable_count }} photos)</label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="text"
                                            :value="getDownloadUrl(gallery.access_token)"
                                            readonly
                                            class="flex-1 px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg truncate"
                                        />
                                        <button
                                            @click="copyToClipboard(getDownloadUrl(gallery.access_token), `link-${gallery.id}`)"
                                            :class="[
                                                'p-1.5 rounded-lg transition-all duration-300',
                                                copiedId === `link-${gallery.id}`
                                                    ? 'text-green-600 bg-green-100'
                                                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
                                            ]"
                                            :title="copiedId === `link-${gallery.id}` ? 'Copié !' : 'Copier le lien'"
                                        >
                                            <svg v-if="copiedId === `link-${gallery.id}`" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <p v-if="gallery.expires_at" class="text-xs text-gray-400">
                                    Expire le {{ formatDate(gallery.expires_at) }}
                                </p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
                                <button
                                    @click="openGallery(gallery)"
                                    class="flex-1 px-3 py-2 text-sm font-medium text-gold bg-gold/10 rounded-lg hover:bg-gold/20 transition-colors"
                                >
                                    Gerer les photos
                                </button>
                                <button
                                    @click="confirmDelete(gallery)"
                                    class="p-2 text-red-500 hover:text-red-700 rounded-lg hover:bg-red-50"
                                    title="Supprimer"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="filteredGalleries.length === 0"
                        class="col-span-full bg-white rounded-xl border border-gray-200 p-12 text-center"
                    >
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-gray-500 mb-4">Aucune galerie</p>
                        <Button @click="openCreateModal">Créer une galerie</Button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Create/Edit Modal -->
        <Modal v-model="showFormModal" :title="isEditing ? 'Modifier la galerie' : 'Nouvelle galerie'" size="lg">
            <form @submit.prevent="saveGallery" class="space-y-4">
                <FormField
                    v-model="form.title"
                    label="Titre"
                    required
                />

                <FormField
                    v-model="form.description"
                    type="textarea"
                    label="Description"
                    :rows="3"
                />

                <FormField
                    v-model="form.client_id"
                    type="select"
                    label="Client"
                    :options="clientOptions"
                    placeholder="Sélectionner un client (optionnel)"
                />

                <FormField
                    v-model="form.expires_at"
                    type="date"
                    label="Date d'expiration"
                    helper="Laisser vide pour un accès permanent"
                />

                <FormField
                    v-model="form.is_active"
                    type="checkbox"
                    checkbox-label="Galerie active (visible)"
                />
            </form>

            <template #footer>
                <Button variant="secondary" @click="showFormModal = false">Annuler</Button>
                <Button :loading="isSaving" @click="saveGallery">
                    {{ isEditing ? 'Enregistrer' : 'Créer' }}
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
                        <div
                            class="h-full bg-gold transition-all duration-300"
                            :style="{ width: `${uploadProgress}%` }"
                        />
                    </div>
                </div>

                <!-- Loading State for Photos -->
                <div v-if="isLoadingPhotos" class="flex items-center justify-center py-16">
                    <div class="flex flex-col items-center gap-3">
                        <svg class="w-10 h-10 text-gold animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-gray-500 text-sm">Chargement des photos...</span>
                    </div>
                </div>

                <!-- Filter Tabs & Actions -->
                <div v-if="!isLoadingPhotos" class="flex flex-wrap items-center justify-between gap-4 p-4 bg-gray-50 rounded-xl">
                    <div class="flex gap-2">
                        <button
                            @click="photoFilter = 'all'"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
                                photoFilter === 'all' ? 'bg-gold text-white' : 'bg-white text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            Toutes ({{ galleryPhotos.length }})
                        </button>
                        <button
                            @click="photoFilter = 'liked'"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-1',
                                photoFilter === 'liked' ? 'bg-red-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                            </svg>
                            Likees ({{ likedPhotos.length }})
                        </button>
                        <button
                            @click="photoFilter = 'downloadable'"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-1',
                                photoFilter === 'downloadable' ? 'bg-green-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Telechargeable ({{ downloadablePhotos.length }})
                        </button>
                    </div>

                    <!-- Selection Mode Toggle & Quick Actions -->
                    <div class="flex gap-2">
                        <button
                            @click="toggleSelectionMode"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-1',
                                selectionMode ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            {{ selectionMode ? 'Annuler' : 'Selectionner' }}
                        </button>
                        <button
                            v-if="likedPhotos.length > 0 && !selectionMode"
                            @click="makeAllLikedDownloadable"
                            class="px-4 py-2 text-sm font-medium bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors flex items-center gap-1"
                            title="Rendre toutes les photos likees telechargeables"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                            </svg>
                            → DL
                        </button>
                        <button
                            v-if="downloadablePhotos.length > 0 && !selectionMode"
                            @click="removeAllDownloadable"
                            class="px-4 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                            title="Retirer toutes les photos du telechargement"
                        >
                            Tout retirer
                        </button>
                    </div>
                </div>

                <!-- Selection Action Bar -->
                <div
                    v-if="!isLoadingPhotos && selectionMode && selectedPhotos.length > 0"
                    class="flex items-center justify-between p-4 bg-blue-50 border border-blue-200 rounded-xl"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-blue-800">
                            {{ selectedPhotos.length }} photo{{ selectedPhotos.length > 1 ? 's' : '' }} selectionnee{{ selectedPhotos.length > 1 ? 's' : '' }}
                        </span>
                        <button
                            @click="selectAllFiltered"
                            class="text-sm text-blue-600 hover:text-blue-800 underline"
                        >
                            Tout selectionner
                        </button>
                        <button
                            @click="clearSelection"
                            class="text-sm text-gray-500 hover:text-gray-700 underline"
                        >
                            Deselectionner
                        </button>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="setSelectedDownloadable(true)"
                            class="px-4 py-2 text-sm font-medium bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors flex items-center gap-1"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Rendre telechargeables
                        </button>
                        <button
                            @click="setSelectedDownloadable(false)"
                            class="px-4 py-2 text-sm font-medium bg-gray-500 text-red-400 rounded-lg hover:bg-gray-600 transition-colors"
                        >
                            Retirer du DL
                        </button>
                    </div>
                </div>

                <!-- Photos Grid -->
                <div v-if="!isLoadingPhotos" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <div
                        v-for="(photo, index) in filteredPhotos"
                        :key="photo.id"
                        class="relative group aspect-square rounded-lg overflow-hidden bg-gray-100 cursor-pointer"
                        :class="{
                            'ring-2 ring-green-500': photo.is_downloadable && !isSelected(photo.id),
                            'ring-2 ring-red-400': !photo.is_downloadable && photo.is_liked && !isSelected(photo.id),
                            'ring-4 ring-blue-500': isSelected(photo.id)
                        }"
                        @click="selectionMode ? togglePhotoSelection(photo.id) : openLightbox(index)"
                    >
                        <img
                            :src="photo.thumbnail_path || photo.file_path || photo.path"
                            :alt="photo.original_filename || photo.title"
                            class="w-full h-full object-cover"
                        />

                        <!-- Selection checkbox -->
                        <div
                            v-if="selectionMode"
                            class="absolute top-2 left-2 z-10"
                        >
                            <div
                                :class="[
                                    'w-6 h-6 rounded-md border-2 flex items-center justify-center transition-colors',
                                    isSelected(photo.id)
                                        ? 'bg-blue-500 border-blue-500'
                                        : 'bg-white/80 border-gray-300 hover:border-blue-400'
                                ]"
                            >
                                <svg
                                    v-if="isSelected(photo.id)"
                                    class="w-4 h-4 text-white"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>

                        <!-- Hover actions (only when not in selection mode) -->
                        <div
                            v-if="!selectionMode"
                            class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100"
                        >
                            <button
                                @click.stop="toggleDownloadable(photo)"
                                :class="[
                                    'p-2 rounded-lg text-white',
                                    photo.is_downloadable ? 'bg-green-500 hover:bg-green-600' : 'bg-gray-500 hover:bg-gray-600'
                                ]"
                                :title="photo.is_downloadable ? 'Retirer du telechargement' : 'Rendre telechargeable'"
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

                        <!-- Status indicators -->
                        <div class="absolute bottom-2 left-2 right-2 flex justify-between">
                            <div v-if="photo.is_liked" class="flex items-center gap-1 text-white text-xs bg-red-500/90 rounded px-2 py-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                                </svg>
                            </div>
                            <div v-if="photo.is_downloadable" class="flex items-center gap-1 text-white text-xs bg-green-500/90 rounded px-2 py-1">
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
                <div v-else-if="!isLoadingPhotos && filteredPhotos.length === 0" class="py-12 text-center text-gray-500">
                    Aucune photo dans ce filtre
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
                Toutes les photos seront également supprimées. Cette action est irréversible.
            </p>

            <template #footer>
                <Button variant="secondary" @click="showDeleteModal = false">Annuler</Button>
                <Button variant="danger" :loading="isDeleting" @click="deleteGallery">Supprimer</Button>
            </template>
        </Modal>

        <!-- Lightbox -->
        <Teleport to="body">
            <div
                v-if="lightboxOpen"
                class="fixed inset-0 z-[100] bg-black/95 flex items-center justify-center"
                @click="closeLightbox"
            >
                <!-- Close button -->
                <button
                    @click="closeLightbox"
                    class="absolute top-4 right-4 p-2 text-white/70 hover:text-white transition-colors z-10"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Navigation buttons -->
                <button
                    v-if="lightboxIndex > 0"
                    @click.stop="lightboxIndex--"
                    class="absolute left-4 p-3 text-white/70 hover:text-white bg-black/30 hover:bg-black/50 rounded-full transition-colors"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button
                    v-if="lightboxIndex < filteredPhotos.length - 1"
                    @click.stop="lightboxIndex++"
                    class="absolute right-4 p-3 text-white/70 hover:text-white bg-black/30 hover:bg-black/50 rounded-full transition-colors"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <!-- Image -->
                <img
                    v-if="currentLightboxPhoto"
                    :src="currentLightboxPhoto.file_path || currentLightboxPhoto.path"
                    :alt="currentLightboxPhoto.title || 'Photo'"
                    class="max-h-[90vh] max-w-[90vw] object-contain"
                    @click.stop
                />

                <!-- Info bar -->
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-4 px-4 py-2 bg-black/50 rounded-full text-white text-sm">
                    <span>{{ lightboxIndex + 1 }} / {{ filteredPhotos.length }}</span>
                    <span v-if="currentLightboxPhoto?.is_liked" class="flex items-center gap-1 text-red-400">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                        </svg>
                    </span>
                    <span v-if="currentLightboxPhoto?.is_downloadable" class="flex items-center gap-1 text-green-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        DL
                    </span>
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
const selectionMode = ref(false)
const selectedPhotos = ref<string[]>([])
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)
const copiedId = ref<string | null>(null)

const form = reactive<GalleryFormData>({
    title: '',
    description: '',
    client_id: '',
    expires_at: '',
    is_active: true,
})

const filteredGalleries = computed(() => galleries.value)

const clientOptions = computed(() =>
    clients.value.map(c => ({value: c.id, label: `${c.first_name} ${c.last_name}`}))
)

const likedPhotos = computed(() =>
    galleryPhotos.value.filter(p => p.is_liked)
)

const downloadablePhotos = computed(() =>
    galleryPhotos.value.filter(p => p.is_downloadable)
)

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

const currentLightboxPhoto = computed(() => {
    return filteredPhotos.value[lightboxIndex.value] || null
})

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
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

    if (diffMins < 1) return "a l'instant"
    if (diffMins < 60) return `il y a ${diffMins} min`
    if (diffHours < 24) return `il y a ${diffHours}h`
    if (diffDays < 7) return `il y a ${diffDays} jour${diffDays > 1 ? 's' : ''}`
    return formatDate(dateStr)
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
    } catch {
        // Fallback
    }
}

function resetForm() {
    form.title = ''
    form.description = ''
    form.client_id = ''
    form.expires_at = ''
    form.is_active = true
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
    form.expires_at = gallery.expires_at?.split('T')[0] || ''
    form.is_active = gallery.is_active
    isEditing.value = true
    editingId.value = gallery.id
    showFormModal.value = true
}

async function openGallery(gallery: AdminGallery) {
    selectedGallery.value = gallery
    galleryPhotos.value = []
    photoFilter.value = 'all'
    selectionMode.value = false
    selectedPhotos.value = []
    isLoadingPhotos.value = true
    showPhotosModal.value = true

    // Fetch gallery details with photos
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

async function fetchGalleries() {
    isLoadingGalleries.value = true
    try {
        const response = await adminApi.getGalleries()
        galleries.value = response.data
    } catch {
        // Silently fail
    } finally {
        isLoadingGalleries.value = false
    }
}

async function fetchClients() {
    try {
        const response = await adminApi.getClients(1, 100)
        clients.value = response.data
    } catch {
        // Silently fail
    }
}

function triggerFileInput() {
    fileInput.value?.click()
}

async function saveGallery() {
    isSaving.value = true
    try {
        // Filter out empty values
        const data: GalleryFormData = {
            title: form.title,
            description: form.description || '',
            client_id: form.client_id || '',
            expires_at: form.expires_at || '',
            is_active: form.is_active
        }

        if (isEditing.value && editingId.value) {
            await adminApi.updateGallery(editingId.value, data)
        } else {
            await adminApi.createGallery(data)
        }
        showFormModal.value = false
        await fetchGalleries()
    } catch (error) {
        console.error('Error saving gallery:', error)
        alert('Erreur lors de la sauvegarde de la galerie')
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
        alert('Erreur lors de la suppression de la galerie')
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
    } catch {
        // Handle error
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
    } catch {
        // Handle error
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
    } catch {
        // Handle error
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
        } catch {
            // Continue with next photo
        }
    }
}

async function removeAllDownloadable() {
    for (const photo of downloadablePhotos.value) {
        try {
            const response = await adminApi.togglePhotoDownloadable(photo.id)
            if (response.success) {
                photo.is_downloadable = response.data.is_downloadable
            }
        } catch {
            // Continue with next photo
        }
    }
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

function isSelected(photoId: string): boolean {
    return selectedPhotos.value.includes(photoId)
}

function selectAllFiltered() {
    selectedPhotos.value = filteredPhotos.value.map(p => p.id)
}

function clearSelection() {
    selectedPhotos.value = []
}

async function setSelectedDownloadable(downloadable: boolean) {
    const photosToUpdate = galleryPhotos.value.filter(
        p => selectedPhotos.value.includes(p.id) && p.is_downloadable !== downloadable
    )

    for (const photo of photosToUpdate) {
        try {
            const response = await adminApi.togglePhotoDownloadable(photo.id)
            if (response.success) {
                photo.is_downloadable = response.data.is_downloadable
            }
        } catch {
            // Continue with next photo
        }
    }

    // Exit selection mode after action
    selectionMode.value = false
    selectedPhotos.value = []
}

// Lightbox functions
function openLightbox(index: number) {
    lightboxIndex.value = index
    lightboxOpen.value = true
}

function closeLightbox() {
    lightboxOpen.value = false
}

onMounted(() => {
    fetchGalleries()
    fetchClients()
})
</script>
