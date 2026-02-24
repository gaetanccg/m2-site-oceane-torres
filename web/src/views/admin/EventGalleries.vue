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
                    <Button @click="openCreateModal">{{ currentParent ? 'Creer une sous-galerie' : 'Creer une galerie' }}</Button>
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
                                    <h3 class="font-semibold text-gray-900 mb-1">{{ gallery.title }}</h3>
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
        <Modal v-model="showFormModal" :title="formModalTitle" size="md">
            <form @submit.prevent="saveGallery" class="space-y-4">
                <FormField
                    v-model="form.title"
                    :label="currentParent ? 'Nom de la sous-galerie' : 'Nom de l\'événement'"
                    required
                    :placeholder="currentParent ? 'Ex: Épreuve 1 - Shetlands' : 'Ex: Mariage Julie & Thomas'"
                />

                <div v-if="!currentParent">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
                    <select
                        v-model="form.event_category_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-gold focus:border-gold text-sm"
                    >
                        <option value="">Aucune catégorie</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                </div>

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

                <!-- Product Types Configuration -->
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Produits disponibles</h4>
                    <div class="space-y-3">
                        <div
                            v-for="pt in productTypesList"
                            :key="pt.key"
                            class="flex items-center gap-3 p-3 rounded-lg border transition-colors"
                            :class="pt.is_enabled ? 'border-gold/30 bg-gold/5' : 'border-gray-200 bg-gray-50'"
                        >
                            <label class="flex items-center gap-2 cursor-pointer flex-shrink-0">
                                <input
                                    type="checkbox"
                                    :checked="pt.is_enabled"
                                    @change="toggleProductType(pt.key)"
                                    class="w-4 h-4 text-gold border-gray-300 rounded focus:ring-gold"
                                />
                                <span class="text-sm font-medium" :class="pt.is_enabled ? 'text-gray-900' : 'text-gray-400'">
                                    {{ pt.label }}
                                </span>
                            </label>
                            <div class="flex items-center gap-1 ml-auto">
                                <input
                                    type="number"
                                    :value="pt.price"
                                    @input="updateProductPrice(pt.key, ($event.target as HTMLInputElement).value)"
                                    :disabled="!pt.is_enabled"
                                    step="0.01"
                                    min="0.01"
                                    class="w-20 px-2 py-1 text-sm text-right border rounded-md focus:ring-gold focus:border-gold disabled:opacity-40 disabled:bg-gray-100"
                                    :class="pt.is_enabled ? 'border-gray-300' : 'border-gray-200'"
                                />
                                <span class="text-sm text-gray-500">&euro;</span>
                            </div>
                        </div>
                    </div>
                    <p v-if="productTypesError" class="text-xs text-red-500 mt-2">{{ productTypesError }}</p>
                </div>
            </form>

            <template #footer>
                <Button variant="secondary" @click="showFormModal = false">Annuler</Button>
                <Button :loading="isSaving" @click="saveGallery">
                    {{ isEditing ? 'Enregistrer' : 'Creer' }}
                </Button>
            </template>
        </Modal>

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

        <!-- Photos Modal -->
        <Modal v-model="showPhotosModal" :title="selectedGallery?.title || 'Photos'" size="full">
            <div v-if="selectedGallery" class="space-y-4">
                <!-- Upload Zone (hidden on parent galleries) -->
                <div
                    v-if="!selectedGalleryIsParent"
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
                    </div>
                </div>

                <div v-if="!isLoadingPhotos && galleryPhotos.length === 0 && !selectedGalleryIsParent" class="py-12 text-center text-gray-500">
                    Aucune photo dans cette galerie
                </div>

                <!-- Parent gallery info -->
                <div v-if="selectedGalleryIsParent" class="py-12 text-center text-gray-500">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <p class="mb-2">Cette galerie est un conteneur de sous-galeries.</p>
                    <p class="text-sm">Les photos se trouvent dans les sous-galeries. Fermez cette modale et cliquez sur "Sous-galeries" pour y accéder.</p>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showPhotosModal = false">Fermer</Button>
            </template>
        </Modal>

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

        <!-- Single Photo Delete Confirmation Modal -->
        <Modal v-model="showDeletePhotoModal" title="Supprimer la photo" size="sm">
            <div class="space-y-4">
                <!-- Preview -->
                <div v-if="photoToDelete" class="flex justify-center">
                    <img
                        :src="photoToDelete.thumbnail_url || photoToDelete.preview_url || photoToDelete.display_url || photoToDelete.file_path"
                        :alt="photoToDelete.title || 'Photo'"
                        class="h-32 rounded-lg object-cover"
                    />
                </div>
                <p class="text-gray-600 text-center">
                    Etes-vous sur de vouloir supprimer cette photo ?
                    Cette action est irreversible.
                </p>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showDeletePhotoModal = false">Annuler</Button>
                <Button variant="danger" :loading="isDeletingPhoto" @click="deletePhoto">Supprimer</Button>
            </template>
        </Modal>

        <!-- Bulk Delete Confirmation Modal -->
        <Modal v-model="showBulkDeleteModal" title="Supprimer les photos selectionnées" size="sm">
            <div class="space-y-4">
                <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
                    <p class="font-medium flex items-center gap-1.5">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Cette action est irreversible
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
                        <div class="absolute bottom-1 left-1 px-1.5 py-0.5 bg-black/60 text-white text-[10px] rounded">
                            {{ photo._childTitle }}
                        </div>
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
import {useToast} from '@/composables/useToast'
import type {AdminGallery, AdminPhoto, EventGalleryFormData, EventCategory, ProductType} from '@/types/admin'

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
const isLoadingPhotos = ref(false)
const galleryPhotos = ref<AdminPhoto[]>([])
const showFormModal = ref(false)
const showPhotosModal = ref(false)
const showDeleteModal = ref(false)
const showBulkDeleteModal = ref(false)
const showCategoryModal = ref(false)
const showDeleteCategoryModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const isDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const editingId = ref<string | null>(null)
const selectedGallery = ref<EventGalleryWithCover | null>(null)
const galleryToDelete = ref<EventGalleryWithCover | null>(null)
const deleteConfirmInput = ref('')
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)
const selectionMode = ref(false)
const selectedPhotos = ref<string[]>([])
const isBulkProcessing = ref(false)
const bulkDeleteConfirmInput = ref('')
const showDeletePhotoModal = ref(false)
const photoToDelete = ref<AdminPhoto | null>(null)
const isDeletingPhoto = ref(false)

