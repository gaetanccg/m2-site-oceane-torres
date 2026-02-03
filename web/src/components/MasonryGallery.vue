<template>
    <div>
        <!-- Filter buttons -->
        <div v-if="toolbarFilters.length" class="flex flex-wrap justify-center gap-2 sm:gap-4 mb-4 px-2 sm:px-0">
            <button
                v-for="filter in toolbarFilters"
                :key="filter"
                @click="activeFilter = filter"
                @mouseenter="precacheFilter(filter)"
                :class="[
          'px-3 sm:px-6 py-2 text-xs sm:text-sm uppercase tracking-wider sm:tracking-widest font-light transition-all',
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
                <!-- Blur-up placeholder (thumbnail blurred) -->
                <img
                    v-if="item.type === 'image' && item.thumbnailUrl && !isLoaded(item.previewUrl || item.url)"
                    :src="item.thumbnailUrl"
                    :alt="item.alt"
                    class="blur-placeholder"
                    aria-hidden="true"
                />

                <!-- Image with priority loading and responsive srcset -->
                <img
                    v-if="item.type === 'image'"
                    :src="item.previewUrl || item.url"
                    :srcset="getImageSrcset(item)"
                    sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
                    :alt="item.alt"
                    draggable="false"
                    decoding="async"
                    loading="lazy"
                    :fetchpriority="index < priorityCount ? 'high' : 'auto'"
                    :class="['gallery-image select-none', isLoaded(item.previewUrl || item.url) ? 'loaded' : '']"
                    @load="handleImageLoad(item.previewUrl || item.url)"
                    @error="(e) => handleImageError(e, item)"
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
                    v-if="!isLoaded(item.previewUrl || item.url)"
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

// Preload a single image with retry support
const preloadImage = (url: string, retries = 0): Promise<void> => {
    if (preloadedUrls.has(url)) return Promise.resolve()
    if (retries === 0) preloadedUrls.add(url)

    return new Promise((resolve) => {
        const img = new Image()
        img.onload = () => {
            loadedImages.value.add(url)
            resolve()
        }
        img.onerror = () => {
            if (retries < MAX_RETRIES) {
                // Retry after delay
                setTimeout(() => {
                    preloadImage(url, retries + 1).then(resolve)
                }, RETRY_DELAY)
            } else {
                resolve() // Give up, mark as done
            }
        }
        img.src = url
    })
}

// Preload image with its preview URL
const preloadGalleryItem = (item: GalleryItem): Promise<void> => {
    const url = item.previewUrl || item.url
    return preloadImage(url)
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
    const imageItems = filteredItems.value.filter(item => item.type === 'image')

    // First batch (visible) - load immediately with high priority
    const firstBatch = imageItems.slice(0, priorityCount.value)
    const restBatch = imageItems.slice(priorityCount.value)

    // Preload thumbnails first (they're small, load fast for blur-up effect)
    const thumbnailUrls = firstBatch
        .filter(item => item.thumbnailUrl)
        .map(item => item.thumbnailUrl!)
    thumbnailUrls.forEach(url => preloadImage(url))

    // Load first batch preview images in parallel
    Promise.all(firstBatch.map(preloadGalleryItem)).then(() => {
        // Then load rest in smaller batches to not overwhelm
        const restUrls = restBatch.map(item => item.previewUrl || item.url)
        preloadBatch(restUrls, 4)
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

    // Preload first 6 images of this filter (use preview URL if available)
    items.slice(0, 6).forEach(item => {
        const url = item.previewUrl || item.url
        preloadImage(url)
    })
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

// Track retry attempts per image
const retryAttempts = new Map<string, number>()
const MAX_RETRIES = 3
const RETRY_DELAY = 2000

// Generate srcset for responsive images
const getImageSrcset = (item: GalleryItem): string => {
    const srcsetParts: string[] = []

    // Thumbnail for small screens (600w)
    if (item.thumbnailUrl) {
        srcsetParts.push(`${item.thumbnailUrl} 600w`)
    }

    // Preview for medium screens (2560w)
    if (item.previewUrl) {
        srcsetParts.push(`${item.previewUrl} 2560w`)
    }

    // Original for large screens
    if (item.url && item.url !== item.previewUrl) {
        srcsetParts.push(`${item.url} 4000w`)
    }

    return srcsetParts.join(', ')
}

// Handle image load error with auto-retry
const handleImageError = (e: Event, item: GalleryItem) => {
    const img = e.target as HTMLImageElement
    const url = item.previewUrl || item.url
    const attempts = retryAttempts.get(url) || 0

    if (attempts < MAX_RETRIES) {
        retryAttempts.set(url, attempts + 1)
        console.warn(`Image load failed, retrying (${attempts + 1}/${MAX_RETRIES}):`, url)

        // Retry after delay
        setTimeout(() => {
            // Force reload by appending cache-buster
            const separator = url.includes('?') ? '&' : '?'
            img.src = `${url}${separator}_retry=${Date.now()}`
        }, RETRY_DELAY)
    } else {
        console.error(`Image failed after ${MAX_RETRIES} retries:`, url)
        // Mark as loaded to remove skeleton (show broken state)
        loadedImages.value.add(url)
    }
}

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

/* Blur-up placeholder - shows blurred thumbnail while loading */
.blur-placeholder{
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: blur(20px);
    transform: scale(1.1); /* Prevent blur edges */
    z-index: 1;
}

.gallery-image{
    position: relative;
    display: block;
    width: 100%;
    height: auto;
    opacity: 0;
    transition: transform 0.5s ease, opacity 0.3s ease-out;
    z-index: 2;
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
