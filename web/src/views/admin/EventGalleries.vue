<template>
    <div>
        <AdminHeader
            :title="currentParent ? currentParent.title : 'Galeries d\'événements'"
            :subtitle="currentParent ? 'Sous-galeries de cet événement' : 'Gerez les galeries publiques de vos événements'"
        >
            <template #actions>
                <Button v-if="currentParent" variant="secondary" @click="exitDrillDown">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Retour aux événements
                </Button>
                <Button v-if="currentParent" variant="secondary" @click="openThumbnailPicker">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                    Miniature
                </Button>
                <Button v-if="!currentParent" variant="secondary" @click="showCategoryModal = true">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    Categories
                </Button>
                <Button @click="openCreateModal">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ currentParent ? 'Nouvelle sous-galerie' : 'Nouvel événement' }}
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
                <div v-if="displayedGalleries.length === 0" class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-gray-500 mb-4">{{ currentParent ? 'Aucune sous-galerie' : 'Aucune galerie d\'événement' }}</p>
                    <Button @click="openCreateModal">{{ currentParent ? 'Créer une sous-galerie' : 'Créer une galerie' }}</Button>
                </div>

                <!-- Galleries grouped by category (top-level only) -->
                <div v-else-if="!currentParent" class="space-y-8">
                    <div v-for="section in galleriesByCategory" :key="section.categoryId || 'uncategorized'">
                        <!-- Section header -->
                        <div v-if="galleriesByCategory.length > 1 || section.categoryId" class="flex items-center gap-3 mb-4">
                            <h2 class="text-lg font-semibold text-gray-800">{{ section.categoryName }}</h2>
                            <span class="text-sm text-gray-400">({{ section.galleries.length }})</span>
                        </div>

                        <!-- Galleries grid for this section -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div
                                v-for="(gallery, galleryIndex) in section.galleries"
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
                                    <!-- Photos/Children count badge -->
                                    <div class="absolute bottom-2 right-2 px-2 py-1 bg-black/70 text-white text-xs rounded-lg">
                                        <template v-if="gallery.children_count && gallery.children_count > 0">
                                            {{ gallery.children_count }} sous-galerie(s)
                                        </template>
                                        <template v-else>
                                            {{ gallery.photos_count }} photo(s)
                                        </template>
                                    </div>
                                    <!-- Sort order arrows -->
                                    <div class="absolute top-2 left-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                            v-if="galleryIndex > 0"
                                            @click.stop="moveGallery(section, galleryIndex, -1)"
                                            class="p-1 bg-black/60 text-white rounded hover:bg-black/80"
                                            title="Monter"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                                        </button>
                                        <button
                                            v-if="galleryIndex < section.galleries.length - 1"
                                            @click.stop="moveGallery(section, galleryIndex, 1)"
                                            class="p-1 bg-black/60 text-white rounded hover:bg-black/80"
                                            title="Descendre"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Card Content -->
                                <div class="p-4">
                                    <div class="flex items-center gap-2 mb-1">
                                        <h3 class="font-semibold text-gray-900">{{ gallery.title }}</h3>
                                        <span
                                            v-if="gallery.is_published === false"
                                            class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600"
                                        >
                                            Brouillon
                                        </span>
                                    </div>
                                    <p v-if="gallery.description" class="text-sm text-gray-500 line-clamp-2 mb-2">
                                        {{ gallery.description }}
                                    </p>

                                    <div class="flex items-center gap-3 text-sm text-gray-400 mb-1">
                                        <span v-if="gallery.event_category" class="flex items-center gap-1 text-gold">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                            </svg>
                                            {{ gallery.event_category.name }}
                                        </span>
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
                                        <!-- Has children → only Sous-galeries button -->
                                        <button
                                            v-if="(gallery.children_count ?? 0) > 0"
                                            @click="enterDrillDown(gallery)"
                                            class="flex-1 px-3 py-2 text-sm font-medium text-gold bg-gold/10 rounded-lg hover:bg-gold/20 transition-colors flex items-center justify-center gap-1"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                            </svg>
                                            Sous-galeries ({{ gallery.children_count }})
                                        </button>
                                        <!-- Has photos → only Photos button -->
                                        <button
                                            v-else-if="gallery.photos_count > 0"
                                            @click="openGallery(gallery)"
                                            class="flex-1 px-3 py-2 text-sm font-medium text-gold bg-gold/10 rounded-lg hover:bg-gold/20 transition-colors flex items-center justify-center gap-1"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            Photos ({{ gallery.photos_count }})
                                        </button>
                                        <!-- Empty gallery → both buttons -->
                                        <template v-else>
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
                                                @click="enterDrillDown(gallery)"
                                                class="flex-1 px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors flex items-center justify-center gap-1"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                </svg>
                                                Sous-galeries
                                            </button>
                                        </template>
                                        <button
                                            @click="togglePublish(gallery)"
                                            :class="[
                                                'px-3 py-2 text-sm rounded-lg transition-colors',
                                                gallery.is_published !== false
                                                    ? 'text-orange-600 hover:text-orange-700 hover:bg-orange-50'
                                                    : 'text-green-600 hover:text-green-700 hover:bg-green-50'
                                            ]"
                                            :title="gallery.is_published !== false ? 'Depublier' : 'Publier'"
                                        >
                                            <svg v-if="gallery.is_published !== false" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                                            </svg>
                                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
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
                    </div>
                </div>

                <!-- Drill-down: children grid (flat, no category grouping) -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="gallery in displayedGalleries"
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

        <!-- Create/Edit Gallery Modal -->
        <EventGalleryFormModal
            v-model="showFormModal"
            :gallery="editingGallery"
            :categories="categories"
            :parent-id="currentParent?.id ?? null"
            @saved="onGallerySaved"
        />

        <!-- Category Management Modal -->
        <Modal v-model="showCategoryModal" title="Gérer les catégories" size="md">
            <div class="space-y-4">
                <!-- Add category form -->
                <div class="flex gap-2">
                    <input
                        v-model="categoryForm.name"
                        type="text"
                        placeholder="Nom de la catégorie"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-gold focus:border-gold text-sm"
                        @keyup.enter="saveCategory"
                    />
                    <Button size="sm" :loading="isSavingCategory" @click="saveCategory">
                        {{ editingCategoryId ? 'Modifier' : 'Ajouter' }}
                    </Button>
                    <Button v-if="editingCategoryId" variant="secondary" size="sm" @click="cancelEditCategory">
                        Annuler
                    </Button>
                </div>
                <input
                    v-model="categoryForm.description"
                    type="text"
                    placeholder="Description (optionnel)"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-gold focus:border-gold text-sm"
                />

                <!-- Categories list -->
                <div v-if="categories.length === 0" class="text-center py-6 text-gray-400 text-sm">
                    Aucune catégorie
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="(cat, catIndex) in categories"
                        :key="cat.id"
                        class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200"
                    >
                        <!-- Sort arrows -->
                        <div class="flex flex-col gap-0.5">
                            <button
                                :disabled="catIndex === 0"
                                @click="moveCategoryUp(catIndex)"
                                class="p-0.5 text-gray-400 hover:text-gray-700 disabled:opacity-30"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                            </button>
                            <button
                                :disabled="catIndex === categories.length - 1"
                                @click="moveCategoryDown(catIndex)"
                                class="p-0.5 text-gray-400 hover:text-gray-700 disabled:opacity-30"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                        </div>

                        <!-- Category info -->
                        <div class="flex-1 min-w-0">
                            <span class="font-medium text-sm text-gray-900">{{ cat.name }}</span>
                            <span v-if="cat.galleries_count" class="text-xs text-gray-400 ml-2">({{ cat.galleries_count }} galerie(s))</span>
                            <p v-if="cat.description" class="text-xs text-gray-500 truncate">{{ cat.description }}</p>
                        </div>

                        <!-- Actions -->
                        <button @click="startEditCategory(cat)" class="p-1.5 text-gray-400 hover:text-gray-700 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </button>
                        <button @click="deleteCategoryConfirm(cat)" class="p-1.5 text-red-400 hover:text-red-600 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showCategoryModal = false">Fermer</Button>
            </template>
        </Modal>

        <!-- Photos Manager -->
        <PhotosManager
            v-model="showPhotosModal"
            :gallery-id="selectedGallery?.id || null"
            :gallery-title="selectedGallery?.title || 'Photos'"
            :is-event-gallery="true"
            :hide-upload="selectedGalleryIsParent"
            :thumbnail-photo-id="selectedGallery?.thumbnail_photo_id || null"
            @photos-changed="fetchGalleries"
            @thumbnail-changed="handleThumbnailChanged"
        />

        <!-- Delete Gallery Confirmation Modal -->
        <Modal v-model="showDeleteModal" title="Confirmer la suppression" size="sm">
            <div class="space-y-4">
                <p class="text-gray-600">
                    Etes-vous sur de vouloir supprimer la galerie <strong>{{ galleryToDelete?.title }}</strong> ?
                </p>

                <!-- Detailed impact warning -->
                <div
                    v-if="galleryToDeleteHasContent"
                    class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1"
                >
                    <p class="font-medium flex items-center gap-1.5">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Cette action est irreversible
                    </p>
                    <ul class="list-disc list-inside ml-5 space-y-0.5">
                        <li v-if="galleryToDelete?.children_count && galleryToDelete.children_count > 0">
                            {{ galleryToDelete.children_count }} sous-galerie(s) seront supprimées
                        </li>
                        <li v-if="galleryToDelete?.photos_count && galleryToDelete.photos_count > 0">
                            {{ galleryToDelete.photos_count }} photo(s) seront supprimées
                        </li>
                        <li v-if="galleryToDelete?.children_count && galleryToDelete.children_count > 0">
                            Toutes les photos des sous-galeries seront supprimées
                        </li>
                    </ul>
                </div>

                <!-- Type gallery name to confirm -->
                <div v-if="galleryToDeleteHasContent">
                    <label class="block text-sm text-gray-600 mb-1.5">
                        Tapez <strong class="text-gray-900 select-all">{{ galleryToDelete?.title }}</strong> pour confirmer :
                    </label>
                    <input
                        v-model="deleteConfirmInput"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-red-500 focus:border-red-500"
                        placeholder="Nom de la galerie"
                        @keyup.enter="deleteConfirmMatches && deleteGallery()"
                    />
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showDeleteModal = false">Annuler</Button>
                <Button
                    variant="danger"
                    :loading="isDeleting"
                    :disabled="galleryToDeleteHasContent && !deleteConfirmMatches"
                    @click="deleteGallery"
                >
                    Supprimer
                </Button>
            </template>
        </Modal>

        <!-- Delete Category Confirmation Modal -->
        <Modal v-model="showDeleteCategoryModal" title="Supprimer la catégorie" size="sm">
            <p class="text-gray-600">
                Etes-vous sur de vouloir supprimer la catégorie <strong>{{ categoryToDelete?.name }}</strong> ?
                Les galeries associées ne seront pas supprimées, elles perdront simplement leur catégorie.
            </p>

            <template #footer>
                <Button variant="secondary" @click="showDeleteCategoryModal = false">Annuler</Button>
                <Button variant="danger" :loading="isDeletingCategory" @click="deleteCategory">Supprimer</Button>
            </template>
        </Modal>

        <!-- Thumbnail Picker Modal (for parent galleries) -->
        <Modal v-model="showThumbnailPicker" title="Choisir la miniature" size="lg">
            <div v-if="thumbnailPickerPhotos.length === 0" class="py-12 text-center text-gray-500">
                <p>Aucune photo disponible dans les sous-galeries.</p>
                <p class="text-sm mt-2">Ajoutez d'abord des photos dans les sous-galeries.</p>
            </div>
            <div v-else>
                <p class="text-sm text-gray-500 mb-4">Cliquez sur une photo pour la définir comme miniature de l'événement parent.</p>
                <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    <div
                        v-for="photo in thumbnailPickerPhotos"
                        :key="photo.id"
                        class="relative aspect-square rounded-lg overflow-hidden bg-gray-100 cursor-pointer group"
                        :class="currentParent?.thumbnail_photo_id === photo.id ? 'ring-3 ring-gold' : ''"
                        @click="setParentThumbnail(photo.id)"
                    >
                        <img
                            :src="photo.thumbnail_url || photo.preview_url || photo.display_url || photo.file_path"
                            :alt="photo.title || 'Photo'"
                            class="w-full h-full object-cover"
                        />
                        <div v-if="currentParent?.thumbnail_photo_id === photo.id" class="absolute top-2 right-2 px-2 py-1 bg-gold text-white text-xs font-medium rounded-lg flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                            </svg>
                            Actuelle
                        </div>
                        <div v-else class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
                            <span class="px-3 py-1.5 bg-white/90 text-gray-800 text-xs font-medium rounded-lg">Choisir</span>
                        </div>
                        <button
                            @click.stop="thumbnailPreviewUrl = photo.preview_url || photo.display_url || photo.file_path || null"
                            class="absolute bottom-1 right-1 p-1 bg-black/60 text-white rounded hover:bg-black/80 transition-colors opacity-0 group-hover:opacity-100"
                            title="Voir en grand"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button
                    v-if="currentParent?.thumbnail_photo_id"
                    variant="secondary"
                    :loading="isSettingThumbnail"
                    @click="setParentThumbnail(null)"
                >
                    Retirer la miniature
                </Button>
                <Button variant="secondary" @click="showThumbnailPicker = false">Fermer</Button>
            </template>
        </Modal>

        <!-- Thumbnail full-size preview -->
        <Teleport to="body">
            <div
                v-if="thumbnailPreviewUrl"
                class="fixed inset-0 z-[110] bg-black/95 flex items-center justify-center"
                @click="thumbnailPreviewUrl = null"
            >
                <button @click="thumbnailPreviewUrl = null" class="absolute top-4 right-4 p-2 text-white/70 hover:text-white z-10">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <img
                    :src="thumbnailPreviewUrl"
                    alt="Preview"
                    class="max-h-[90vh] max-w-[90vw] object-contain"
                    @click.stop
                />
            </div>
        </Teleport>
