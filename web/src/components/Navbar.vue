<template>
    <nav
        :class="[
            'fixed top-0 left-0 right-0 z-50 transition-all duration-300',
            scrolled ? 'bg-white/80 backdrop-blur-md shadow-sm' : 'bg-white'
        ]"
    >
        <div class="max-w-7xl mx-auto px-6 lg:px-12">
            <div class="flex items-center justify-between h-20">
                <!-- Logo -->
                <router-link
                    to="/"
                    class="flex items-center text-2xl font-light tracking-wider hover:text-gold transition-colors"
                >
                    <img src="/logo-oceane.png" alt="Logo Océane" class="h-36 w-20 object-contain" />
                </router-link>

                <!-- Mobile menu button -->
                <button
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="md:hidden p-2 hover:text-gold transition-colors"
                    aria-label="Menu"
                >
                    <IconClose v-if="mobileMenuOpen" class="w-6 h-6" />
                    <IconMenu v-else class="w-6 h-6" />
                </button>

                <!-- Desktop navigation - épurée -->
                <div class="hidden md:flex items-center space-x-8">
                    <router-link
                        v-for="link in NAV_LINKS"
                        :key="link.path"
                        :to="link.path"
                        class="text-sm uppercase tracking-widest font-light hover:text-gold transition-colors"
                    >
                        {{ link.name }}
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
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

                    <!-- Ma galerie button (always visible) -->
                    <router-link
                        to="/gallery"
                        @click="mobileMenuOpen = false"
                        class="flex items-center justify-center gap-2 w-full px-4 py-3 border-2 border-gold text-gold rounded-lg text-lg font-medium hover:bg-gold hover:text-white transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        Galerie
                    </router-link>

                    <!-- Mobile: Espace Client section -->
                    <div class="pt-4 border-t border-gray-100 space-y-4">
                        <template v-if="authStore.isAuthenticated">
                            <div class="text-sm text-gray-500 mb-2">
                                Connecte en tant que <strong>{{ authStore.userFullName }}</strong>
                            </div>
                            <router-link
                                to="/mon-compte"
                                @click="mobileMenuOpen = false"
                                class="block text-lg font-light hover:text-gold transition-colors"
                            >
                                Mon espace
                            </router-link>
                            <button
                                @click="handleLogout"
                                class="block text-lg font-light text-red-600 hover:text-red-700 transition-colors"
                            >
                                Deconnexion
                            </button>
                        </template>
                        <template v-else>
                            <button
                                @click="openAuthModal"
                                class="w-full text-center px-4 py-3 bg-gold text-white rounded-lg text-lg font-medium hover:bg-gold/90 transition-colors"
                            >
                                Espace Client
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </Transition>
    </nav>

    <!-- Sidebar flottante droite (Desktop) - Indépendante de la navbar -->
    <div class="hidden md:flex fixed right-4 top-1/2 -translate-y-1/2 z-50 flex-col gap-3">
        <!-- Bouton Galerie -->
        <router-link
            to="/gallery"
            class="group relative flex items-center justify-center w-12 h-12 bg-white border-2 border-gold text-gold rounded-full shadow-lg hover:bg-gold hover:text-white transition-all duration-300"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            <!-- Tooltip -->
            <span class="absolute right-full mr-3 px-3 py-1.5 bg-gray-900 text-white text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                Ma galerie
            </span>
        </router-link>

        <!-- Bouton Espace Client -->
        <div class="relative" ref="sidebarUserMenuRef">
            <button
                @click="handleEspaceClientClick"
                class="group relative flex items-center justify-center w-12 h-12 bg-gold text-white rounded-full shadow-lg hover:bg-gold/90 transition-all duration-300"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <!-- Tooltip (caché quand menu ouvert) -->
                <span
                    v-if="!userMenuOpen"
                    class="absolute right-full mr-3 px-3 py-1.5 bg-gray-900 text-white text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none"
                >
                    {{ authStore.isAuthenticated ? authStore.user?.first_name : 'Espace Client' }}
                </span>
            </button>

            <!-- Dropdown menu for authenticated users -->
            <Transition name="dropdown">
                <div
                    v-if="authStore.isAuthenticated && userMenuOpen"
                    class="absolute right-full mr-3 top-0 w-56 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-50"
                >
                    <div class="px-4 py-3 border-b border-gray-100">
                        <p class="text-sm font-medium text-gray-900">{{ authStore.userFullName }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ authStore.user?.email }}</p>
                    </div>
                    <div class="py-1">
                        <router-link
                            to="/mon-compte"
                            @click="userMenuOpen = false"
                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Mon espace
                        </router-link>
                    </div>
                    <div class="border-t border-gray-100 py-1">
                        <button
                            @click="handleLogout"
                            class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Deconnexion
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </div>

    <!-- Auth Modal -->
    <AuthModal v-model="showAuthModal" />
</template>

<script setup lang="ts">
import {ref, onMounted, onUnmounted, watch} from 'vue'
import {useRouter, useRoute} from 'vue-router'
import {NAV_LINKS} from '@/config/constants'
import {IconMenu, IconClose} from './icons'
import {useAuthStore} from '@/stores/auth'
import AuthModal from '@/components/auth/AuthModal.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const scrolled = ref(false)
const mobileMenuOpen = ref(false)
const showAuthModal = ref(false)
const userMenuOpen = ref(false)
const sidebarUserMenuRef = ref<HTMLElement | null>(null)

const handleScroll = () => {
    scrolled.value = window.scrollY > 50
}

function handleClickOutside(event: MouseEvent) {
    if (sidebarUserMenuRef.value && !sidebarUserMenuRef.value.contains(event.target as Node)) {
        userMenuOpen.value = false
    }
}

function handleEspaceClientClick() {
    if (authStore.isAuthenticated) {
        userMenuOpen.value = !userMenuOpen.value
    } else {
        showAuthModal.value = true
    }
}

function openAuthModal() {
    mobileMenuOpen.value = false
    showAuthModal.value = true
}

async function handleLogout() {
    userMenuOpen.value = false
    mobileMenuOpen.value = false
    await authStore.logout()
    router.push('/')
}

// Open auth modal when redirected with ?login=true
watch(() => route.query.login, (loginQuery) => {
    if (loginQuery === 'true' && !authStore.isAuthenticated) {
        showAuthModal.value = true
        // Remove the query param to clean up the URL
        router.replace({query: {...route.query, login: undefined, redirect: route.query.redirect}})
    }
}, {immediate: true})

onMounted(() => {
    window.addEventListener('scroll', handleScroll)
    document.addEventListener('click', handleClickOutside)
    if (authStore.token) {
        authStore.checkAuth()
    }
})

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active{
    transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to{
    opacity: 0;
    transform: translateY(-10px);
}

.slide-fade-enter-active,
.slide-fade-leave-active{
    transition: all 0.3s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to{
    opacity: 0;
    transform: translateY(-10px);
}
</style>
