<template>
    <div>
        <!-- Filter buttons -->
        <div v-if="toolbarFilters.length" class="flex flex-wrap justify-center gap-4 mb-4">
            <button
                v-for="filter in toolbarFilters"
                :key="filter"
                @click="activeFilter = filter"
                @mouseenter="precacheFilter(filter)"
                :class="[
          'px-6 py-2 text-sm uppercase tracking-widest font-light transition-all',
          activeFilter === filter
            ? 'bg-black text-white'
            : 'bg-white border border-gray-300 hover:border-gold hover:text-gold'
        ]"
            >
                {{ filter }}
            </button>
        </div>

        <!-- Category description -->
        <div v-if="activeFilter && categoryDescription" class="text-center mb-6">
            <p class="text-gray-600 font-light">{{ categoryDescription }}</p>
        </div>

        <!-- Gallery grid - utilise une grille pour les vidéos, columns pour les images -->
        <div :class="isVideoFilter(activeFilter) ? 'video-grid' : 'gallery-grid'" ref="galleryRef">
            <div
                v-for="(item, index) in filteredItems"
                :key="item.url + index"
                class="gallery-item group"
                @click="openLightbox(index)"
                @contextmenu.prevent
            >
                <!-- Image with priority loading -->
                <img
                    v-if="item.type === 'image'"
                    :src="item.url"
                    :alt="item.alt"
                    draggable="false"
                    decoding="async"
                    loading="lazy"
                    :fetchpriority="index < priorityCount ? 'high' : 'auto'"
                    :class="['gallery-image select-none', isLoaded(item.url) ? 'loaded' : '']"
                    @load="handleImageLoad(item.url)"
                />

                <!-- Video locale -->
                <div v-else-if="item.type === 'video'" class="relative w-full h-full bg-black">
                    <video
                        :src="item.url"
                        class="w-full h-full object-cover"
                        muted
                        loop
                        @mouseenter="(e) => (e.target as HTMLVideoElement).play()"
                        @mouseleave="(e) => (e.target as HTMLVideoElement).pause()"
                    />
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <svg class="w-16 h-16 text-white/80" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z" />
                        </svg>
                    </div>
                </div>

                <!-- Video YouTube - ratio 16:9 fixe pour uniformité -->
                <div v-else-if="item.type === 'youtube'" class="relative w-full bg-black aspect-video">
                    <img
                        :src="item.url"
                        :alt="item.alt"
                        draggable="false"
                        loading="lazy"
                        class="absolute inset-0 w-full h-full object-cover"
                        @load="handleImageLoad(item.url)"
                        @error="(e) => handleYoutubeError(e, item)"
                    />
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none bg-black/20">
                        <!-- YouTube Play Button -->
                        <div class="w-16 h-12 bg-red-600 rounded-xl flex items-center justify-center shadow-lg">
                            <svg class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Hover overlay -->
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 pointer-events-none" />

                <!-- Subtle skeleton (no pulse, just smooth bg) -->
                <div
                    v-if="!isLoaded(item.url)"
                    class="skeleton-loader"
                />
            </div>
        </div>

        <Lightbox
            :images="filteredItems"
            :is-open="lightboxOpen"
            :initial-index="currentImageIndex"
            @close="lightboxOpen = false"
        />
    </div>
</template>

<script setup lang="ts">
import {ref, computed, onMounted, onUnmounted, watch, nextTick} from 'vue'
import type {GalleryItem, CategoryDescriptions} from '@/types'
import {
    sortByFilename,
    reorderForColumns,
    getColumnCount,
    isVideoFilter,
    isVideoItem,
    getShuffledAllItems
} from '@/utils/gallery'
import Lightbox from './Lightbox.vue'

interface Props {
    items: GalleryItem[]
    filters?: string[]
    categoryDescriptions?: CategoryDescriptions
    showAllTab?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => [],
    categoryDescriptions: () => ({}),
    showAllTab: true
})

// State
const activeFilter = ref(props.showAllTab ? 'Tous' : props.filters[0] ?? '')
const lightboxOpen = ref(false)
const currentImageIndex = ref(0)
const loadedImages = ref(new Set<string>())
const galleryRef = ref<HTMLElement | null>(null)
const columnCount = ref(3)

// Preload cache to avoid duplicate requests
const preloadedUrls = new Set<string>()
const precachedFilters = new Set<string>()

// Priority count - first N images get fetchpriority="high"
const priorityCount = computed(() => Math.max(6, columnCount.value * 2))

// Computed
const toolbarFilters = computed(() => {
    return props.showAllTab ? ['Tous', ...props.filters] : [...props.filters]
})

const categoryDescription = computed(() => {
    return props.categoryDescriptions[activeFilter.value] ?? ''
})

const baseFilteredItems = computed(() => {
    if (!activeFilter.value) {
        return sortByFilename(props.items.filter(item => !isVideoItem(item.type)))
    }

    // Vidéos : garder l'ordre original du tableau (pas de tri, pas de réorganisation)
    if (isVideoFilter(activeFilter.value)) {
        return props.items.filter(item => isVideoItem(item.type))
    }

    if (activeFilter.value === 'Tous') {
        return getShuffledAllItems(props.items, props.filters, 4)
    }

    return sortByFilename(
        props.items.filter(item => item.category === activeFilter.value && !isVideoItem(item.type))
    )
})

const filteredItems = computed(() => {
    // Ne pas réordonner les vidéos - garder l'ordre original
    if (isVideoFilter(activeFilter.value)) {
        return baseFilteredItems.value
    }
    return reorderForColumns(baseFilteredItems.value, columnCount.value)
})

