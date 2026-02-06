<template>
    <div class="min-h-screen bg-gray-50 pt-24 pb-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-light text-gray-900">
                    Bonjour, {{ authStore.user?.first_name }}
                </h1>
                <p class="text-gray-500 mt-1">Bienvenue dans votre espace client</p>
            </div>

            <!-- Loading state -->
            <div v-if="isLoading" class="flex items-center justify-center py-24">
                <div class="flex flex-col items-center gap-3">
                    <svg class="w-10 h-10 text-gold animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-gray-500 text-sm">Chargement...</span>
                </div>
            </div>

            <template v-else>
                <!-- Tabs -->
                <div class="flex gap-1 mb-6 bg-gray-100 p-1 rounded-xl w-full sm:w-fit overflow-x-auto">
                    <button
                        @click="activeTab = 'galleries'"
                        :class="[
                            'px-4 sm:px-6 py-2.5 text-sm font-medium rounded-lg transition-colors whitespace-nowrap flex-shrink-0',
                            activeTab === 'galleries'
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-600 hover:text-gray-900'
                        ]"
                    >
                        Mes galeries
                        <span v-if="galleries.length > 0" class="ml-1 sm:ml-2 text-xs bg-gold/10 text-gold px-2 py-0.5 rounded-full">
                            {{ galleries.length }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'reservations'"
                        :class="[
                            'px-4 sm:px-6 py-2.5 text-sm font-medium rounded-lg transition-colors whitespace-nowrap flex-shrink-0',
                            activeTab === 'reservations'
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-600 hover:text-gray-900'
                        ]"
                    >
                        Mes reservations
                        <span v-if="reservations.length > 0" class="ml-1 sm:ml-2 text-xs bg-gold/10 text-gold px-2 py-0.5 rounded-full">
                            {{ reservations.length }}
                        </span>
                    </button>
                </div>

                <!-- Galleries Tab -->
                <div v-if="activeTab === 'galleries'">
                    <!-- Empty state -->
                    <div v-if="galleries.length === 0" class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune galerie</h3>
                        <p class="text-gray-500 mb-6 max-w-sm mx-auto">
                            Vos galeries photos apparaitront ici une fois que je vous les aurai partagé.
                        </p>
                        <router-link
                            to="/gallery"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-gold text-white rounded-lg hover:bg-gold/90 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            Acceder avec un code
                        </router-link>
                    </div>

                    <!-- Galleries grid -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <GalleryCard
                            v-for="gallery in galleries"
                            :key="gallery.id"
                            :gallery="gallery"
                        />
                    </div>
                </div>

                <!-- Reservations Tab -->
                <div v-if="activeTab === 'reservations'">
                    <!-- Empty state -->
                    <div v-if="reservations.length === 0" class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune reservation</h3>
                        <p class="text-gray-500 mb-6 max-w-sm mx-auto">
                            Vous n'avez pas encore effectue de demande de reservation.
                        </p>
                        <router-link
                            to="/prestations"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-gold text-white rounded-lg hover:bg-gold/90 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Voir les prestations
                        </router-link>
                    </div>

                    <!-- Reservations list -->
                    <div v-else class="space-y-4">
                        <ReservationCard
                            v-for="reservation in reservations"
                            :key="reservation.id"
                            :reservation="reservation"
                        />
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/services/authApi'
import type { AccountGallery, AccountReservation } from '@/types/account'
import GalleryCard from './components/GalleryCard.vue'
import ReservationCard from './components/ReservationCard.vue'

const authStore = useAuthStore()

const isLoading = ref(true)
const activeTab = ref<'galleries' | 'reservations'>('galleries')
const galleries = ref<AccountGallery[]>([])
const reservations = ref<AccountReservation[]>([])

async function fetchDashboard() {
    isLoading.value = true
    try {
        const response = await authApi.getDashboard()
        if (response.success && response.data) {
            galleries.value = response.data.galleries
            reservations.value = response.data.reservations
        }
    } catch {
        // Failed to fetch dashboard
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    fetchDashboard()
})
</script>
