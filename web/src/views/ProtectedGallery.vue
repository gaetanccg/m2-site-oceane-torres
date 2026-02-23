<template>
    <div class="pt-20 min-h-screen bg-cream">
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
                to="/gallery"
                class="inline-block px-6 py-3 bg-gold text-white rounded-lg hover:opacity-90 transition-colors"
            >
                Retour
            </router-link>
        </div>

        <!-- Gallery content -->
        <template v-else-if="gallery">
            <!-- Gallery Header -->
            <section class="py-8 sm:py-12 px-4 sm:px-6 lg:px-12 bg-white border-b border-gold">
                <div class="max-w-4xl mx-auto text-center">
                    <h1 class="text-3xl sm:text-4xl md:text-5xl font-light mb-4">{{ gallery.title }}</h1>
                    <p v-if="gallery.description" class="text-gray-600 font-light text-lg max-w-2xl mx-auto mb-2">
                        {{ gallery.description }}
                    </p>
                    <p class="text-sm text-gray-400">
                        {{ gallery.photos?.length || 0 }} photo(s)
                    </p>
                </div>
            </section>

            <!-- How it works -->
            <section class="py-8 sm:py-10 px-4 sm:px-6 lg:px-12 bg-gradient-to-b from-white to-cream">
                <div class="max-w-5xl mx-auto">
                    <h2 class="text-xl sm:text-2xl md:text-3xl font-light text-center mb-6 sm:mb-10 text-gray-800">
                        Comment fonctionne votre galerie ?
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 md:gap-8">
                        <!-- Step 1 -->
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                            <div class="w-10 h-10 bg-gold/10 rounded-full flex items-center justify-center mb-4">
                                <span class="text-gold font-semibold">1</span>
                            </div>
                            <h3 class="text-lg font-medium mb-3 text-gray-800">Parcourez votre galerie</h3>
                            <p class="text-gray-600 text-sm leading-relaxed">
                                Découvrez toutes vos photos et cliquez sur le cœur <span class="text-red-500">♥</span> pour marquer vos coups de cœur. Prenez votre temps, vous pouvez y revenir autant de fois que vous le souhaitez !
                            </p>
                        </div>

                        <!-- Step 2 -->
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                            <div class="w-10 h-10 bg-gold/10 rounded-full flex items-center justify-center mb-4">
                                <span class="text-gold font-semibold">2</span>
                            </div>
                            <h3 class="text-lg font-medium mb-3 text-gray-800">Ajoutez vos photos au panier</h3>
                            <p class="text-gray-600 text-sm leading-relaxed">
                                Choisissez les photos que vous souhaitez commander : Ajoutez-les à votre panier en un clic. Vous pouvez choisir entre des fichiers numériques haute résolution ou des tirages papier de qualité professionnelle.
                            </p>
                        </div>

                        <!-- Step 3 -->
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                            <div class="w-10 h-10 bg-gold/10 rounded-full flex items-center justify-center mb-4">
                                <span class="text-gold font-semibold">3</span>
                            </div>
                            <h3 class="text-lg font-medium mb-3 text-gray-800">Recevez vos photos</h3>
                            <p class="text-gray-600 text-sm leading-relaxed">
                                Après paiement, recevez immédiatement vos photos numériques par email. Pour les tirages papier, je vous contacterai pour organiser la livraison.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Photos Gallery -->
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
                                    <LikeButton
                                        :photo-id="photo.id"
                                        :liked="photo.is_liked"
                                        size="lg"
                                        show-label
                                        @like="handleLike"
                                    />
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
                </div>
            </section>
        </template>

        <!-- Lightbox (watermark intégré dans les images serveur) -->
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
import {ref, computed, onMounted} from 'vue'
import {useRoute} from 'vue-router'
import LikeButton from '@/components/LikeButton.vue'
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
    is_liked: boolean
}

interface AvailableProductType {
    label: string
    price: number
    is_print: boolean
    is_enabled: boolean
}

type ProductTypeKey = 'digital' | 'print_10x15' | 'print_15x20'

interface Gallery {
    id: string
    title: string
    description?: string
    photos: Photo[]
}

const route = useRoute()

const gallery = ref<Gallery | null>(null)
const availableProductTypes = ref<Record<ProductTypeKey, AvailableProductType> | null>(null)
const isLoading = ref(true)
const error = ref('')
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)

// Natural sort for photo titles (handles "photo 1", "photo 2", ..., "photo 10" correctly)
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

const handleLike = (photoId: string, isLiked: boolean) => {
    if (gallery.value?.photos) {
        const photo = gallery.value.photos.find(p => p.id === photoId)
        if (photo) {
            photo.is_liked = isLiked
        }
    }
}

const fetchGallery = async () => {
    const code = route.params.code as string
    if (!code) {
        error.value = 'Code manquant'
        isLoading.value = false
        return
    }

    try {
        const response = await fetch(`${API_CONFIG.baseUrl}/galleries/code/${code}`, {
            headers: {
                'Accept': 'application/json'
            }
        })

        if (response.ok) {
            const data = await response.json()
            gallery.value = data.gallery
            if (data.available_product_types) {
                availableProductTypes.value = data.available_product_types
            }
        } else {
            const data = await response.json()
            error.value = data.message || 'Galerie non trouvee'
        }
    } catch (_err) {
        error.value = 'Erreur de connexion'
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchGallery)
</script>
