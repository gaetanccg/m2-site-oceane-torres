<template>
    <div
        class="group bg-white border border-gray-200 hover:border-gold transition-all duration-300 p-6 lg:p-8 text-center"
    >
        <ServiceIcon :icon="icon" size="md" class="mx-auto mb-4" />

        <h3 class="text-xl font-light mb-3 group-hover:text-gold transition-colors">
            {{ title }}
        </h3>

        <p class="text-gray-600 font-light leading-relaxed text-sm mb-4">
            {{ description }}
        </p>

        <FeatureList
            v-if="features?.length"
            :features="features"
            list-class="text-left"
        />
    </div>
</template>

<script setup lang="ts">
import type {ServiceIconType} from '@/types'
import ServiceIcon from './ServiceIcon.vue'
import FeatureList from './FeatureList.vue'

interface Props {
    icon?: ServiceIconType
    title: string
    description: string
    features?: string[]
}

defineProps<Props>()
</script>