// Preload a single image and mark as loaded when done
const preloadImage = (url: string): Promise<void> => {
    if (preloadedUrls.has(url)) return Promise.resolve()
    preloadedUrls.add(url)

    return new Promise((resolve) => {
        const img = new Image()
        img.onload = () => {
            loadedImages.value.add(url)
            resolve()
        }
        img.onerror = () => resolve()
        img.src = url
    })
}

// Preload images in batches for better performance
const preloadBatch = async (urls: string[], batchSize = 6) => {
    for (let i = 0; i < urls.length; i += batchSize) {
        const batch = urls.slice(i, i + batchSize)
        await Promise.all(batch.map(preloadImage))
    }
}

// Preload current filter's images
const preloadCurrentImages = () => {
    const urls = filteredItems.value
        .filter(item => item.type === 'image')
        .map(item => item.url)

    // First batch (visible) - load immediately with high priority
    const firstBatch = urls.slice(0, priorityCount.value)
    const restBatch = urls.slice(priorityCount.value)

    // Load first batch in parallel (fast)
    Promise.all(firstBatch.map(preloadImage)).then(() => {
        // Then load rest in smaller batches to not overwhelm
        preloadBatch(restBatch, 4)
    })
}

// Precache images when hovering a filter button (anticipation)
const precacheFilter = (filter: string) => {
    if (filter === activeFilter.value || precachedFilters.has(filter)) return
    precachedFilters.add(filter)

    // Get items for this filter
    let items: GalleryItem[]
    if (isVideoFilter(filter)) {
        items = props.items.filter(item => isVideoItem(item.type))
    } else if (filter === 'Tous') {
        items = getShuffledAllItems(props.items, props.filters, 4)
    } else {
        items = props.items.filter(item => item.category === filter && !isVideoItem(item.type))
    }

    // Preload first 6 images of this filter
    const urls = items.slice(0, 6).map(item => item.url)
    urls.forEach(preloadImage)
}

// Check if images are already in browser cache
const checkCachedImages = async () => {
    await nextTick()

    // Check each image in the DOM
    const imgs = galleryRef.value?.querySelectorAll('img')
    imgs?.forEach(img => {
        if (img.complete && img.naturalWidth > 0) {
            const src = img.getAttribute('src')
            if (src) {
                loadedImages.value.add(src)
                preloadedUrls.add(src)
            }
        }
    })
}

// Methods
const handleImageLoad = (url: string) => {
    loadedImages.value.add(url)
}

const isLoaded = (url: string) => loadedImages.value.has(url)

// Fallback pour les thumbnails YouTube qui ne chargent pas en maxresdefault
const handleYoutubeError = (e: Event, item: GalleryItem) => {
    const img = e.target as HTMLImageElement
    const currentSrc = img.src

    // Essayer différentes qualités de thumbnail YouTube
    if (currentSrc.includes('maxresdefault')) {
        img.src = `https://img.youtube.com/vi/${item.youtubeId}/hqdefault.jpg`
    } else if (currentSrc.includes('hqdefault')) {
        img.src = `https://img.youtube.com/vi/${item.youtubeId}/mqdefault.jpg`
    }
}

const updateColumnCount = () => {
    columnCount.value = getColumnCount(window.innerWidth)
}

const openLightbox = (index: number) => {
    currentImageIndex.value = index
    lightboxOpen.value = true
}

// Lifecycle
onMounted(() => {
    updateColumnCount()
    window.addEventListener('resize', updateColumnCount)

    // Check for cached images first
    checkCachedImages()

    // Start preloading current view
    preloadCurrentImages()
})

onUnmounted(() => {
    window.removeEventListener('resize', updateColumnCount)
})

// When filter changes, check cache and preload new images
watch(activeFilter, async () => {
    // Don't reset - keep previously loaded images in memory
    // This allows instant display if user switches back

    await nextTick()
    checkCachedImages()
    preloadCurrentImages()
})
</script>

<style scoped>
.gallery-grid{
    columns: 3;
    column-gap: 4px;
}

/* Grille CSS pour les vidéos - affichage uniforme */
.video-grid{
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 4px;
}

.gallery-item{
    position: relative;
    overflow: hidden;
    cursor: pointer;
    break-inside: avoid;
    margin-bottom: 4px;
}

.video-grid .gallery-item{
    margin-bottom: 0;
}

.gallery-image{
    display: block;
    width: 100%;
    height: auto;
    opacity: 0;
    transition: transform 0.5s ease, opacity 0.3s ease-out;
}

.gallery-image.loaded{
    opacity: 1;
}

.gallery-item:hover .gallery-image{
    transform: scale(1.05);
}

/* Subtle skeleton - no jarring pulse */
.skeleton-loader{
    position: absolute;
    inset: 0;
    background: linear-gradient(
        110deg,
        #f0f0f0 0%,
        #f5f5f5 40%,
        #f0f0f0 60%,
        #f5f5f5 100%
    );
    background-size: 200% 100%;
    animation: shimmer 1.5s ease-in-out infinite;
}

@keyframes shimmer{
    0%{
        background-position: 200% 0;
    }
    100%{
        background-position: -200% 0;
    }
}

@media (max-width: 1024px){
    .gallery-grid{
        columns: 2;
    }
    .video-grid{
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px){
    .gallery-grid{
        columns: 1;
        column-gap: 0;
    }

    .video-grid{
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .gallery-item{
        margin-bottom: 8px;
    }

    .gallery-image{
        width: 100%;
        height: auto;
        max-height: 80vh;
        object-fit: contain;
    }
}
</style>
