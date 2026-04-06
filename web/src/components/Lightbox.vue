<template>
    <Transition name="fade">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-50 flex flex-col bg-black/95"
            @click="close"
            @contextmenu.prevent
        >
            <!-- Close button -->
            <button
                @click="close"
                class="absolute top-4 right-4 md:top-6 md:right-6 text-white hover:text-gold transition-colors z-20"
                aria-label="Fermer"
            >
                <IconClose class="w-7 h-7 md:w-8 md:h-8" />
            </button>

            <!-- Main content area -->
            <div class="flex-1 flex items-center justify-center relative min-h-0">
                <!-- Previous button -->
                <button
                    v-if="currentIndex > 0"
                    @click.stop="previous"
                    class="absolute left-2 md:left-6 text-white hover:text-gold transition-colors z-10"
                    aria-label="Précédent"
                >
                    <IconChevronLeft class="w-8 h-8 md:w-10 md:h-10" />
                </button>

                <!-- Media container -->
                <div class="media-container" @click.stop>
                    <div class="media-wrapper">
                        <img
                            v-if="images[currentIndex]?.type === 'image'"
                            :src="images[currentIndex].url"
                            :alt="images[currentIndex].alt"
                            draggable="false"
                            class="media-content select-none"
                        />
                        <video
                            v-else-if="images[currentIndex]?.type === 'video'"
                            :src="images[currentIndex].url"
                            controls
                            class="media-content"
                        />
                        <!-- YouTube iframe (requiert consentement marketing) -->
                        <template v-else-if="images[currentIndex]?.type === 'youtube'">
                            <iframe
                                v-if="consentStore.marketingEnabled"
                                :src="`https://www.youtube.com/embed/${images[currentIndex].youtubeId}?autoplay=1&rel=0`"
                                class="youtube-iframe"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            />
                            <div v-else class="youtube-iframe youtube-placeholder" @click.stop>
                                <div class="flex flex-col items-center justify-center h-full text-center px-6">
                                    <svg class="w-16 h-16 text-gray-400 mb-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0C.488 3.45.029 5.804 0 12c.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0C23.512 20.55 23.971 18.196 24 12c-.029-6.185-.484-8.549-4.385-8.816zM9 16V8l8 4-8 4z"/>
                                    </svg>
                                    <p class="text-gray-300 text-sm mb-4">
                                        Cette vidéo est hébergée par YouTube. En l'affichant, vous acceptez les cookies tiers de YouTube.
                                    </p>
                                    <button
                                        @click="acceptMarketingAndPlay"
                                        class="px-6 py-2.5 bg-gold text-black text-sm font-medium rounded-lg hover:bg-gold/90 transition-colors"
                                    >
                                        Accepter et regarder
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Watermark overlay (uniquement pour les images) -->
                        <div v-if="showWatermark && images[currentIndex]?.type === 'image'" class="watermark-overlay" aria-hidden="true">
                            <!-- Layer 1: grille 4×5 diagonale -->
                            <div class="watermark-grid">
                                <span v-for="n in 20" :key="n">©Oceane Torres</span>
                            </div>
                            <!-- Layer 2: gros texte central -->
                            <div class="watermark-center">©Oceane Torres</div>
                        </div>
                    </div>
                </div>

                <!-- Next button -->
                <button
                    v-if="currentIndex < images.length - 1"
                    @click.stop="next"
                    class="absolute right-2 md:right-6 text-white hover:text-gold transition-colors z-10"
                    aria-label="Suivant"
                >
                    <IconChevronRight class="w-8 h-8 md:w-10 md:h-10" />
                </button>
            </div>

            <!-- Bottom thumbnail gallery -->
            <div class="thumbnail-bar" @click.stop>
                <div class="thumbnail-container" ref="thumbnailContainer">
                    <button
                        v-for="(image, index) in images"
                        :key="index"
                        :ref="el => { if (el && index === currentIndex) scrollToThumbnail(el as HTMLElement) }"
                        @click="goToIndex(index)"
                        class="thumbnail-item"
                        :class="{ 'active': index === currentIndex }"
                    >
                        <img
                            v-if="image.type === 'image'"
                            :src="image.thumbnailUrl || image.url"
                            :alt="image.alt || `Photo ${index + 1}`"
                            class="thumbnail-img"
                            loading="lazy"
                            width="60"
                            height="60"
                        />
                        <div v-else class="thumbnail-placeholder">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                        </div>
                    </button>
                </div>

                <!-- Counter -->
                <div class="thumbnail-counter">
                    {{ currentIndex + 1 }} / {{ images.length }}
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup lang="ts">
import {ref, watch, onMounted, onUnmounted, nextTick} from 'vue'
import type {LightboxImage} from '@/types'
import {IconClose, IconChevronLeft, IconChevronRight} from './icons'
import {useConsentStore} from '@/stores/consent'

