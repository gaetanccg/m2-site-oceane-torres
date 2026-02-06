<template>
    <Teleport to="body" v-if="isMounted">
        <Transition name="slide-up">
            <div
                v-if="consentStore.showBanner"
                class="fixed bottom-0 left-0 right-0 z-[100] bg-white border-t border-gray-200 shadow-lg"
            >
                <div class="max-w-6xl mx-auto px-4 py-4 sm:px-6 sm:py-6">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <!-- Texte -->
                        <div class="flex-1">
                            <h3 class="text-lg font-medium text-gray-900 mb-1">
                                Gestion des cookies
                            </h3>
                            <p class="text-sm text-gray-600 leading-relaxed">
                                Nous utilisons des cookies pour ameliorer votre experience sur notre site et analyser notre trafic.
                                Les cookies essentiels sont necessaires au fonctionnement du site.
                                <button
                                    @click="consentStore.openSettings"
                                    class="text-gold hover:underline font-medium"
                                >
                                    En savoir plus
                                </button>
                            </p>
                        </div>

                        <!-- Boutons -->
                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 lg:flex-shrink-0">
                            <button
                                @click="consentStore.openSettings"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors order-3 sm:order-1"
                            >
                                Personnaliser
                            </button>
                            <button
                                @click="consentStore.rejectAll"
                                class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 rounded-lg transition-colors order-2"
                            >
                                Tout refuser
                            </button>
                            <button
                                @click="consentStore.acceptAll"
                                class="px-4 py-2 text-sm font-medium text-white bg-gold hover:bg-gold/90 rounded-lg transition-colors order-1 sm:order-3"
                            >
                                Tout accepter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal de parametres -->
        <Transition name="modal">
            <div
                v-if="consentStore.showSettings"
                class="fixed inset-0 z-[101] flex items-center justify-center p-4"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                    @click="consentStore.closeSettings"
                />

                <!-- Modal -->
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-hidden">
                    <!-- Header -->
                    <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <h2 class="text-xl font-medium text-gray-900">Parametres des cookies</h2>
                        <button
                            @click="consentStore.closeSettings"
                            class="p-2 text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
                        <p class="text-sm text-gray-600 mb-6">
                            Gerez vos preferences de cookies. Les cookies essentiels sont necessaires au fonctionnement du site
                            et ne peuvent pas etre desactives.
                        </p>

                        <!-- Cookie categories -->
                        <div class="space-y-4">
                            <!-- Essentiels -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-900">Essentiels</h4>
                                            <p class="text-xs text-gray-500">Toujours actifs</p>
                                        </div>
                                    </div>
                                    <div class="relative">
                                        <input
                                            type="checkbox"
                                            checked
                                            disabled
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-green-500 rounded-full"></div>
                                        <div class="absolute left-[22px] top-[2px] bg-white w-5 h-5 rounded-full shadow"></div>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600">
                                    Ces cookies sont indispensables au fonctionnement du site (session, panier, authentification).
                                </p>
                            </div>

                            <!-- Analytics -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-900">Analytiques</h4>
                                            <p class="text-xs text-gray-500">Google Analytics</p>
                                        </div>
                                    </div>
                                    <label class="relative cursor-pointer">
                                        <input
                                            v-model="localAnalytics"
                                            type="checkbox"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-gray-300 peer-checked:bg-gold rounded-full transition-colors"></div>
                                        <div class="absolute left-[2px] peer-checked:left-[22px] top-[2px] bg-white w-5 h-5 rounded-full shadow transition-all"></div>
                                    </label>
                                </div>
                                <p class="text-sm text-gray-600">
                                    Ces cookies nous permettent de mesurer l'audience du site et d'ameliorer son contenu.
                                </p>
                            </div>

                            <!-- Marketing -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-900">Marketing</h4>
                                            <p class="text-xs text-gray-500">Publicites ciblees</p>
                                        </div>
                                    </div>
                                    <label class="relative cursor-pointer">
                                        <input
                                            v-model="localMarketing"
                                            type="checkbox"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-gray-300 peer-checked:bg-gold rounded-full transition-colors"></div>
                                        <div class="absolute left-[2px] peer-checked:left-[22px] top-[2px] bg-white w-5 h-5 rounded-full shadow transition-all"></div>
                                    </label>
                                </div>
                                <p class="text-sm text-gray-600">
                                    Ces cookies permettent d'afficher des publicites pertinentes sur d'autres sites.
                                </p>
                            </div>
                        </div>

                        <!-- Links -->
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <div class="flex flex-wrap gap-4 text-sm">
                                <router-link
                                    to="/politique-confidentialite"
                                    class="text-gold hover:underline"
                                    @click="consentStore.closeSettings"
                                >
                                    Politique de confidentialite
                                </router-link>
                                <router-link
                                    to="/mentions-legales"
                                    class="text-gold hover:underline"
                                    @click="consentStore.closeSettings"
                                >
                                    Mentions legales
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex flex-col sm:flex-row gap-2 sm:gap-3 sm:justify-end">
                        <button
                            @click="rejectAllFromSettings"
                            class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                            Tout refuser
                        </button>
                        <button
                            @click="saveFromSettings"
                            class="px-4 py-2 text-sm font-medium text-white bg-gold hover:bg-gold/90 rounded-lg transition-colors"
                        >
                            Enregistrer mes choix
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useConsentStore } from '@/stores/consent'

const consentStore = useConsentStore()

// Flag to prevent prerender duplication - only render after client-side mount
// Check if we're in prerendering mode (Puppeteer sets window.__PRERENDERING__)
const isPrerendering = typeof window !== 'undefined' && (window as typeof globalThis & { __PRERENDERING__?: boolean }).__PRERENDERING__ === true
const isMounted = ref(false)

// Local state for settings modal
const localAnalytics = ref(false)
const localMarketing = ref(false)

// Sync local state with store when opening settings
watch(() => consentStore.showSettings, (isOpen) => {
    if (isOpen) {
        localAnalytics.value = consentStore.preferences.analytics
        localMarketing.value = consentStore.preferences.marketing
    }
})

function saveFromSettings() {
    consentStore.savePreferences(localAnalytics.value, localMarketing.value)
}

function rejectAllFromSettings() {
    localAnalytics.value = false
    localMarketing.value = false
    consentStore.rejectAll()
}

// Initialize consent store on mount and enable rendering (skip during prerender)
onMounted(() => {
    if (isPrerendering) return
    isMounted.value = true
    consentStore.initialize()
})
</script>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .relative,
.modal-leave-active .relative {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
    transform: scale(0.95);
    opacity: 0;
}
</style>