</div>
</template>

<script setup lang="ts">
import {ref, reactive, computed, onMounted} from 'vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import EventGalleryFormModal from '@/components/admin/EventGalleryFormModal.vue'
import PhotosManager from '@/components/admin/PhotosManager.vue'
import {adminApi} from '@/services/adminApi'
import {useToast} from '@/composables/useToast'
import type {AdminGallery, AdminPhoto, EventGalleryFormData, EventCategory} from '@/types/admin'

interface EventGalleryWithCover extends AdminGallery {
    cover_photo?: AdminPhoto
    thumbnail_photo_id?: string | null
}

interface GallerySection {
    categoryId: string | null
    categoryName: string
    galleries: EventGalleryWithCover[]
}

const galleries = ref<EventGalleryWithCover[]>([])
const childGalleries = ref<EventGalleryWithCover[]>([])
const currentParent = ref<EventGalleryWithCover | null>(null)
const categories = ref<EventCategory[]>([])
const isLoading = ref(true)
const showFormModal = ref(false)
const showPhotosModal = ref(false)
const showDeleteModal = ref(false)
const showCategoryModal = ref(false)
const showDeleteCategoryModal = ref(false)
const isDeleting = ref(false)
const editingGallery = ref<EventGalleryWithCover | null>(null)
const selectedGallery = ref<EventGalleryWithCover | null>(null)
const galleryToDelete = ref<EventGalleryWithCover | null>(null)
const deleteConfirmInput = ref('')