// Thumbnail picker for parent galleries
const showThumbnailPicker = ref(false)
const thumbnailPickerPhotos = ref<(AdminPhoto & {_childTitle: string})[]>([])
const isSettingThumbnail = ref(false)

// Category management
const isSavingCategory = ref(false)
const isDeletingCategory = ref(false)
const editingCategoryId = ref<string | null>(null)
const categoryToDelete = ref<EventCategory | null>(null)
const categoryForm = reactive({
    name: '',
    description: '',
})

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

const toast = useToast()

const form = reactive<EventGalleryFormData>({
    title: '',
    description: '',
    event_date: '',
    event_link: '',
    event_category_id: '',
    parent_id: null,
})

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

const bulkDeleteConfirmMatches = computed(() => {
    return bulkDeleteConfirmInput.value.trim() === String(selectedPhotos.value.length)
})

const formModalTitle = computed(() => {
    if (isEditing.value) {
        return currentParent.value ? 'Modifier la sous-galerie' : 'Modifier l\'événement'
    }
    return currentParent.value ? 'Nouvelle sous-galerie' : 'Nouvel événement'
})

// Product types configuration
const DEFAULT_PRODUCT_TYPES: Record<ProductType, {label: string; price: number}> = {
    digital: {label: 'Photo numerique', price: 13},
    print_10x15: {label: 'Impression 10x15', price: 10},
    print_15x20: {label: 'Impression 15x20', price: 15},
}

