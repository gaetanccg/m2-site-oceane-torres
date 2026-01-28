<template>
    <section :class="sectionClasses">
        <div class="max-w-4xl mx-auto text-center">
            <h2 :class="titleClasses">{{ title }}</h2>
            <p v-if="subtitle" :class="subtitleClasses">{{ subtitle }}</p>

            <div :class="['flex flex-col sm:flex-row items-center justify-center gap-4', multipleButtons ? '' : 'mt-10']">
                <slot>
                    <!-- Primary button: router-link for internal, <a> for external -->
                    <router-link
                        v-if="isPrimaryInternal"
                        :to="primaryLink"
                        :class="primaryButtonClasses"
                    >
                        {{ primaryText }}
                    </router-link>
                    <a
                        v-else
                        :href="primaryLink"
                        :target="isExternal ? '_blank' : undefined"
                        :rel="isExternal ? 'noopener noreferrer' : undefined"
                        :class="primaryButtonClasses"
                    >
                        {{ primaryText }}
                    </a>

                    <!-- Secondary button: router-link for internal, <a> for external -->
                    <template v-if="secondaryText && secondaryLink">
                        <router-link
                            v-if="isSecondaryInternal"
                            :to="secondaryLink"
                            :class="secondaryButtonClasses"
                        >
                            {{ secondaryText }}
                        </router-link>
                        <a
                            v-else
                            :href="secondaryLink"
                            :target="isSecondaryExternal ? '_blank' : undefined"
                            :rel="isSecondaryExternal ? 'noopener noreferrer' : undefined"
                            :class="secondaryButtonClasses"
                        >
                            {{ secondaryText }}
                        </a>
                    </template>
                </slot>
            </div>
        </div>
    </section>
</template>

<script setup lang="ts">
import {computed} from 'vue'
import {SOCIAL_LINKS} from '@/config/constants'

interface Props {
    title: string
    subtitle?: string
    primaryText?: string
    primaryLink?: string
    secondaryText?: string
    secondaryLink?: string
    variant?: 'dark' | 'light'
    isExternal?: boolean
    isSecondaryExternal?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    primaryText: 'Me contacter',
    primaryLink: '/contact',
    secondaryText: 'Me contacter sur Instagram',
    secondaryLink: SOCIAL_LINKS[0].url,
    variant: 'dark',
    isExternal: false,
    isSecondaryExternal: true,
})

const multipleButtons = computed(() => !!props.secondaryText && !!props.secondaryLink)

// Check if link is internal (starts with /)
const isPrimaryInternal = computed(() => props.primaryLink.startsWith('/'))
const isSecondaryInternal = computed(() => props.secondaryLink?.startsWith('/') ?? false)

const sectionClasses = computed(() => [
    'py-16 px-6 lg:px-12',
    props.variant === 'dark' ? 'bg-black text-white' : 'bg-gray-50 text-black',
])

const titleClasses = computed(() => [
    'text-4xl md:text-5xl font-light mb-6',
])

const subtitleClasses = computed(() => [
    'font-light text-lg mb-10',
    props.variant === 'dark' ? 'text-gray-300' : 'text-gray-600',
])

const primaryButtonClasses = computed(() => [
    'w-full sm:w-auto inline-block px-10 py-4 text-sm uppercase tracking-widest font-medium transition-all duration-300',
    'bg-gold text-black hover:bg-gold/90',
])

const secondaryButtonClasses = computed(() => [
    'w-full sm:w-auto inline-block px-10 py-4 border-2 text-sm uppercase tracking-widest font-light transition-all duration-300',
    'border-gold text-gold hover:bg-gold hover:text-black',
])
</script>