// Thumbnail picker for parent galleries
const showThumbnailPicker = ref(false)
const thumbnailPickerPhotos = ref<(AdminPhoto & {_childTitle: string})[]>([])
const isSettingThumbnail = ref(false)
const thumbnailPreviewUrl = ref<string | null>(null)

// Category management
const isSavingCategory = ref(false)
const isDeletingCategory = ref(false)
const editingCategoryId = ref<string | null>(null)
const categoryToDelete = ref<EventCategory | null>(null)
const categoryForm = reactive({
    name: '',
    description: '',
})

const toast = useToast()

// Galleries grouped by category
const galleriesByCategory = computed<GallerySection[]>(() => {
    const sections: GallerySection[] = []
    const categorized = new Map<string, EventGalleryWithCover[]>()
    const uncategorized: EventGalleryWithCover[] = []

    for (const gallery of galleries.value) {
        if (gallery.event_category_id) {
            const existing = categorized.get(gallery.event_category_id)
            if (existing) {
                existing.push(gallery)
            } else {
                categorized.set(gallery.event_category_id, [gallery])
            }
        } else {
            uncategorized.push(gallery)
        }
    }

    // Add categorized sections in category sort order
    for (const cat of categories.value) {
        const catGalleries = categorized.get(cat.id)
        if (catGalleries && catGalleries.length > 0) {
            // Sort galleries within category by sort_order
            catGalleries.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
            sections.push({
                categoryId: cat.id,
                categoryName: cat.name,
                galleries: catGalleries,
            })
        }
    }

    // Add uncategorized at the end
    if (uncategorized.length > 0) {
        uncategorized.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
        sections.push({
            categoryId: null,
            categoryName: sections.length > 0 ? 'Autres' : '',
            galleries: uncategorized,
        })
    }

    return sections
})

