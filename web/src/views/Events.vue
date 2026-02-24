<template>
    <div class="pt-20 min-h-screen bg-white">
        <!-- Hero Section -->
        <section class="py-16 px-6 bg-cream">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl md:text-5xl font-light mb-4 text-center">Galeries d'événements</h1>
                <p class="text-gray-600 font-light text-lg max-w-2xl mx-auto">
                    Découvrez les moments capturés lors de mes différents événements.
                    Événements équestres, séances sportives, journées spéciales et bien plus encore.
                </p>
            </div>
        </section>

        <!-- Category navigation -->
        <nav
            v-if="!isLoading && !error && galleries.length > 0 && categoryNav.length > 1"
            class="sticky top-20 z-30 bg-white/90 backdrop-blur border-b border-gray-200"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-12 py-3 flex gap-2 overflow-x-auto scrollbar-hide">
                <button
                    v-for="cat in categoryNav"
                    :key="cat.id"
                    @click="scrollToCategory(cat.id)"
                    class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap bg-gray-100 text-gray-700 hover:bg-gold hover:text-white transition-colors"
                >
                    {{ cat.name }}
                </button>
            </div>
        </nav>

        <!-- Loading state -->
        <div v-if="isLoading" class="flex items-center justify-center py-24">
            <div class="flex flex-col items-center gap-3">
                <svg class="animate-spin h-10 w-10 text-gold" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-gray-500 font-light">Chargement des galeries...</span>
            </div>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="text-center py-24 px-6">
            <div class="max-w-md mx-auto">
                <svg class="w-16 h-16 text-gold/60 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-gray-600 font-light text-lg leading-relaxed">
                    Un peu de patience, je publierai très prochainement les photos des événements auxquels j'étais présente !
                </p>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="galleries.length === 0" class="text-center py-24 px-6">
            <div class="max-w-md mx-auto">
                <svg class="w-16 h-16 text-gold/60 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-gray-600 font-light text-lg leading-relaxed">
                    Un peu de patience, je publierai très prochainement les photos des événements auxquels j'étais présente !
                </p>
            </div>
        </div>

        <!-- Galleries grouped by category -->
        <section v-else class="py-8 sm:py-12 px-4 sm:px-6 lg:px-12">
            <div class="max-w-7xl mx-auto space-y-12">
                <div
                    v-for="section in galleriesByCategory"
                    :key="section.categoryId || 'uncategorized'"
                    :id="`category-${section.categoryId || 'other'}`"
                    class="category-section"
                >
                    <!-- Category title -->
                    <h2
                        v-if="section.categoryName"
                        class="text-2xl md:text-3xl font-light text-gray-800 mb-6 text-center"
                    >
                        {{ section.categoryName }}
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
                        <router-link
                            v-for="gallery in section.galleries"
                            :key="gallery.id"
                            :to="{ name: 'event-gallery', params: { id: gallery.id } }"
                            class="group"
                        >
                            <div class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300">
                                <!-- Cover Image -->
                                <div class="aspect-[4/3] bg-gray-100 relative overflow-hidden">
                                    <img
                                        v-if="gallery.cover_photo"
                                        :src="gallery.cover_photo.thumbnail_url || gallery.cover_photo.preview_url || gallery.cover_photo.display_url || gallery.cover_photo.file_path"
                                        :alt="gallery.title"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                        loading="lazy"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <!-- Photos/Sub-galleries count -->
                                    <div class="absolute bottom-3 right-3 px-3 py-1.5 bg-black/70 text-white text-sm rounded-full">
                                        <template v-if="gallery.children_count && gallery.children_count > 0">
                                            {{ gallery.children_count }} sous-galerie(s)
                                        </template>
                                        <template v-else>
                                            {{ gallery.photos_count }} photo(s)
                                        </template>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="p-5">
                                    <h3 class="text-xl font-medium text-gray-900 mb-2 group-hover:text-gold transition-colors">
                                        {{ gallery.title }}
                                    </h3>
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
        <section class="py-16 px-6 text-gray-600">
            <div class="max-w-4xl mx-auto text-center">
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
import {ref, computed, onMounted} from 'vue'
import {API_CONFIG} from '@/config/constants'

interface Photo {
    id: string
    file_path: string
    display_url?: string
    preview_url?: string
    thumbnail_url?: string
}

interface EventCategory {
    id: string
    name: string
    slug: string
    sort_order: number
}

interface Gallery {
    id: string
    title: string
    description?: string
    event_date?: string
    event_link?: string
    event_category_id?: string
    event_category?: EventCategory
    sort_order?: number
    photos_count: number
    children_count?: number
    photos?: Photo[]
    cover_photo?: Photo
    created_at: string
}

interface GallerySection {
    categoryId: string | null
    categoryName: string
    galleries: Gallery[]
}

const galleries = ref<Gallery[]>([])
const isLoading = ref(true)
const isLoadingMore = ref(false)
const error = ref('')
const currentPage = ref(1)
const hasMore = ref(false)

// Group galleries by category, sorted by category sort_order
const galleriesByCategory = computed<GallerySection[]>(() => {
    const sections: GallerySection[] = []
    const categorized = new Map<string, {category: EventCategory; galleries: Gallery[]}>()
    const uncategorized: Gallery[] = []

    for (const gallery of galleries.value) {
        if (gallery.event_category_id && gallery.event_category) {
            const existing = categorized.get(gallery.event_category_id)
            if (existing) {
                existing.galleries.push(gallery)
            } else {
                categorized.set(gallery.event_category_id, {
                    category: gallery.event_category,
                    galleries: [gallery],
                })
            }
        } else {
            uncategorized.push(gallery)
        }
    }

    // Sort categories by sort_order then add them
    const sortedCategories = [...categorized.values()].sort(
        (a, b) => a.category.sort_order - b.category.sort_order
    )

    for (const {category, galleries: catGalleries} of sortedCategories) {
        catGalleries.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
        sections.push({
            categoryId: category.id,
            categoryName: category.name,
            galleries: catGalleries,
        })
    }

    // Uncategorized at the end
    if (uncategorized.length > 0) {
        uncategorized.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
        sections.push({
            categoryId: null,
            // Show "Autres" only if there are other sections with category names
            categoryName: sections.length > 0 ? 'Autres' : '',
            galleries: uncategorized,
        })
    }

    return sections
})

const categoryNav = computed(() => {
    return galleriesByCategory.value
        .filter((s) => s.categoryName)
        .map((s) => ({
            id: `category-${s.categoryId || 'other'}`,
            name: s.categoryName,
        }))
})

function scrollToCategory(id: string) {
    document.getElementById(id)?.scrollIntoView({behavior: 'smooth', block: 'start'})
}

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
            headers: {'Accept': 'application/json'}
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
    } catch {
        error.value = 'Erreur de connexion'
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
.line-clamp-2{
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.category-section {
    scroll-margin-top: 8rem;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
