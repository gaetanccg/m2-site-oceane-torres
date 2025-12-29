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
                    <div class="text-center mb-8">
                        <h1 class="text-4xl font-light mb-4">{{ gallery.title }}</h1>
                        <p v-if="gallery.description" class="text-gray-600 font-light max-w-2xl mx-auto mb-4">
                            {{ gallery.description }}
                        </p>
                        <p class="text-sm text-gray-400">
                            {{ gallery.photos?.length || 0 }} photo(s) disponible(s) au telechargement
                        </p>
                    </div>

                    <!-- Download all button -->
                    <div class="flex justify-center mb-8">
                        <button
                            @click="downloadAll"
                            :disabled="isDownloadingAll || !gallery.photos?.length"
                            class="inline-flex items-center gap-3 px-8 py-4 bg-gold text-white rounded-lg font-medium text-lg hover:opacity-90 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg v-if="!isDownloadingAll" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span v-if="isDownloadingAll" class="animate-spin">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                            </span>
                            {{ isDownloadingAll ? 'Preparation du ZIP...' : 'Telecharger toutes les photos' }}
                        </button>
                    </div>

                    <!-- Photos grid -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div
                            v-for="(photo, index) in gallery.photos"
                            :key="photo.id"
                            class="relative group cursor-pointer overflow-hidden rounded-lg aspect-square"
                        >
                            <img
                                :src="photo.file_path"
                                :alt="photo.title || 'Photo'"
                                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                loading="lazy"
                                @click="openLightbox(index)"
                            />

                            <!-- Download button overlay -->
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
                                <button
                                    @click.stop="downloadPhoto(photo)"
                                    :disabled="downloadingPhotos.has(photo.id)"
                                    class="opacity-0 group-hover:opacity-100 transition-opacity p-3 bg-white/90 rounded-full hover:bg-white"
                                >
                                    <svg v-if="!downloadingPhotos.has(photo.id)" class="w-6 h-6 text-gray-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <svg v-else class="w-6 h-6 text-gold animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <!-- Lightbox (without watermark for download gallery) -->
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
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import Lightbox from '@/components/Lightbox.vue'
import { API_CONFIG } from '@/config/constants'
import type { LightboxImage } from '@/types'

interface Photo {
    id: string
    file_path: string
    title?: string
}

interface Gallery {
    id: string
    title: string
    description?: string
    access_token: string
    photos: Photo[]
}

const route = useRoute()

const gallery = ref<Gallery | null>(null)
const isLoading = ref(true)
const error = ref('')
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)
const isDownloadingAll = ref(false)
const downloadingPhotos = ref(new Set<string>())

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

const downloadPhoto = async (photo: Photo) => {
    if (!gallery.value) return

    downloadingPhotos.value.add(photo.id)

    try {
        const response = await fetch(
            `${API_CONFIG.baseUrl}/photos/${photo.id}/download?token=${gallery.value.access_token}`,
            {
                headers: { 'Accept': 'application/json' }
            }
        )

        if (response.ok) {
            const data = await response.json()
            // Open download URL in new tab/trigger download
            const link = document.createElement('a')
            link.href = data.download_url
            link.download = data.filename || 'photo.jpg'
            link.target = '_blank'
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
        }
    } catch (err) {
        console.error('Download error:', err)
    } finally {
        downloadingPhotos.value.delete(photo.id)
    }
}

const downloadAll = async () => {
    if (!gallery.value) return

    isDownloadingAll.value = true

    try {
        const response = await fetch(
            `${API_CONFIG.baseUrl}/galleries/${gallery.value.id}/download-zip?token=${gallery.value.access_token}`,
            {
                headers: { 'Accept': 'application/json' }
            }
        )

        if (response.ok) {
            const data = await response.json()
            // Trigger ZIP download
            const link = document.createElement('a')
            link.href = data.download_url
            link.download = data.filename || 'gallery.zip'
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
        }
    } catch (err) {
        console.error('Download error:', err)
    } finally {
        isDownloadingAll.value = false
    }
}

const fetchGallery = async () => {
    const token = route.params.token as string
    if (!token) {
        error.value = 'Lien invalide'
        isLoading.value = false
        return
    }

    try {
        const response = await fetch(`${API_CONFIG.baseUrl}/galleries/download/${token}`, {
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