// Displayed galleries: either top-level (from galleriesByCategory) or children in drill-down
const displayedGalleries = computed<EventGalleryWithCover[]>(() => {
    if (currentParent.value) return childGalleries.value
    return galleries.value
})

const selectedGalleryIsParent = computed(() => {
    if (!selectedGallery.value) return false
    return (selectedGallery.value.children_count ?? 0) > 0
})

const galleryToDeleteHasContent = computed(() => {
    if (!galleryToDelete.value) return false
    return (galleryToDelete.value.photos_count > 0) || ((galleryToDelete.value.children_count ?? 0) > 0)
})

const deleteConfirmMatches = computed(() => {
    if (!galleryToDelete.value) return false
    return deleteConfirmInput.value.trim() === galleryToDelete.value.title.trim()
})

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {day: 'numeric', month: 'short', year: 'numeric'})
}

function openCreateModal() {
    editingGallery.value = null
    showFormModal.value = true
}

function openEditModal(gallery: EventGalleryWithCover) {
    editingGallery.value = gallery
    showFormModal.value = true
}

function openGallery(gallery: EventGalleryWithCover) {
    // If it's a parent gallery, drill-down instead of opening photos modal
    if ((gallery.children_count ?? 0) > 0) {
        enterDrillDown(gallery)
        return
    }

    selectedGallery.value = gallery
    showPhotosModal.value = true
}

