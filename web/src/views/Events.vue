<template>
    <div class="pt-20 min-h-screen bg-white">
        <!-- Hero Section -->
        <section class="py-16 px-6 bg-cream">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl md:text-5xl font-light mb-4">Galeries d'evenements</h1>
                <p class="text-gray-600 font-light text-lg max-w-2xl mx-auto">
                    Decouvrez les moments captures lors de mes differents evenements.
                    Mariages, baptemes, anniversaires et bien plus encore.
                </p>
            </div>
        </section>

        <!-- Loading state -->
        <div v-if="isLoading" class="flex items-center justify-center py-24">
            <div class="animate-spin w-8 h-8 border-2 border-gold border-t-transparent rounded-full" />
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="text-center py-24 px-6">
            <p class="text-gray-500">{{ error }}</p>
        </div>

        <!-- Empty state -->
        <div v-else-if="galleries.length === 0" class="text-center py-24 px-6">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <p class="text-gray-500 font-light">Aucun evenement disponible pour le moment.</p>
        </div>

        <!-- Galleries Grid -->
        <section v-else class="py-12 px-6 lg:px-12">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <router-link
                        v-for="gallery in galleries"
                        :key="gallery.id"
                        :to="{ name: 'event-gallery', params: { id: gallery.id } }"
                        class="group"
                    >
                        <div class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300">
                            <!-- Cover Image -->
                            <div class="aspect-[4/3] bg-gray-100 relative overflow-hidden">
                                <img
                                    v-if="gallery.photos && gallery.photos.length > 0"
                                    :src="gallery.photos[0].display_url || gallery.photos[0].file_path"
                                    :alt="gallery.title"
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
                                    {{ gallery.photos_count }} photo(s)
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-5">
                                <h2 class="text-xl font-medium text-gray-900 mb-2 group-hover:text-gold transition-colors">
                                    {{ gallery.title }}
                                </h2>
                                <p v-if="gallery.description" class="text-gray-500 font-light line-clamp-2 text-sm">
                                    {{ gallery.description }}
                                </p>

                                <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                                    <span v-if="gallery.event_date" class="text-sm text-gray-400 flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        {{ formatDate(gallery.event_date) }}
                                    </span>
                                    <span v-else class="text-sm text-gray-400">{{ formatDate(gallery.created_at) }}</span>
                                    <span class="text-gold font-medium text-sm flex items-center gap-1 group-hover:gap-2 transition-all">
                                        Voir la galerie
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </router-link>
                </div>

                <!-- Load More Button -->
                <div v-if="hasMore" class="text-center mt-12">
                    <button
                        @click="loadMore"
                        :disabled="isLoadingMore"
                        class="px-8 py-3 border-2 border-gold text-gold rounded-lg font-medium hover:bg-gold hover:text-white transition-colors disabled:opacity-50"
                    >
                        <span v-if="isLoadingMore" class="flex items-center gap-2">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Chargement...
                        </span>
                        <span v-else>Voir plus</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- Contact CTA -->
        <section class="py-16 px-6 bg-gray-900 text-white">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-3xl font-light mb-4">Vous vous reconnaissez sur une photo ?</h2>
                <p class="text-gray-300 font-light mb-8 max-w-2xl mx-auto">
                    Si vous avez participe a l'un de ces evenements et souhaitez obtenir vos photos,
                    n'hesitez pas a me contacter. Je serai ravie de vous les partager !
                </p>
                <router-link
                    to="/contact"
                    class="inline-flex items-center gap-2 px-8 py-4 bg-gold text-white rounded-lg font-medium hover:opacity-90 transition-opacity"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Me contacter
                </router-link>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { API_CONFIG } from '@/config/constants'

interface Photo {
    id: string
    file_path: string
    display_url?: string
}

interface Gallery {
    id: string
    title: string
    description?: string
    event_date?: string
    event_link?: string
    photos_count: number
    photos?: Photo[]
    created_at: string
}

const galleries = ref<Gallery[]>([])
const isLoading = ref(true)
const isLoadingMore = ref(false)
const error = ref('')
const currentPage = ref(1)
const hasMore = ref(false)

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    })
}

async function fetchGalleries(page = 1) {
    try {
        const response = await fetch(`${API_CONFIG.baseUrl}/events?page=${page}`, {
            headers: { 'Accept': 'application/json' }
        })

        if (response.ok) {
            const data = await response.json()
            if (page === 1) {
                galleries.value = data.data
            } else {
                galleries.value.push(...data.data)
            }
            currentPage.value = data.meta?.current_page || page
            hasMore.value = data.meta?.current_page < data.meta?.last_page
        } else {
            error.value = 'Impossible de charger les galeries'
        }
    } catch (err) {
        error.value = 'Erreur de connexion'
        console.error('Error fetching events:', err)
    } finally {
        isLoading.value = false
        isLoadingMore.value = false
    }
}

async function loadMore() {
    isLoadingMore.value = true
    await fetchGalleries(currentPage.value + 1)
}

onMounted(() => {
    fetchGalleries()
})
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