const productTypesState = ref<Record<ProductType, {is_enabled: boolean; price: number}>>({
    digital: {is_enabled: true, price: 13},
    print_10x15: {is_enabled: true, price: 10},
    print_15x20: {is_enabled: true, price: 15},
})

const productTypesError = ref('')

const productTypesList = computed(() => {
    return (Object.keys(DEFAULT_PRODUCT_TYPES) as ProductType[]).map(key => ({
        key,
        label: DEFAULT_PRODUCT_TYPES[key].label,
        is_enabled: productTypesState.value[key].is_enabled,
        price: productTypesState.value[key].price,
    }))
})

function toggleProductType(key: ProductType) {
    productTypesState.value[key].is_enabled = !productTypesState.value[key].is_enabled
    productTypesError.value = ''
}

function updateProductPrice(key: ProductType, value: string) {
    const num = parseFloat(value)
    if (!isNaN(num) && num > 0) {
        productTypesState.value[key].price = num
    }
}

function resetProductTypes() {
    productTypesState.value = {
        digital: {is_enabled: true, price: DEFAULT_PRODUCT_TYPES.digital.price},
        print_10x15: {is_enabled: true, price: DEFAULT_PRODUCT_TYPES.print_10x15.price},
        print_15x20: {is_enabled: true, price: DEFAULT_PRODUCT_TYPES.print_15x20.price},
    }
    productTypesError.value = ''
}

function loadProductTypesFromGallery(gallery: EventGalleryWithCover) {
    const configs = gallery.gallery_product_types
    if (!configs || configs.length === 0) {
        resetProductTypes()
        return
    }

    const state: Record<string, {is_enabled: boolean; price: number}> = {}
    for (const key of Object.keys(DEFAULT_PRODUCT_TYPES) as ProductType[]) {
        const config = configs.find(c => c.product_type === key)
        state[key] = {
            is_enabled: config ? config.is_enabled : false,
            price: config?.price !== null && config?.price !== undefined
                ? Number(config.price)
                : DEFAULT_PRODUCT_TYPES[key].price,
        }
    }
    productTypesState.value = state as Record<ProductType, {is_enabled: boolean; price: number}>
    productTypesError.value = ''
}

function buildProductTypesPayload() {
    return (Object.keys(productTypesState.value) as ProductType[]).map(key => ({
        product_type: key,
        is_enabled: productTypesState.value[key].is_enabled,
        price: productTypesState.value[key].price !== DEFAULT_PRODUCT_TYPES[key].price
            ? productTypesState.value[key].price
            : null,
    }))
}

const currentLightboxPhoto = computed(() => galleryPhotos.value[lightboxIndex.value] || null)

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {day: 'numeric', month: 'short', year: 'numeric'})
}

function resetForm() {
    form.title = ''
    form.description = ''
    form.event_date = ''
    form.event_link = ''
    form.event_category_id = ''
    form.parent_id = null
    resetProductTypes()
}

function openCreateModal() {
    resetForm()
    if (currentParent.value) {
        form.parent_id = currentParent.value.id
    }
    isEditing.value = false
    editingId.value = null
    showFormModal.value = true
}

function openEditModal(gallery: EventGalleryWithCover) {
    form.title = gallery.title
    form.description = gallery.description || ''
    form.event_date = gallery.event_date || ''
    form.event_link = gallery.event_link || ''
    form.event_category_id = gallery.event_category_id || ''
    loadProductTypesFromGallery(gallery)
    isEditing.value = true
    editingId.value = gallery.id
    showFormModal.value = true
}

