<template>
    <div
        class="group bg-white border border-gray-200 hover:border-gold transition-all duration-300 p-5 sm:p-8 lg:p-12 relative overflow-hidden"
    >
        <!-- Background image -->
        <div
            v-if="backgroundUrl"
            class="absolute inset-0 bg-cover bg-center pointer-events-none"
            :style="{ backgroundImage: `url(${backgroundUrl})`, opacity: backgroundOpacity }"
            aria-hidden="true"
        />

        <div class="relative z-10 flex flex-col h-full">
            <div class="mb-6">
                <ServiceIcon :icon="icon" size="md" class="mb-6" />

                <h3 class="text-2xl font-light mb-3 group-hover:text-gold transition-colors">
                    {{ title }}
                </h3>

                <p class="text-gray-600 font-light leading-relaxed">
                    {{ description }}
                </p>
            </div>

            <div class="mt-auto">
                <div v-if="price" class="flex items-baseline mb-6">
                    <span v-if="priceUnit" class="text-gray-500 ml-2 text-sm mr-2">{{ priceUnit }}</span>
                    <span class="text-3xl font-light text-gold">{{ price }}</span>
                </div>

                <FeatureList v-if="features?.length" :features="features" />

                <p v-if="disclaimer" class="mt-8 text-gray-500 italic">{{ disclaimer }}</p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import {computed} from 'vue'
import type {ServiceIconType} from '@/types'
import ServiceIcon from './ServiceIcon.vue'
import FeatureList from './FeatureList.vue'

interface Props {
    icon?: ServiceIconType
    title: string
    description: string
    price?: string
    priceUnit?: string
    features?: string[]
    background?: string
    backgroundOpacity?: number
    disclaimer?: string
}

const props = withDefaults(defineProps<Props>(), {
    backgroundOpacity: 0.08
})

const backgroundUrl = computed(() => {
    const bg = props.background
    if (!bg) return ''

    if (/^(https?:)?\/\//.test(bg) || bg.startsWith('data:') || bg.startsWith('/')) {
        return bg
    }

    try {
        return new URL(bg, import.meta.url).href
    } catch {
        return bg
    }
})
</script>
