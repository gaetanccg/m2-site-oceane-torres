<template>
    <div :class="cardClasses">
        <!-- Popular badge -->
        <div
            v-if="isPopular"
            class="absolute -top-4 left-1/2 -translate-x-1/2 bg-gold text-black px-4 py-1 text-xs uppercase tracking-widest"
        >
            Populaire
        </div>

        <!-- Header -->
        <div class="text-center mb-8">
            <h3 class="text-2xl font-light mb-2">{{ title }}</h3>
            <p :class="subtitleClasses">{{ subtitle }}</p>
            <div :class="priceClasses">
                <span v-if="isCustomPrice" class="text-3xl">{{ price }}</span>
                <template v-else>{{ price }}</template>
            </div>
        </div>

        <!-- Features -->
        <FeatureList
            :features="features"
            list-class="space-y-4 mb-8"
            item-class=""
            :text-class="variant === 'dark' ? 'text-gray-600 font-light' : 'font-light'"
            check-class="w-5 h-5 text-gold mr-3 mt-0.5 flex-shrink-0"
        />
    </div>
</template>

<script setup lang="ts">
import {computed} from 'vue'
import FeatureList from './FeatureList.vue'

interface Props {
    title: string
    subtitle: string
    price: string
    features: string[]
    isPopular?: boolean
    variant?: 'light' | 'dark'
}

const props = withDefaults(defineProps<Props>(), {
    isPopular: false,
    variant: 'light',
})

const isCustomPrice = computed(() => props.price.toLowerCase().includes('devis'))

const cardClasses = computed(() => [
    'p-8 lg:p-10 relative',
    props.variant === 'dark'
        ? 'bg-black text-white border-2 border-gold'
        : 'bg-white border border-gray-200',
])

const subtitleClasses = computed(() => [
    'text-sm mb-6',
    props.variant === 'dark' ? 'text-gray-500' : 'text-gray-500',
])

const priceClasses = computed(() => [
    'text-5xl font-light text-gold mb-2',
])
</script>
