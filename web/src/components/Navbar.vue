<template>
    <nav
        :class="[
      'fixed top-0 left-0 right-0 z-50 transition-all duration-300',
      scrolled ? 'bg-white/80 backdrop-blur-md shadow-sm' : 'bg-white'
    ]"
    >
        <div class="max-w-7xl mx-auto px-6 lg:px-12">
            <div class="flex items-center justify-between h-20">
                <router-link
                    to="/"
                    class="flex items-center gap-3 text-2xl font-light tracking-wider hover:text-gold transition-colors"
                >
                    <img src="/logo-oceane.png" alt="Logo Océane" class="h-36 w-20 object-contain" />
                </router-link>

                <button
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="md:hidden p-2 hover:text-gold transition-colors"
                    aria-label="Menu"
                >
                    <IconClose v-if="mobileMenuOpen" class="w-6 h-6" />
                    <IconMenu v-else class="w-6 h-6" />
                </button>

                <div class="hidden md:flex items-center space-x-10">
                    <router-link
                        v-for="link in NAV_LINKS"
                        :key="link.path"
                        :to="link.path"
                        class="text-sm uppercase tracking-widest font-light hover:text-gold transition-colors"
                    >
                        {{ link.name }}
                    </router-link>

                    <a
                        :href="EXTERNAL_LINKS.privateGallery"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="ml-4 inline-flex items-center gap-2 px-4 py-2 bg-gold text-white rounded-md text-lg font-medium hover:opacity-90 transition-colors"
                        aria-label="Ouvrir ma Gallerie privée (nouvel onglet)"
                    >
                        Gallerie Privée
                    </a>
                </div>
            </div>
        </div>

        <Transition name="slide-fade">
            <div
                v-if="mobileMenuOpen"
                class="md:hidden bg-white border-t border-gray-100"
            >
                <div class="px-6 py-8 space-y-6">
                    <router-link
                        v-for="link in NAV_LINKS"
                        :key="link.path"
                        :to="link.path"
                        @click="mobileMenuOpen = false"
                        class="block text-lg font-light hover:text-gold transition-colors"
                    >
                        {{ link.name }}
                    </router-link>

                    <a
                        :href="EXTERNAL_LINKS.privateGallery"
                        target="_blank"
                        rel="noopener noreferrer"
                        @click="mobileMenuOpen = false"
                        class="block w-full text-center mt-2 px-4 py-3 bg-gold text-white rounded-md text-lg font-medium hover:opacity-90 transition-colors"
                        aria-label="Ouvrir ma librairie privée (nouvel onglet)"
                    >
                        Gallerie Privée
                    </a>
                </div>
            </div>
        </Transition>
    </nav>
</template>

<script setup lang="ts">
import {ref, onMounted, onUnmounted} from 'vue'
import {NAV_LINKS, EXTERNAL_LINKS} from '@/config/constants'
import {IconMenu, IconClose} from './icons'

const scrolled = ref(false)
const mobileMenuOpen = ref(false)

const handleScroll = () => {
    scrolled.value = window.scrollY > 50
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll)
})

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll)
})
</script>