function handleThumbnailChanged(photoId: string | null) {
    if (!selectedGallery.value) return
    selectedGallery.value.thumbnail_photo_id = photoId

    // Update gallery in list
    const galleryIndex = galleries.value.findIndex(g => g.id === selectedGallery.value?.id)
    if (galleryIndex !== -1) {
        galleries.value[galleryIndex].thumbnail_photo_id = photoId
    }
}

async function togglePublish(gallery: EventGalleryWithCover) {
    const newStatus = gallery.is_published === false ? true : false
    try {
        await adminApi.updateEventGallery(gallery.id, { is_published: newStatus } as EventGalleryFormData)
        gallery.is_published = newStatus
        toast.success('Succes', newStatus ? 'Evenement publie' : 'Evenement depublie')
    } catch {
        toast.error('Erreur', 'Impossible de modifier le statut')
    }
}

function confirmDelete(gallery: EventGalleryWithCover) {
    galleryToDelete.value = gallery
    deleteConfirmInput.value = ''
    showDeleteModal.value = true
}

async function fetchGalleries() {
    isLoading.value = true
    try {
        const response = await adminApi.getEventGalleries()
        galleries.value = response.data as EventGalleryWithCover[]
    } catch {
        toast.error('Erreur', 'Impossible de charger les galeries')
    } finally {
        isLoading.value = false
    }
}

