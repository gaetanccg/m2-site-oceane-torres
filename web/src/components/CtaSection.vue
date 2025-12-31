<template>
    <section :class="sectionClasses">
        <div class="max-w-4xl mx-auto text-center">
            <h2 :class="titleClasses">{{ title }}</h2>
            <p v-if="subtitle" :class="subtitleClasses">{{ subtitle }}</p>

            <div :class="['flex justify-center', multipleButtons ? 'flex-wrap gap-4' : 'mt-10']">
                <slot>
                    <a
                        :href="primaryLink"
                        :target="isExternal ? '_blank' : undefined"
                        :rel="isExternal ? 'noopener noreferrer' : undefined"
                        :class="primaryButtonClasses"
                    >
                        {{ primaryText }}
                    </a>

                    <a
                        v-if="secondaryText && secondaryLink"
                        :href="secondaryLink"
                        :target="isSecondaryExternal ? '_blank' : undefined"
                        :rel="isSecondaryExternal ? 'noopener noreferrer' : undefined"
                        :class="secondaryButtonClasses"
                    >
                        {{ secondaryText }}
                    </a>
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
    primaryText: 'Me contacter sur Instagram',
    primaryLink: SOCIAL_LINKS[0].url,
    variant: 'dark',
    isExternal: true,
    isSecondaryExternal: false,
})

const multipleButtons = computed(() => !!props.secondaryText && !!props.secondaryLink)

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
    'inline-block px-10 py-4 text-sm uppercase tracking-widest font-light transition-all duration-300',
    props.variant === 'dark'
        ? 'border-2 border-gold text-gold hover:bg-gold hover:text-black'
        : 'bg-black text-white hover:bg-gold',
])

const secondaryButtonClasses = computed(() => [
    'inline-block px-6 py-3 border-2 text-sm uppercase tracking-widest font-light transition-all duration-300',
    props.variant === 'dark'
        ? 'border-gold text-gold bg-white hover:bg-gold hover:text-black'
        : 'border-black text-black hover:bg-black hover:text-white',
])
</script>
