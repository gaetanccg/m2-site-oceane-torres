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
            <section class="py-12 px-6 bg-white border-b border-gray-100">
                <div class="max-w-5xl mx-auto">
                    <router-link
                        to="/evenements"
                        class="inline-flex items-center gap-2 text-gray-500 hover:text-gold transition-colors mb-6"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Retour aux événements
                    </router-link>

                    <h1 class="text-4xl font-light mb-4">{{ gallery.title }}</h1>
                    <p v-if="gallery.description" class="text-gray-600 font-light max-w-2xl mb-4">
                        {{ gallery.description }}
                    </p>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-400">
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
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ gallery.photos?.length || 0 }} photo(s)
                        </span>
                    </div>
                </div>
            </section>

            <!-- Photos grid (masonry-style) -->
            <section class="py-12 px-6 lg:px-12">
                <div class="max-w-7xl mx-auto">
                    <div class="columns-1 sm:columns-2 lg:columns-3 xl:columns-4 gap-6 space-y-6">
                        <div
                            v-for="(photo, index) in gallery.photos"
                            :key="photo.id"
                            class="break-inside-avoid"
                        >
                            <div
                                class="relative group bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow cursor-pointer"
                                @click="openLightbox(index)"
                            >
                                <img
                                    :src="photo.display_url || photo.file_path"
                                    :alt="photo.title || 'Photo'"
                                    class="w-full h-auto"
                                    loading="lazy"
                                />
                                <!-- Hover overlay -->
                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity p-3 bg-white/90 rounded-full">
                                        <svg class="w-6 h-6 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="!gallery.photos?.length" class="text-center py-16">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-gray-500 font-light">Aucune photo disponible pour le moment.</p>
                    </div>
                </div>
            </section>

            <!-- Contact CTA -->
            <section class="py-12 px-6 bg-cream">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-light mb-3">Vous vous reconnaissez ?</h2>
                    <p class="text-gray-600 font-light mb-6">
                        Contactez-moi pour obtenir vos photos personnelles de cet événement.
                    </p>
                    <router-link
                        to="/contact"
                        class="inline-flex items-center gap-2 px-6 py-3 bg-gold text-white rounded-lg font-medium hover:opacity-90 transition-opacity"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Me contacter
                    </router-link>
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
import Lightbox from '@/components/Lightbox.vue'
import { API_CONFIG } from '@/config/constants'
import type { LightboxImage } from '@/types'

interface Photo {
    id: string
    file_path: string
    display_url?: string
    title?: string
}

interface Gallery {
    id: string
    title: string
    description?: string
    event_date?: string
    event_link?: string
    photos: Photo[]
}

const route = useRoute()

const gallery = ref<Gallery | null>(null)
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

const lightboxImages = computed<LightboxImage[]>(() => {
    if (!gallery.value?.photos) return []
    return gallery.value.photos.map(photo => ({
        url: photo.display_url || photo.file_path,
        alt: photo.title || 'Photo',
        type: 'image' as const
    }))
})

const openLightbox = (index: number) => {
    lightboxIndex.value = index
    lightboxOpen.value = true
}

const fetchGallery = async () => {
    const id = route.params.id as string
    if (!id) {
        error.value = 'Galerie non trouvee'
        isLoading.value = false
        return
    }

    try {
        const response = await fetch(`${API_CONFIG.baseUrl}/events/${id}`, {
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
    } catch (_err) {
        error.value = 'Erreur de connexion'
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchGallery)
</script>