async function fetchCategories() {
    try {
        const response = await adminApi.getEventCategories()
        if (response.success) {
            categories.value = response.data
        }
    } catch {
        toast.error('Erreur', 'Impossible de charger les catégories')
    }
}

async function onGallerySaved() {
    if (currentParent.value) {
        await fetchChildren(currentParent.value.id)
        await fetchGalleries()
    } else {
        await fetchGalleries()
    }
}

async function deleteGallery() {
    if (!galleryToDelete.value) return
    isDeleting.value = true
    try {
        await adminApi.deleteEventGallery(galleryToDelete.value.id)
        showDeleteModal.value = false
        galleryToDelete.value = null
        toast.success('Galerie supprimée')

        if (currentParent.value) {
            await fetchChildren(currentParent.value.id)
            await fetchGalleries()
        } else {
            await fetchGalleries()
        }
    } catch {
        toast.error('Erreur', 'Impossible de supprimer la galerie')
    } finally {
        isDeleting.value = false
    }
}

// Gallery sort order
async function moveGallery(section: GallerySection, index: number, direction: number) {
    const newIndex = index + direction
    if (newIndex < 0 || newIndex >= section.galleries.length) return

    const galleryList = [...section.galleries]
    const [moved] = galleryList.splice(index, 1)
    galleryList.splice(newIndex, 0, moved)

    // Update sort_order for both galleries
    const updates = galleryList.map((g, i) => ({id: g.id, sort_order: i}))
    for (const u of updates) {
        const g = galleries.value.find(g => g.id === u.id)
        if (g) g.sort_order = u.sort_order
    }

    // Persist the two swapped galleries
    try {
        const a = updates[index]
        const b = updates[newIndex]
        await Promise.all([
            adminApi.updateEventGallery(a.id, {sort_order: a.sort_order} as EventGalleryFormData),
            adminApi.updateEventGallery(b.id, {sort_order: b.sort_order} as EventGalleryFormData),
        ])
    } catch {
        toast.error('Erreur', 'Impossible de modifier l\'ordre')
        await fetchGalleries()
    }
}

// Drill-down navigation
async function enterDrillDown(gallery: EventGalleryWithCover) {
    currentParent.value = gallery
    childGalleries.value = []
    isLoading.value = true
    await fetchChildren(gallery.id)
    isLoading.value = false
}

function exitDrillDown() {
    currentParent.value = null
    childGalleries.value = []
}

async function fetchChildren(parentId: string) {
    try {
        const response = await adminApi.getEventGalleryChildren(parentId)
        if (response.success) {
            childGalleries.value = (response.data || []) as EventGalleryWithCover[]
        }
    } catch {
        toast.error('Erreur', 'Impossible de charger les sous-galeries')
    }
}

// Thumbnail picker for parent galleries
async function openThumbnailPicker() {
    if (!currentParent.value) return

    // Collect all photos from children
    const allPhotos: (AdminPhoto & {_childTitle: string})[] = []
    for (const child of childGalleries.value) {
        // Fetch full child data if photos not loaded
        try {
            const response = await adminApi.getEventGallery(child.id) as { success: boolean; data: EventGalleryWithCover }
            if (response.success && response.data?.photos) {
                for (const photo of response.data.photos) {
                    allPhotos.push({...photo, _childTitle: child.title})
                }
            }
        } catch {
            // Skip children we can't load
        }
    }

    thumbnailPickerPhotos.value = allPhotos
    showThumbnailPicker.value = true
}

