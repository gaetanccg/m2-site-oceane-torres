<template>
    <div class="pt-20 min-h-screen bg-gray-50">
        <!-- Loading state -->
        <div v-if="isLoading" class="flex items-center justify-center py-32">
            <div class="animate-spin w-8 h-8 border-2 border-gold border-t-transparent rounded-full" />
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
            <section class="py-12 px-6 lg:px-12">
                <div class="max-w-7xl mx-auto">
                    <!-- Header -->
                    <div class="text-center mb-12">
                        <h1 class="text-4xl font-light mb-4">{{ gallery.title }}</h1>
                        <p v-if="gallery.description" class="text-gray-600 font-light max-w-2xl mx-auto">
                            {{ gallery.description }}
                        </p>
                        <p class="text-sm text-gray-400 mt-4">
                            {{ gallery.photos?.length || 0 }} photo(s)
                        </p>
                    </div>

                    <!-- Photos grid -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div
                            v-for="(photo, index) in gallery.photos"
                            :key="photo.id"
                            class="relative group cursor-pointer overflow-hidden rounded-lg aspect-square"
                            @click="openLightbox(index)"
                        >
                            <WatermarkOverlay>
                                <img
                                    :src="photo.file_path"
                                    :alt="photo.title || 'Photo'"
                                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                    loading="lazy"
                                />
                            </WatermarkOverlay>

                            <!-- Like button -->
                            <div class="absolute bottom-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                <LikeButton
                                    :photo-id="photo.id"
                                    :initial-likes-count="photo.likes_count || 0"
                                    @like="handleLike"
                                />
                            </div>

                            <!-- Likes indicator (always visible if > 0) -->
                            <div
                                v-if="photo.likes_count > 0"
                                class="absolute bottom-3 left-3 flex items-center gap-1 text-white text-sm bg-black/40 px-2 py-1 rounded-full"
                            >
                                <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                                </svg>
                                {{ photo.likes_count }}
                            </div>
                        </div>
                    </div>

                    <!-- Info box -->
                    <div class="mt-12 p-6 bg-white rounded-xl text-center">
                        <p class="text-gray-600 font-light">
                            Cliquez sur le coeur pour liker vos photos preferees.
                            <br class="hidden md:block" />
                            La photographe pourra voir vos coups de coeur !
                        </p>
                    </div>
                </div>
            </section>
        </template>

        <!-- Lightbox -->
        <Lightbox
            :images="lightboxImages"
            :is-open="lightboxOpen"
            :initial-index="lightboxIndex"
            :show-watermark="true"
            @close="lightboxOpen = false"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import WatermarkOverlay from '@/components/WatermarkOverlay.vue'
import LikeButton from '@/components/LikeButton.vue'
import Lightbox from '@/components/Lightbox.vue'
import { API_CONFIG } from '@/config/constants'
import type { LightboxImage } from '@/types'

interface Photo {
    id: string
    file_path: string
    title?: string
    likes_count: number
}

interface Gallery {
    id: string
    title: string
    description?: string
    photos: Photo[]
}

const route = useRoute()

const gallery = ref<Gallery | null>(null)
const isLoading = ref(true)
const error = ref('')
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)

const lightboxImages = computed<LightboxImage[]>(() => {
    if (!gallery.value?.photos) return []
    return gallery.value.photos.map(photo => ({
        url: photo.file_path,
        alt: photo.title || 'Photo',
        type: 'image' as const
    }))
})

const openLightbox = (index: number) => {
    lightboxIndex.value = index
    lightboxOpen.value = true
}

const handleLike = (photoId: string, newCount: number) => {
    if (!gallery.value?.photos) return
    const photo = gallery.value.photos.find(p => p.id === photoId)
    if (photo) {
        photo.likes_count = newCount
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
        } else {
            const data = await response.json()
            error.value = data.message || 'Galerie non trouvee'
        }
    } catch (err) {
        error.value = 'Erreur de connexion'
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchGallery)
</script>
