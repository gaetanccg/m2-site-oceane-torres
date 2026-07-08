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
                        <h1 class="text-4xl font-light mb-4 text-center">{{ gallery.title }}</h1>
                        <p v-if="gallery.description" class="text-gray-600 font-light max-w-2xl mx-auto mb-4 text-center">
                            {{ gallery.description }}
                        </p>
                        <p class="text-sm text-gray-400">
                            {{ gallery.photos?.length || 0 }} photo(s) disponible(s) au téléchargement
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
                            {{ isDownloadingAll ? 'Preparation du ZIP...' : 'Télécharger toutes les photos' }}
                        </button>
                    </div>

                    <!-- Photos grid (left-to-right, row order) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 items-start">
                        <div
                            v-for="(photo, index) in gallery.photos"
                            :key="photo.id"
                        >
                            <PhotoCard
                                :src="getThumbUrl(photo)"
                                :thumbnail-url="getThumbUrl(photo)"
                                :alt="photo.title || 'Photo'"
                                @click="openLightbox(index)"
                            >
                                <template #actions>
                                    <button
                                        @click.stop="downloadPhoto(photo)"
                                        :disabled="downloadingPhotos.has(photo.id)"
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 hover:text-gold transition-colors disabled:opacity-50"
                                    >
                                        <svg v-if="!downloadingPhotos.has(photo.id)" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        <svg v-else class="w-5 h-5 text-gold animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        {{ downloadingPhotos.has(photo.id) ? 'Téléchargement…' : 'Télécharger' }}
                                    </button>
                                </template>
                            </PhotoCard>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <!-- ZIP preparation overlay -->
        <div
            v-if="isDownloadingAll"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
        >
            <div class="bg-white rounded-2xl shadow-xl px-10 py-8 flex flex-col items-center gap-4 max-w-sm mx-6 text-center">
                <svg class="animate-spin h-12 w-12 text-gold" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <div>
                    <p class="text-lg font-medium text-gray-800">Préparation de votre archive…</p>
                    <p class="text-sm text-gray-500 font-light mt-1">
                        Le téléchargement démarrera automatiquement. Merci de patienter.
                    </p>
                </div>
            </div>
        </div>

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
import {ref, computed, onMounted} from 'vue'
import {useRoute} from 'vue-router'
import Lightbox from '@/components/Lightbox.vue'
import PhotoCard from '@/components/PhotoCard.vue'
import {API_CONFIG} from '@/config/constants'
import {isInAppBrowser} from '@/utils/download'
import type {LightboxImage} from '@/types'

interface Photo {
    id: string
    file_path: string
    display_url?: string
    preview_url?: string
    thumbnail_url?: string
    clean_thumbnail_url?: string
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

// Grid thumbnail: prefer the clean thumbnail served directly from MinIO by the
// backend (fast); fall back to the on-the-fly proxy if it hasn't been generated.
const getThumbUrl = (photo: Photo) => {
    if (photo.clean_thumbnail_url) return photo.clean_thumbnail_url
    if (!gallery.value) return photo.thumbnail_url || photo.preview_url || photo.file_path
    return `${API_CONFIG.baseUrl}/images/clean-thumb/${photo.id}?token=${gallery.value.access_token}`
}

// Full-size clean image (no watermark) — used by the lightbox
const getCleanUrl = (photo: Photo) => {
    if (!gallery.value) return photo.preview_url || photo.display_url || photo.file_path
    return `${API_CONFIG.baseUrl}/images/clean/${photo.id}?token=${gallery.value.access_token}`
}

const lightboxImages = computed<LightboxImage[]>(() => {
    if (!gallery.value?.photos) return []
    return gallery.value.photos.map(photo => ({
        url: getCleanUrl(photo),
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
        if (isInAppBrowser()) {
            window.location.href = `${API_CONFIG.baseUrl}/photos/${photo.id}/download?token=${gallery.value.access_token}&direct=1`
            setTimeout(() => downloadingPhotos.value.delete(photo.id), 2000)
            return
        }

        const response = await fetch(
            `${API_CONFIG.baseUrl}/photos/${photo.id}/download?token=${gallery.value.access_token}`,
            {
                headers: {'Accept': 'application/json'}
            }
        )

        if (response.ok) {
            const data = await response.json()
            const filename = data.filename || 'photo.jpg'

            // Fetch the actual file and create blob for download
            const fileResponse = await fetch(data.download_url)
            const blob = await fileResponse.blob()
            const blobUrl = URL.createObjectURL(blob)

            const link = document.createElement('a')
            link.href = blobUrl
            link.download = filename
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            URL.revokeObjectURL(blobUrl)
        }
    } catch {
        // Download failed
    } finally {
        downloadingPhotos.value.delete(photo.id)
    }
}

const downloadAll = () => {
    if (!gallery.value) return

    isDownloadingAll.value = true

    // The ZIP is streamed by the backend as a Content-Disposition attachment.
    // We trigger it with a plain browser download (not an XHR) so CORS never
    // applies and the browser shows its native download progress. The overlay
    // bridges the short "connecting" gap until the browser UI takes over.
    const url = `${API_CONFIG.baseUrl}/galleries/${gallery.value.id}/download-zip?token=${gallery.value.access_token}`

    if (isInAppBrowser()) {
        window.location.href = url
    } else {
        const link = document.createElement('a')
        link.href = url
        link.rel = 'noopener'
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
    }

    setTimeout(() => {
        isDownloadingAll.value = false
    }, 4000)
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
    } catch (_err) {
        error.value = 'Erreur de connexion'
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchGallery)
</script>
