<template>
    <div class="pt-20 min-h-screen bg-gray-50">
        <!-- Loading state -->
        <div v-if="isLoading" class="flex items-center justify-center py-32">
            <div class="flex flex-col items-center gap-3">
                <svg class="animate-spin h-10 w-10 text-gold" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-gray-500 font-light">Chargement de la galerie...</span>
            </div>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="text-center py-32 px-6">
            <h2 class="text-2xl font-light mb-4">{{ error }}</h2>
            <router-link
                to="/evenements"
                class="inline-block px-6 py-3 bg-gold text-white rounded-lg hover:opacity-90 transition-colors"
            >
                Retour aux événements
            </router-link>
        </div>

        <!-- Gallery content -->
        <template v-else-if="gallery">
            <!-- Header -->
            <section class="py-8 sm:py-12 px-4 sm:px-6 bg-white border-b border-gray-100">
                <div class="max-w-5xl mx-auto text-center">
                    <!-- Breadcrumb -->
                    <div class="flex items-center justify-center gap-2 text-sm text-gray-500 mb-4 sm:mb-6">
                        <router-link
                            to="/evenements"
                            class="hover:text-gold transition-colors"
                        >
                            Événements
                        </router-link>
                        <template v-if="gallery.parent">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <router-link
                                :to="{ name: 'event-gallery', params: { id: gallery.parent.id } }"
                                class="hover:text-gold transition-colors"
                            >
                                {{ gallery.parent.title }}
                            </router-link>
                        </template>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span class="text-gray-800 font-medium">{{ gallery.title }}</span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl md:text-4xl font-light mb-4 text-center">{{ gallery.title }}</h1>
                    <p v-if="gallery.description" class="text-gray-600 font-light max-w-2xl mx-auto mb-4">
                        {{ gallery.description }}
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-4 text-sm text-gray-400">
                        <span v-if="gallery.event_date" class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ formatDate(gallery.event_date) }}
                        </span>
                        <a
                            v-if="gallery.event_link"
                            :href="gallery.event_link"
                            target="_blank"
                            class="flex items-center gap-1 text-gold hover:text-gold/80 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Site de l'événement
                        </a>
                        <span v-if="!isParent" class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ gallery.photos?.length || 0 }} photo(s)
                        </span>
                        <span v-else class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            {{ gallery.children?.length || 0 }} sous-galerie(s)
                        </span>
                    </div>
                </div>
            </section>

            <!-- Parent mode: children grid -->
            <section v-if="isParent" class="py-8 sm:py-12 px-4 sm:px-6 lg:px-12">
                <div class="max-w-7xl mx-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
                        <router-link
                            v-for="child in gallery.children"
                            :key="child.id"
                            :to="{ name: 'event-gallery', params: { id: child.id } }"
                            class="group"
                        >
                            <div class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300">
                                <!-- Cover Image -->
                                <div class="aspect-[4/3] bg-gray-100 relative overflow-hidden">
                                    <img
                                        v-if="child.cover_photo"
                                        :src="child.cover_photo.thumbnail_url || child.cover_photo.preview_url || child.cover_photo.display_url || child.cover_photo.file_path"
                                        :alt="child.title"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                        loading="lazy"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <!-- Photos count -->
                                    <div class="absolute bottom-3 right-3 px-3 py-1.5 bg-black/70 text-white text-sm rounded-full">
                                        {{ child.photos_count }} photo(s)
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="p-5">
                                    <h3 class="text-xl font-medium text-gray-900 mb-2 group-hover:text-gold transition-colors">
                                        {{ child.title }}
                                    </h3>
                                    <p v-if="child.description" class="text-gray-500 font-light line-clamp-2 text-sm">
                                        {{ child.description }}
                                    </p>

                                    <div class="flex items-center justify-end mt-4 pt-4 border-t border-gray-100">
                                        <span class="text-gold font-medium text-sm flex items-center gap-1 group-hover:gap-2 transition-all">
                                            Voir les photos
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </router-link>
                    </div>

                    <!-- Empty state -->
                    <div v-if="!gallery.children?.length" class="text-center py-16">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <p class="text-gray-500 font-light">Les sous-galeries seront bientot disponibles.</p>
                    </div>
                </div>
            </section>

            <!-- Leaf mode: photos grid -->
            <template v-else>
                <section class="py-8 sm:py-12 px-4 sm:px-6 lg:px-12">
                    <div class="max-w-7xl mx-auto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                            <div
                                v-for="(photo, index) in sortedPhotos"
                                :key="photo.id"
                            >
                                <PhotoCard
                                    :src="photo.preview_url || photo.display_url || photo.file_path"
                                    :thumbnail-url="photo.thumbnail_url"
                                    :alt="photo.title || 'Photo'"
                                    @click="openLightbox(index)"
                                >
                                    <template #actions>
                                        <AddToCartButton
                                            :photo-id="photo.id"
                                            size="md"
                                            show-label
                                            :available-product-types="availableProductTypes"
                                        />
                                    </template>
                                </PhotoCard>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-if="!sortedPhotos.length" class="text-center py-16">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="text-gray-500 font-light">Aucune photo disponible pour le moment.</p>
                        </div>
                    </div>
                </section>

                <!-- Purchase CTA -->
                <section v-if="sortedPhotos.length > 0" class="py-12 px-6 bg-cream">
                    <div class="max-w-3xl mx-auto text-center">
                        <h2 class="text-2xl font-light mb-3">Vous vous reconnaissez ?</h2>
                        <p class="text-gray-600 font-light mb-6">
                            Ajoutez les photos qui vous plaisent au panier et commandez-les en haute qualité.
                        </p>
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                            <router-link
                                to="/panier"
                                class="inline-flex items-center gap-2 px-6 py-3 bg-gold text-white rounded-lg font-medium hover:opacity-90 transition-opacity"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Voir mon panier
                            </router-link>
                            <router-link
                                to="/contact"
                                class="inline-flex items-center gap-2 px-6 py-3 border-2 border-gold text-gold rounded-lg font-medium hover:bg-gold hover:text-white transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                Me contacter
                            </router-link>
                        </div>
                    </div>
                </section>
            </template>
        </template>

        <!-- Lightbox (watermark intégré côté serveur) -->
        <Lightbox
            :images="lightboxImages"
            :is-open="lightboxOpen"
            :initial-index="lightboxIndex"
            :show-watermark="false"
            @close="lightboxOpen = false"
        />
    </div>