async function openGallery(gallery: EventGalleryWithCover) {
    // If it's a parent gallery, drill-down instead of opening photos modal
    if ((gallery.children_count ?? 0) > 0) {
        enterDrillDown(gallery)
        return
    }

    selectedGallery.value = gallery
    galleryPhotos.value = []
    isLoadingPhotos.value = true
    showPhotosModal.value = true
    selectionMode.value = false
    selectedPhotos.value = []

    try {
        const response = await adminApi.getEventGallery(gallery.id) as { success: boolean; data: EventGalleryWithCover; is_parent?: boolean }
        if (response.success && response.data) {
            selectedGallery.value = response.data
            if (response.is_parent) {
                // Gallery became a parent since last fetch, update count
                selectedGallery.value.children_count = response.data.children?.length || 0
                galleryPhotos.value = []
            } else {
                galleryPhotos.value = response.data.photos || []
            }
        }
    } catch {
        toast.error('Erreur', 'Impossible de charger la galerie')
    } finally {
        isLoadingPhotos.value = false
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

function triggerFileInput() {
    fileInput.value?.click()
}

async function saveGallery() {
    const hasEnabled = Object.values(productTypesState.value).some(pt => pt.is_enabled)
    if (!hasEnabled) {
        productTypesError.value = 'Au moins un type de produit doit etre actif.'
        return
    }

    isSaving.value = true
    try {
        const payload = {
            ...form,
            event_category_id: form.event_category_id || undefined,
            parent_id: form.parent_id || undefined,
            product_types: buildProductTypesPayload(),
        }
        if (isEditing.value && editingId.value) {
            await adminApi.updateEventGallery(editingId.value, payload)
        } else {
            await adminApi.createEventGallery(payload)
        }
        showFormModal.value = false
        const label = currentParent.value ? 'Sous-galerie' : 'Événement'
        toast.success(isEditing.value ? `${label} modifié` : `${label} créé`)

        if (currentParent.value) {
            await fetchChildren(currentParent.value.id)
            // Also refresh parent to update children_count
            await fetchGalleries()
        } else {
            await fetchGalleries()
        }
    } catch {
        toast.error('Erreur', 'Impossible de sauvegarder')
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

function handleGalleryMainAction(gallery: EventGalleryWithCover) {
    // For galleries with no children: open photos
    // For galleries that are empty (no photos, no children): also allow drill-down entry to create sub-galleries
    openGallery(gallery)
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

        if (result.completed > 0) {
            await refreshGalleryPhotos()
        }
    } catch {
        toast.error('Erreur', 'Erreur lors de l\'upload des photos')
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
        toast.error('Erreur', 'Impossible de rafraîchir les photos')
    }
}

function handleCancelUpload() {
    cancelUpload()
    resetUpload()
}

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
        if (selectedGallery.value?.thumbnail_photo_id === photoId) {
            selectedGallery.value.thumbnail_photo_id = null
        }
        showDeletePhotoModal.value = false
        photoToDelete.value = null
        toast.success('Photo supprimée')
    } catch {
        toast.error('Erreur', 'Impossible de supprimer la photo')
    } finally {
        isDeletingPhoto.value = false
    }
}

async function setAsThumbnail(photoId: string) {
    if (!selectedGallery.value) return

    try {
        const newThumbnailId = selectedGallery.value.thumbnail_photo_id === photoId ? null : photoId
        const response = await adminApi.setEventThumbnail(selectedGallery.value.id, newThumbnailId)

        if (response.success) {
            selectedGallery.value.thumbnail_photo_id = newThumbnailId

            const galleryIndex = galleries.value.findIndex(g => g.id === selectedGallery.value?.id)
            if (galleryIndex !== -1) {
                galleries.value[galleryIndex].thumbnail_photo_id = newThumbnailId
                const thumbnailPhoto = newThumbnailId
                    ? galleryPhotos.value.find(p => p.id === newThumbnailId)
                    : galleryPhotos.value[0]
                if (thumbnailPhoto) {
                    galleries.value[galleryIndex].cover_photo = thumbnailPhoto
                }
            }
        }
    } catch {
        toast.error('Erreur', 'Impossible de modifier la miniature')
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
    bulkDeleteConfirmInput.value = ''
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
        toast.success('Photos supprimées')
        selectedPhotos.value = []
        selectionMode.value = false
        showBulkDeleteModal.value = false
    } catch {
        toast.error('Erreur', 'Impossible de supprimer les photos')
    } finally {
        isBulkProcessing.value = false
    }
}

onMounted(async () => {
    await fetchCategories()
    await fetchGalleries()
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
