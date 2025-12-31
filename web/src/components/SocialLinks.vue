<template>
    <div :class="['flex', horizontal ? 'space-x-4' : 'flex-col space-y-3']">
        <a
            v-for="link in SOCIAL_LINKS"
            :key="link.name"
            :href="link.url"
            target="_blank"
            rel="noopener noreferrer"
            :class="linkClasses"
            :aria-label="link.ariaLabel"
        >
            <component :is="getIconComponent(link.icon)" :class="iconClass" />
            <span v-if="showLabels" class="ml-2">{{ link.name }}</span>
        </a>
    </div>
</template>

<script setup lang="ts">
import {SOCIAL_LINKS} from '@/config/constants'
import {IconInstagram, IconTiktok, IconLinkedin} from './icons'
import type {Component} from 'vue'

interface Props {
    horizontal?: boolean
    showLabels?: boolean
    variant?: 'light' | 'dark'
    size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
    horizontal: true,
    showLabels: false,
    variant: 'dark',
    size: 'md',
})

const iconComponents: Record<string, Component> = {
    instagram: IconInstagram,
    tiktok: IconTiktok,
    linkedin: IconLinkedin,
}

const getIconComponent = (icon: string) => iconComponents[icon] || IconInstagram

const sizeClasses = {
    sm: 'w-8 h-8',
    md: 'w-10 h-10',
    lg: 'w-12 h-12',
}

const iconSizeClasses = {
    sm: 'w-4 h-4',
    md: 'w-5 h-5',
    lg: 'w-6 h-6',
}

const linkClasses = [
    sizeClasses[props.size],
    'flex items-center justify-center transition-colors',
    props.variant === 'dark'
        ? 'bg-white/10 hover:bg-gold text-white'
        : 'bg-gray-100 hover:bg-gold hover:text-white text-gray-600',
]

const iconClass = iconSizeClasses[props.size]
</script>