async function setParentThumbnail(photoId: string | null) {
    if (!currentParent.value) return
    isSettingThumbnail.value = true

    try {
        const response = await adminApi.setEventThumbnail(currentParent.value.id, photoId)
        if (response.success) {
            currentParent.value.thumbnail_photo_id = photoId

            // Update in top-level galleries list
            const idx = galleries.value.findIndex(g => g.id === currentParent.value?.id)
            if (idx !== -1) {
                galleries.value[idx].thumbnail_photo_id = photoId
                if (photoId) {
                    const photo = thumbnailPickerPhotos.value.find(p => p.id === photoId)
                    if (photo) galleries.value[idx].cover_photo = photo
                } else {
                    galleries.value[idx].cover_photo = undefined
                }
            }

            toast.success(photoId ? 'Miniature définie' : 'Miniature retirée')
            if (!photoId) showThumbnailPicker.value = false
        }
    } catch {
        toast.error('Erreur', 'Impossible de modifier la miniature')
    } finally {
        isSettingThumbnail.value = false
    }
}

// Category management
async function saveCategory() {
    if (!categoryForm.name.trim()) return
    isSavingCategory.value = true
    try {
        if (editingCategoryId.value) {
            await adminApi.updateEventCategory(editingCategoryId.value, {
                name: categoryForm.name,
                description: categoryForm.description || undefined,
            })
            toast.success('Catégorie modifiée')
        } else {
            await adminApi.createEventCategory({
                name: categoryForm.name,
                description: categoryForm.description || undefined,
            })
            toast.success('Catégorie créée')
        }
        categoryForm.name = ''
        categoryForm.description = ''
        editingCategoryId.value = null
        await fetchCategories()
    } catch {
        toast.error('Erreur', 'Impossible de sauvegarder la catégorie')
    } finally {
        isSavingCategory.value = false
    }
}

function startEditCategory(cat: EventCategory) {
    editingCategoryId.value = cat.id
    categoryForm.name = cat.name
    categoryForm.description = cat.description || ''
}

function cancelEditCategory() {
    editingCategoryId.value = null
    categoryForm.name = ''
    categoryForm.description = ''
}

function deleteCategoryConfirm(cat: EventCategory) {
    categoryToDelete.value = cat
    showDeleteCategoryModal.value = true
}

async function deleteCategory() {
    if (!categoryToDelete.value) return
    isDeletingCategory.value = true
    try {
        await adminApi.deleteEventCategory(categoryToDelete.value.id)
        showDeleteCategoryModal.value = false
        categoryToDelete.value = null
        toast.success('Catégorie supprimée')
        await Promise.all([fetchCategories(), fetchGalleries()])
    } catch {
        toast.error('Erreur', 'Impossible de supprimer la catégorie')
    } finally {
        isDeletingCategory.value = false
    }
}

async function moveCategoryUp(index: number) {
    if (index <= 0) return
    await swapCategoryOrder(index, index - 1)
}

async function moveCategoryDown(index: number) {
    if (index >= categories.value.length - 1) return
    await swapCategoryOrder(index, index + 1)
}

async function swapCategoryOrder(fromIndex: number, toIndex: number) {
    const list = [...categories.value]
    const [moved] = list.splice(fromIndex, 1)
    list.splice(toIndex, 0, moved)

    // Update local state immediately for responsive UI
    categories.value = list

    // Persist
    try {
        const reorderPayload = list.map((cat, i) => ({id: cat.id, sort_order: i}))
        await adminApi.reorderEventCategories(reorderPayload)
    } catch {
        toast.error('Erreur', 'Impossible de modifier l\'ordre')
        await fetchCategories()
    }
}

onMounted(async () => {
    await fetchCategories()
    await fetchGalleries()
})
</script>

<style scoped>

.line-clamp-2{
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