</template>

<script setup lang="ts">
import {ref, computed, watch} from 'vue'
import {useRoute} from 'vue-router'
import Lightbox from '@/components/Lightbox.vue'
import PhotoCard from '@/components/PhotoCard.vue'
import AddToCartButton from '@/components/cart/AddToCartButton.vue'
import {API_CONFIG} from '@/config/constants'
import type {LightboxImage} from '@/types'

interface Photo {
    id: string
    file_path: string
    display_url?: string
    preview_url?: string
    thumbnail_url?: string
    title?: string
}

interface AvailableProductType {
    label: string
    price: number
    is_print: boolean
    is_enabled: boolean
}

interface ChildGallery {
    id: string
    title: string
    description?: string
    photos_count: number
    cover_photo?: Photo
}

interface ParentGallery {
    id: string
    title: string
}

interface Gallery {
    id: string
    title: string
    description?: string
    event_date?: string
    event_link?: string
    photos?: Photo[]
    children?: ChildGallery[]
    parent?: ParentGallery | null
}

type ProductTypeKey = 'digital' | 'print_10x15' | 'print_15x20'

const route = useRoute()

const gallery = ref<Gallery | null>(null)
const isParent = ref(false)
const availableProductTypes = ref<Record<ProductTypeKey, AvailableProductType> | null>(null)
const isLoading = ref(true)
const error = ref('')
const lightboxOpen = ref(false)

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    })
}

const lightboxIndex = ref(0)

// Natural sort for photo titles (handles "shetland 1", "shetland 2", ..., "shetland 10" correctly)
const sortedPhotos = computed<Photo[]>(() => {
    if (!gallery.value?.photos) return []
    return [...gallery.value.photos].sort((a, b) => {
        const titleA = a.title || ''
        const titleB = b.title || ''
        return titleA.localeCompare(titleB, 'fr', {numeric: true, sensitivity: 'base'})
    })
})

const lightboxImages = computed<LightboxImage[]>(() => {
    if (!sortedPhotos.value.length) return []
    return sortedPhotos.value.map(photo => ({
        url: photo.preview_url || photo.display_url || photo.file_path,
        alt: photo.title || 'Photo',
        type: 'image' as const
    }))
})

const openLightbox = (index: number) => {
    lightboxIndex.value = index
    lightboxOpen.value = true
}

const fetchGallery = async (id: string) => {
    if (!id) {
        error.value = 'Galerie non trouvée'
        isLoading.value = false
        return
    }

    // Reset state for new gallery
    gallery.value = null
    isParent.value = false
    availableProductTypes.value = null
    error.value = ''
    isLoading.value = true
    lightboxOpen.value = false

    try {
        const response = await fetch(`${API_CONFIG.baseUrl}/events/${id}`, {
            headers: {
                'Accept': 'application/json'
            }
        })

        if (response.ok) {
            const data = await response.json()
            gallery.value = data.gallery
            isParent.value = data.is_parent === true
            if (data.available_product_types) {
                availableProductTypes.value = data.available_product_types
            }
        } else {
            const data = await response.json()
            error.value = data.message || 'Galerie non trouvée'
        }
    } catch (_err) {
        error.value = 'Erreur de connexion'
    } finally {
        isLoading.value = false
    }
}

watch(
    () => route.params.id as string,
    (newId) => {
        if (newId) fetchGallery(newId)
    },
    {immediate: true}
)
</script>

<style scoped>
.line-clamp-2{
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
