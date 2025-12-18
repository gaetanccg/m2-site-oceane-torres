<template>
    <div :class="wrapperClasses">
        <!-- Portrait -->
        <svg v-if="icon === 'portrait'" :class="iconClasses" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>

        <!-- Sport -->
        <img v-else-if="icon === 'sport'" :src="sportIcon" alt="Sport" :class="iconClasses" />

        <!-- Moto -->
        <img v-else-if="icon === 'moto'" :src="motoIcon" alt="Automobile" :class="iconClasses" />

        <!-- Animal -->
        <img v-else-if="icon === 'animal'" :src="animalIcon" alt="Animaux" :class="iconClasses" />

        <!-- Entreprise -->
        <img v-else-if="icon === 'entreprise'" :src="entrepriseIcon" alt="Entreprise" :class="iconClasses" />

        <!-- Video -->
        <img v-else-if="icon === 'video'" :src="videoIcon" alt="Vidéo" :class="iconClasses" />

        <!-- Camera (default) -->
        <svg v-else :class="iconClasses" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    </div>
</template>

<script setup lang="ts">
import {computed} from 'vue'
import type {ServiceIconType} from '@/types'

import sportIcon from '@/assets/icons/sport.svg'
import motoIcon from '@/assets/icons/moto.svg'
import animalIcon from '@/assets/icons/animal.svg'
import entrepriseIcon from '@/assets/icons/entreprise.svg'
import videoIcon from '@/assets/icons/video.svg'

interface Props {
    icon?: ServiceIconType
    size?: 'sm' | 'md' | 'lg'
    showBorder?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    icon: 'camera',
    size: 'md',
    showBorder: true,
})

const sizeMap = {
    sm: {wrapper: 'w-10 h-10', icon: 'w-5 h-5'},
    md: {wrapper: 'w-12 h-12', icon: 'w-6 h-6'},
    lg: {wrapper: 'w-16 h-16', icon: 'w-8 h-8'},
}

const wrapperClasses = computed(() => [
    sizeMap[props.size].wrapper,
    'flex items-center justify-center',
    props.showBorder ? 'border border-gold/30 group-hover:border-gold transition-colors' : '',
])

const iconClasses = computed(() => [
    sizeMap[props.size].icon,
    'text-gold',
])
</script>
