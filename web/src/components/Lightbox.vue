<template>
    <Transition name="fade">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/95"
            @click="close"
            @contextmenu.prevent
        >
            <!-- Close button -->
            <button
                @click="close"
                class="absolute top-6 right-6 text-white hover:text-gold transition-colors z-10"
                aria-label="Fermer"
            >
                <IconClose class="w-8 h-8" />
            </button>

            <!-- Previous button -->
            <button
                v-if="currentIndex > 0"
                @click.stop="previous"
                class="absolute left-6 text-white hover:text-gold transition-colors z-10"
                aria-label="Précédent"
            >
                <IconChevronLeft class="w-10 h-10" />
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
                    <!-- YouTube iframe -->
                    <iframe
                        v-else-if="images[currentIndex]?.type === 'youtube'"
                        :src="`https://www.youtube.com/embed/${images[currentIndex].youtubeId}?autoplay=1&rel=0`"
                        class="youtube-iframe"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    />

                    <!-- Watermark overlay (uniquement pour les images) -->
                    <div v-if="images[currentIndex]?.type === 'image'" class="watermark-overlay" aria-hidden="true">
                        <span v-for="n in 20" :key="n">@Océane Torres Photographie</span>
                    </div>
                </div>
            </div>

            <!-- Next button -->
            <button
                v-if="currentIndex < images.length - 1"
                @click.stop="next"
                class="absolute right-6 text-white hover:text-gold transition-colors z-10"
                aria-label="Suivant"
            >
                <IconChevronRight class="w-10 h-10" />
            </button>

            <!-- Counter -->
            <div class="absolute bottom-6 left-0 right-0 text-center text-white text-sm">
                {{ currentIndex + 1 }} / {{ images.length }}
            </div>
        </div>
    </Transition>
</template>

<script setup lang="ts">
import {ref, watch, onMounted, onUnmounted} from 'vue'
import type {LightboxImage} from '@/types'
import {IconClose, IconChevronLeft, IconChevronRight} from './icons'

interface Props {
    images: LightboxImage[]
    isOpen: boolean
    initialIndex: number
}

const props = defineProps<Props>()
const emit = defineEmits<{ close: [] }>()

const currentIndex = ref(props.initialIndex)

watch(() => props.initialIndex, (newVal) => {
    currentIndex.value = newVal
})

const close = () => emit('close')

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
    max-height: 90vh;
    padding: 0 20px;
}

.media-wrapper{
    position: relative;
    display: inline-block;
    line-height: 0;
}

.media-content{
    max-width: calc(100vw - 160px);
    max-height: 90vh;
    object-fit: contain;
}

.youtube-iframe{
    width: 80vw;
    height: 45vw;
    max-width: 1280px;
    max-height: 720px;
}

.watermark-overlay{
    position: absolute;
    inset: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 3rem;
    padding: 2rem;
    pointer-events: none;
    user-select: none;
    z-index: 10;
    overflow: hidden;
}

.watermark-overlay span{
    font-size: 0.85rem;
    font-weight: 300;
    color: rgba(255, 255, 255, 0.2);
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    letter-spacing: 0.03em;
    white-space: nowrap;
    transform: rotate(-25deg);
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
        padding: 0 10px;
    }

    .media-content{
        max-width: calc(100vw - 20px);
    }

    .youtube-iframe{
        width: calc(100vw - 20px);
        height: calc((100vw - 20px) * 9 / 16);
    }

    .watermark-overlay{
        gap: 1.5rem;
        padding: 1rem;
    }

    .watermark-overlay span{
        font-size: 0.65rem;
    }
}
</style>
