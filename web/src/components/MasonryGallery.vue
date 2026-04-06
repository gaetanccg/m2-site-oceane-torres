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

        <!-- Video grid -->
        <div v-if="isVideoFilter(activeFilter)" class="video-grid" ref="galleryRef">
            <div
                v-for="(item, index) in displayItems"
                :key="item.url + index"
                class="gallery-item group"
                @click="openLightbox(index)"
                @contextmenu.prevent
            >
                <!-- Video locale -->
                <div v-if="item.type === 'video'" class="relative w-full h-full bg-black">
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

                <!-- Video YouTube -->
                <div v-else-if="item.type === 'youtube'" class="relative w-full bg-black aspect-video">
                    <img
                        :src="item.url"
                        :alt="item.alt"
                        draggable="false"
                        loading="lazy"
                        class="absolute inset-0 w-full h-full object-cover"
                        @error="(e) => handleYoutubeError(e, item)"
                    />
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none bg-black/20">
                        <div class="w-16 h-12 bg-red-600 rounded-xl flex items-center justify-center shadow-lg">
                            <svg class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Hover overlay -->
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 pointer-events-none" />
            </div>
        </div>

        <!-- Image masonry grid with DOM columns -->
        <div v-else class="gallery-grid" ref="galleryRef">
            <div v-for="(col, colIdx) in columns" :key="colIdx" class="gallery-column">
                <div
                    v-for="entry in col"
                    :key="entry.item.url"
                    class="gallery-item group"
                    @click="openLightbox(entry.flatIndex)"
                    @contextmenu.prevent
                >
                    <!-- Blur-up placeholder (always visible until main image loads) -->
                    <div
                        v-if="!getImageState(entry.item).loaded"
                        class="blur-placeholder-container"
                    >
                        <img
                            v-if="entry.item.thumbnailUrl"
                            :src="entry.item.thumbnailUrl"
                            :alt="entry.item.alt"
                            class="blur-placeholder"
                            aria-hidden="true"
                            @load="handleThumbnailLoad(entry.item)"
                        />
                        <div class="skeleton-overlay" />
                    </div>

                    <!-- Failed state placeholder -->
                    <div
                        v-if="getImageState(entry.item).failed"
                        class="failed-placeholder"
                        @click.stop="retryFailedImage(entry.item)"
                    >
                        <div class="failed-content">
                            <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-xs text-gray-500">Cliquez pour recharger</span>
                        </div>
                    </div>

                    <!-- Main image -->
                    <img
                        v-show="getImageState(entry.item).loaded && !getImageState(entry.item).failed"
                        :src="getImageState(entry.item).currentSrc"
                        :alt="entry.item.alt"
                        draggable="false"
                        decoding="async"
                        :loading="entry.flatIndex < priorityCount ? 'eager' : 'lazy'"
                        :fetchpriority="entry.flatIndex < priorityCount ? 'high' : 'auto'"
                        class="gallery-image loaded"
                        @load="handleImageLoadSuccess(entry.item)"
                        @error="handleImageLoadError(entry.item)"
                    />

                    <!-- Hover overlay -->
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 pointer-events-none" />
                </div>
            </div>
        </div>

        <Lightbox
            :images="displayItems"
            :is-open="lightboxOpen"
            :initial-index="currentImageIndex"
            @close="lightboxOpen = false"
        />
    </div>
</template>