const consentStore = useConsentStore()

interface Props {
    images: LightboxImage[]
    isOpen: boolean
    initialIndex: number
    showWatermark?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    showWatermark: true
})
const emit = defineEmits<{ close: [] }>()

const currentIndex = ref(props.initialIndex)
const thumbnailContainer = ref<HTMLElement | null>(null)

watch(() => props.initialIndex, (newVal) => {
    currentIndex.value = newVal
})

const close = () => emit('close')

const acceptMarketingAndPlay = () => {
    consentStore.savePreferences(consentStore.preferences.analytics, true)
}

const previous = () => {
    if (currentIndex.value > 0) {
        currentIndex.value--
    }
}

const next = () => {
    if (currentIndex.value < props.images.length - 1) {
        currentIndex.value++
    }
}

const goToIndex = (index: number) => {
    currentIndex.value = index
}

const scrollToThumbnail = (el: HTMLElement) => {
    nextTick(() => {
        el.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
            inline: 'center'
        })
    })
}

const handleKeydown = (e: KeyboardEvent) => {
    if (!props.isOpen) return

    switch (e.key) {
        case 'Escape':
            close()
            break
        case 'ArrowLeft':
            previous()
            break
        case 'ArrowRight':
            next()
            break
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
.media-container{
    display: flex;
    align-items: center;
    justify-content: center;
    max-width: calc(100vw - 120px);
    max-height: calc(100vh - 160px);
    padding: 0 20px;
}

.media-wrapper{
    position: relative;
    display: inline-block;
    line-height: 0;
}

.media-content{
    max-width: calc(100vw - 160px);
    max-height: calc(100vh - 180px);
    object-fit: contain;
}

.youtube-iframe{
    width: 80vw;
    height: 45vw;
    max-width: 1280px;
    max-height: 720px;
}

.youtube-placeholder{
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
}

.watermark-overlay{
    position: absolute;
    inset: 0;
    pointer-events: none;
    user-select: none;
    z-index: 10;
    overflow: hidden;
    container-type: size;
}

.watermark-grid{
    position: absolute;
    inset: -5%;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    grid-template-rows: repeat(5, 1fr);
    align-items: center;
    justify-items: center;
}

.watermark-grid span{
    font-family: 'Amsterdam Four', cursive;
    font-size: 4cqmin;
    color: rgba(50, 50, 50, 0.8);
    white-space: nowrap;
    transform: rotate(-30deg);
}

.watermark-center{
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Amsterdam Four', cursive;
    font-size: min(15cqmin, 9.5cqw);
    color: rgba(50, 50, 50, 0.5);
    white-space: nowrap;
}

/* Thumbnail bar */
.thumbnail-bar{
    flex-shrink: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.9), rgba(0, 0, 0, 0.7));
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.thumbnail-container{
    display: flex;
    gap: 8px;
    overflow-x: auto;
    max-width: 100%;
    padding: 4px;
    scroll-behavior: smooth;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
}

.thumbnail-container::-webkit-scrollbar{
    height: 4px;
}

.thumbnail-container::-webkit-scrollbar-track{
    background: transparent;
}

.thumbnail-container::-webkit-scrollbar-thumb{
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
}

.thumbnail-item{
    flex-shrink: 0;
    width: 60px;
    height: 60px;
    border-radius: 6px;
    overflow: hidden;
    border: 2px solid transparent;
    opacity: 0.5;
    transition: all 0.2s ease;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.1);
}

.thumbnail-item:hover{
    opacity: 0.8;
    border-color: rgba(255, 255, 255, 0.5);
}

.thumbnail-item.active{
    opacity: 1;
    border-color: #dcb253;
    transform: scale(1.05);
}

.thumbnail-img{
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.thumbnail-placeholder{
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.thumbnail-counter{
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.75rem;
    font-weight: 300;
}

.fade-enter-active,
.fade-leave-active{
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to{
    opacity: 0;
}

@media (max-width: 768px){
    .media-container{
        max-width: 100vw;
        padding: 0 8px;
        max-height: calc(100vh - 130px);
        max-height: calc(100dvh - 130px);
    }

    .media-content{
        max-width: calc(100vw - 16px);
        max-height: calc(100vh - 150px);
        max-height: calc(100dvh - 150px);
    }

    .youtube-iframe{
        width: calc(100vw - 16px);
        height: calc((100vw - 16px) * 9 / 16);
        max-height: calc(100vh - 180px);
        max-height: calc(100dvh - 180px);
    }

    .thumbnail-bar{
        padding: 6px 8px;
    }

    .thumbnail-item{
        width: 44px;
        height: 44px;
    }
}

/* Very small screens */
@media (max-width: 380px){
    .thumbnail-item{
        width: 36px;
        height: 36px;
    }
}
</style>
