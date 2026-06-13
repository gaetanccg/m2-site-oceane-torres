<template>
    <div class="photo-card bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow">
        <!-- Image container - always rendered for loading to work -->
        <div class="photo-wrapper cursor-pointer" @click="$emit('click')" @contextmenu.prevent>
            <!-- Skeleton overlay (on top until loaded) -->
            <div v-if="!isLoaded" class="skeleton-overlay">
                <img
                    v-if="thumbnailUrl"
                    :src="thumbnailUrl"
                    class="blur-bg"
                    alt=""
                    aria-hidden="true"
                    loading="lazy"
                />
                <div class="shimmer" />
            </div>

            <!-- Failed state overlay -->
            <div v-if="hasFailed" class="failed-overlay" @click.stop="retryLoad">
                <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="text-xs text-gray-500">Cliquez pour recharger</span>
            </div>

            <!-- Actual image - always in DOM, opacity changes when loaded -->
            <img
                :key="reloadKey"
                :src="imageSrc"
                :alt="alt"
                loading="lazy"
                decoding="async"
                :class="['photo-image', { 'is-loaded': isLoaded && !hasFailed }]"
                @load="onImageLoad"
                @error="onImageError"
            />
        </div>

        <!-- Actions -->
        <div class="photo-actions p-3 flex items-center justify-center gap-3 border-t border-gold">
            <slot name="actions" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

interface Props {
    src: string
    thumbnailUrl?: string
    alt?: string
}

const props = withDefaults(defineProps<Props>(), {
    alt: 'Photo'
})

defineEmits<{
    click: []
}>()

// State
const isLoaded = ref(false)
const hasFailed = ref(false)
const imageSrc = ref(props.src)
const retryCount = ref(0)
// Bumping this key recreates the <img> element to force a fresh network fetch.
const reloadKey = ref(0)

const MAX_RETRIES = 3

// Les URLs signées MinIO signent TOUS les query params (SigV4) : on ne peut pas
// ajouter de cache-buster sans casser la signature. On force alors un nouveau fetch
// en recréant l'élément (bump de key). Pour les URLs proxy classiques, on garde le
// cache-buster qui contourne aussi une entrée de cache navigateur corrompue.
const isSignedUrl = (url: string) => url.includes('X-Amz-Signature')

const forceReload = () => {
    if (isSignedUrl(props.src)) {
        imageSrc.value = props.src
        reloadKey.value++
    } else {
        const separator = props.src.includes('?') ? '&' : '?'
        imageSrc.value = `${props.src}${separator}_r=${Date.now()}`
    }
}

// Handle successful load
const onImageLoad = () => {
    isLoaded.value = true
    hasFailed.value = false
}

// Handle error with retry
const onImageError = () => {
    if (retryCount.value < MAX_RETRIES) {
        retryCount.value++
        setTimeout(forceReload, 1000 * retryCount.value)
    } else {
        hasFailed.value = true
        isLoaded.value = true
    }
}

// Manual retry
const retryLoad = () => {
    hasFailed.value = false
    isLoaded.value = false
    retryCount.value = 0
    forceReload()
}

// Reset when src changes
watch(() => props.src, (newSrc) => {
    isLoaded.value = false
    hasFailed.value = false
    retryCount.value = 0
    imageSrc.value = newSrc
})


</script>

<style scoped>
.photo-card {
    position: relative;
}

.photo-wrapper {
    position: relative;
    overflow: hidden;
    aspect-ratio: 3/2;
    background: #f5f5f5;
}

/* Once loaded, let natural image dimensions take over */
.photo-wrapper:has(.photo-image.is-loaded) {
    aspect-ratio: auto;
}

/* Skeleton overlay */
.skeleton-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    background: #f0f0f0;
}

.blur-bg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: blur(20px);
    transform: scale(1.1);
    opacity: 0.7;
}

.shimmer {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        110deg,
        transparent 0%,
        rgba(255, 255, 255, 0.5) 50%,
        transparent 100%
    );
    background-size: 200% 100%;
    animation: shimmer 1.5s ease-in-out infinite;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Image */
.photo-image {
    display: block;
    width: 100%;
    height: auto;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.photo-image.is-loaded {
    opacity: 1;
}

/* Failed overlay */
.failed-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f8f8f8 0%, #e8e8e8 100%);
    cursor: pointer;
    z-index: 3;
}

.failed-overlay:hover {
    background: linear-gradient(135deg, #f0f0f0 0%, #e0e0e0 100%);
}

</style>