<script setup lang="ts">
import {ref, computed, onMounted, onUnmounted, watch, nextTick, reactive} from 'vue'
import type {GalleryItem, CategoryDescriptions} from '@/types'
import {
    sortByFilename,
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

// Image state tracking
interface ImageState {
    loaded: boolean
    failed: boolean
    retryCount: number
    currentSrc: string
    thumbnailLoaded: boolean
}

// State
const activeFilter = ref(props.showAllTab ? 'Tous' : props.filters[0] ?? '')
const lightboxOpen = ref(false)
const currentImageIndex = ref(0)
const galleryRef = ref<HTMLElement | null>(null)
const columnCount = ref(3)

// Track state for each image by its unique key
const imageStates = reactive(new Map<string, ImageState>())

// Constants
const MAX_RETRIES = 3
const RETRY_DELAYS = [1000, 2000, 4000] // Exponential backoff

// Preload cache
const preloadedUrls = new Set<string>()
const precachedFilters = new Set<string>()

// Priority count
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

const displayItems = computed(() => baseFilteredItems.value)

// Distribute items into DOM columns via round-robin
const columns = computed(() => {
    const items = displayItems.value
    const cols = columnCount.value
    const result: { item: GalleryItem; flatIndex: number }[][] =
        Array.from({ length: cols }, () => [])

    items.forEach((item, i) => {
        result[i % cols].push({ item, flatIndex: i })
    })

    return result
})

// Get unique key for an image item
const getImageKey = (item: GalleryItem): string => {
    return item.previewUrl || item.url
}

// Get or create image state
const getImageState = (item: GalleryItem): ImageState => {
    const key = getImageKey(item)
    if (!imageStates.has(key)) {
        // Initialize with preview URL, fallback chain: preview -> thumbnail -> original
        const initialSrc = item.previewUrl || item.thumbnailUrl || item.url
        imageStates.set(key, {
            loaded: false,
            failed: false,
            retryCount: 0,
            currentSrc: initialSrc,
            thumbnailLoaded: false
        })
    }
    return imageStates.get(key)!
}

// Get fallback URL chain for an item
const getFallbackUrls = (item: GalleryItem): string[] => {
    const urls: string[] = []
    if (item.previewUrl) urls.push(item.previewUrl)
    if (item.thumbnailUrl && !urls.includes(item.thumbnailUrl)) urls.push(item.thumbnailUrl)
    if (item.url && !urls.includes(item.url)) urls.push(item.url)
    return urls
}

// Handle thumbnail load (for blur-up effect)
const handleThumbnailLoad = (item: GalleryItem) => {
    const state = getImageState(item)
    state.thumbnailLoaded = true
}

// Handle successful image load
const handleImageLoadSuccess = (item: GalleryItem) => {
    const state = getImageState(item)
    state.loaded = true
    state.failed = false
}

// Handle image load error with fallback chain
const handleImageLoadError = (item: GalleryItem) => {
    const state = getImageState(item)
    const fallbackUrls = getFallbackUrls(item)
    const currentIndex = fallbackUrls.indexOf(state.currentSrc)

    // Try next URL in fallback chain
    if (currentIndex < fallbackUrls.length - 1) {
        state.currentSrc = fallbackUrls[currentIndex + 1]
        state.retryCount = 0
        return
    }

    // All fallbacks exhausted, try retry with cache buster
    if (state.retryCount < MAX_RETRIES) {
        const delay = RETRY_DELAYS[state.retryCount] || RETRY_DELAYS[RETRY_DELAYS.length - 1]
        state.retryCount++

        setTimeout(() => {
            // Reset to first URL with cache buster
            const baseUrl = fallbackUrls[0]
            const separator = baseUrl.includes('?') ? '&' : '?'
            state.currentSrc = `${baseUrl}${separator}_retry=${Date.now()}`
        }, delay)
    } else {
        // All retries exhausted - mark as failed
        state.failed = true
        state.loaded = true // Hide skeleton
    }
}

// Retry a failed image manually (on click)
const retryFailedImage = (item: GalleryItem) => {
    const state = getImageState(item)
    const fallbackUrls = getFallbackUrls(item)

    // Reset state and try again
    state.failed = false
    state.loaded = false
    state.retryCount = 0
    state.currentSrc = `${fallbackUrls[0]}?_retry=${Date.now()}`
}

// Preload a single image
const preloadImage = (url: string): Promise<void> => {
    if (preloadedUrls.has(url)) return Promise.resolve()
    preloadedUrls.add(url)

    return new Promise((resolve) => {
        const img = new Image()
        img.onload = () => resolve()
        img.onerror = () => resolve() // Don't block on errors
        img.src = url
    })
}

// Preload images in batches
const preloadBatch = async (urls: string[], batchSize = 4) => {
    for (let i = 0; i < urls.length; i += batchSize) {
        const batch = urls.slice(i, i + batchSize)
        await Promise.all(batch.map(preloadImage))
    }
}

// Preload current filter's images
const preloadCurrentImages = () => {
    const imageItems = displayItems.value.filter(item => item.type === 'image')

    // Preload thumbnails first (small, fast)
    const thumbnailUrls = imageItems
        .filter(item => item.thumbnailUrl)
        .slice(0, 20)
        .map(item => item.thumbnailUrl!)
    thumbnailUrls.forEach(url => preloadImage(url))

    // Then preload preview images
    const previewUrls = imageItems
        .slice(0, priorityCount.value)
        .map(item => item.previewUrl || item.url)

    Promise.all(previewUrls.map(preloadImage)).then(() => {
        // Load rest in batches
        const restUrls = imageItems
            .slice(priorityCount.value)
            .map(item => item.previewUrl || item.url)
        preloadBatch(restUrls, 3)
    })
}

// Precache filter on hover
const precacheFilter = (filter: string) => {
    if (filter === activeFilter.value || precachedFilters.has(filter)) return
    precachedFilters.add(filter)

    let items: GalleryItem[]
    if (isVideoFilter(filter)) {
        items = props.items.filter(item => isVideoItem(item.type))
    } else if (filter === 'Tous') {
        items = getShuffledAllItems(props.items, props.filters, 4)
    } else {
        items = props.items.filter(item => item.category === filter && !isVideoItem(item.type))
    }

    // Preload first 6 thumbnails and previews
    items.slice(0, 6).forEach(item => {
        if (item.thumbnailUrl) preloadImage(item.thumbnailUrl)
        preloadImage(item.previewUrl || item.url)
    })
}

// YouTube error fallback
const handleYoutubeError = (e: Event, item: GalleryItem) => {
    const img = e.target as HTMLImageElement
    const currentSrc = img.src

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

// Debounced resize handler
let resizeRaf: number | null = null
const debouncedUpdateColumnCount = () => {
    if (resizeRaf) window.cancelAnimationFrame(resizeRaf)
    resizeRaf = window.requestAnimationFrame(updateColumnCount)
}

// Lifecycle
onMounted(() => {
    updateColumnCount()
    window.addEventListener('resize', debouncedUpdateColumnCount)
    preloadCurrentImages()
})

onUnmounted(() => {
    window.removeEventListener('resize', debouncedUpdateColumnCount)
    if (resizeRaf) window.cancelAnimationFrame(resizeRaf)
})

// When filter changes, preload new images
watch(activeFilter, async () => {
    await nextTick()
    preloadCurrentImages()
})
</script>

<style scoped>
.gallery-grid{
    display: flex;
    gap: 4px;
}

.gallery-column{
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.video-grid{
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 4px;
}

.gallery-item{
    position: relative;
    overflow: hidden;
    cursor: pointer;
    aspect-ratio: 3/2;
    background: #f5f5f5;
}

.video-grid .gallery-item{
    margin-bottom: 0;
    aspect-ratio: 16/9;
}

/* Once the main image loads, let it dictate natural height */
.gallery-item:has(.gallery-image.loaded){
    aspect-ratio: auto;
}

/* Blur-up placeholder container */
.blur-placeholder-container{
    position: absolute;
    inset: 0;
    z-index: 1;
}

.blur-placeholder{
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: blur(20px);
    transform: scale(1.1);
}

.skeleton-overlay{
    position: absolute;
    inset: 0;
    background: linear-gradient(
        110deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.4) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    background-size: 200% 100%;
    animation: shimmer 1.5s ease-in-out infinite;
}

/* Failed image placeholder */
.failed-placeholder{
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f8f8f8 0%, #e8e8e8 100%);
    z-index: 3;
    cursor: pointer;
}

.failed-content{
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
    text-align: center;
}

.failed-placeholder:hover{
    background: linear-gradient(135deg, #f0f0f0 0%, #e0e0e0 100%);
}

.failed-placeholder:hover .failed-content svg{
    color: #a0a0a0;
}

.gallery-image{
    position: relative;
    display: block;
    width: 100%;
    height: auto;
    opacity: 1;
    transition: transform 0.5s ease;
    z-index: 2;
}

.gallery-item:hover .gallery-image{
    transform: scale(1.05);
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
    .video-grid{
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px){
    .gallery-grid{
        gap: 0;
    }

    .gallery-column{
        gap: 8px;
    }

    .video-grid{
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .gallery-image{
        width: 100%;
        height: auto;
        max-height: 80vh;
        object-fit: contain;
    }
}
</style>
