<template>
    <div class="pt-20 min-h-screen bg-cream">
        <!-- Loading state -->
        <div v-if="isLoading" class="flex items-center justify-center py-32">
            <div class="flex flex-col items-center gap-3">
                <svg class="animate-spin h-10 w-10 text-gold" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <span class="text-gray-500 font-light">Vérification du paiement...</span>
            </div>
        </div>

        <!-- Success state -->
        <template v-else-if="order && order.status === 'paid'">
            <section class="py-12 px-6 lg:px-12 bg-white border-b border-gold">
                <div class="max-w-4xl mx-auto text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h1 class="text-4xl md:text-5xl font-light mb-4">Merci pour votre commande !</h1>
                    <p class="text-gray-600 font-light">
                        Commande n° <strong class="text-gold">{{ order.order_number }}</strong>
                    </p>
                </div>
            </section>

            <section class="py-12 px-6 lg:px-12">
                <div class="max-w-2xl mx-auto">
                    <!-- Email confirmation -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-gold/10 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-900">Confirmation envoyée</h3>
                                <p class="text-sm text-gray-600 mt-1" v-html="confirmationMessage"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Print notice -->
                    <div v-if="order.has_prints" class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            <div>
                                <p class="font-medium text-amber-800">Tirages papier inclus</p>
                                <p class="text-sm text-amber-700 mt-1">
                                    Votre commande contient des tirages papier qui seront préparés et expédiés par la photographe. Vous serez contacté(e) pour les détails de livraison.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Order summary -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h2 class="font-medium text-gray-900 mb-4">Récapitulatif</h2>
                        <div class="divide-y divide-gray-100">
                            <div
                                v-for="item in order.items"
                                :key="item.id"
                                class="flex gap-4 py-3"
                            >
                                <div class="w-16 h-16 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                                    <img
                                        v-if="item.thumbnail_url || item.display_url"
                                        :src="item.thumbnail_url || item.display_url"
                                        :alt="item.photo_title || 'Photo'"
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900">{{ item.photo_title || 'Photo' }}</p>
                                    <p v-if="item.gallery_title" class="text-sm text-gray-500">{{ item.gallery_title }}</p>
                                    <span
                                        :class="[
                                            'inline-flex items-center mt-1 px-2 py-0.5 rounded text-xs font-medium',
                                            item.is_print
                                                ? 'bg-amber-100 text-amber-800'
                                                : 'bg-blue-100 text-blue-800'
                                        ]"
                                    >
                                        {{ item.product_type_label }}
                                    </span>
                                </div>
                                <div class="text-gold font-medium">
                                    {{ formatPrice(item.price) }}
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 mt-4 pt-4 flex justify-between">
                            <span class="font-medium">Total payé</span>
                            <span class="text-xl font-semibold text-gold">{{ formatPrice(order.total) }}</span>
                        </div>
                    </div>

                    <!-- Download section (only for digital items) -->
                    <div v-if="digitalItems.length > 0" class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="font-medium text-gray-900 mb-4">Télécharger vos photos numériques</h2>
                        <p class="text-sm text-gray-600 mb-4">
                            Cliquez sur les boutons ci-dessous pour télécharger vos photos en haute qualité.
                        </p>

                        <!-- Download all button -->
                        <button
                            v-if="digitalItems.length > 1"
                            @click="downloadAllPhotos"
                            :disabled="isDownloadingAll"
                            class="w-full mb-4 flex items-center justify-center gap-2 px-4 py-3 bg-gold text-white rounded-lg hover:bg-gold/90 transition-colors disabled:opacity-50"
                        >
                            <svg
                                v-if="isDownloadingAll"
                                class="animate-spin w-5 h-5"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>{{ isDownloadingAll ? 'Préparation du ZIP...' : 'Tout télécharger (ZIP)' }}</span>
                        </button>

                        <!-- Individual download buttons -->
                        <div class="space-y-3">
                            <button
                                v-for="item in digitalItems"
                                :key="item.id"
                                @click="downloadPhoto(item.id, item.photo_title)"
                                :disabled="downloadingItem === item.id"
                                class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors disabled:opacity-50"
                            >
                                <span class="text-sm">{{ item.photo_title || 'Photo' }}</span>
                                <svg
                                    v-if="downloadingItem === item.id"
                                    class="animate-spin w-5 h-5 text-gold"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <svg v-else class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Back home -->
                    <div class="text-center mt-8">
                        <router-link
                            to="/"
                            class="text-gold hover:underline"
                        >
                            Retour à l'accueil
                        </router-link>
                    </div>
                </div>
            </section>
        </template>

        <!-- Pending state -->
        <template v-else-if="order && order.status === 'pending'">
            <section class="py-12 px-6 lg:px-12">
                <div class="max-w-xl mx-auto text-center">
                    <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-light mb-4">Paiement en attente</h1>
                    <p class="text-gray-600 mb-6">
                        Votre paiement est en cours de traitement. Cette page se rafraîchira automatiquement.
                    </p>
                    <button
                        @click="checkPayment"
                        class="px-6 py-2 bg-gold text-white rounded-lg hover:bg-gold/90 transition-colors"
                    >
                        Vérifier le paiement
                    </button>
                </div>
            </section>
        </template>

        <!-- Failed state -->
        <template v-else-if="order && order.status === 'failed'">
            <section class="py-12 px-6 lg:px-12">
                <div class="max-w-xl mx-auto text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-light mb-4">Paiement échoué</h1>
                    <p class="text-gray-600 mb-6">
                        Le paiement n'a pas pu être effectué. Veuillez réessayer.
                    </p>
                    <router-link
                        to="/panier"
                        class="inline-block px-6 py-2 bg-gold text-white rounded-lg hover:bg-gold/90 transition-colors"
                    >
                        Retour au panier
                    </router-link>
                </div>
            </section>
        </template>

        <!-- Error state -->
        <template v-else-if="error">
            <section class="py-12 px-6 lg:px-12">
                <div class="max-w-xl mx-auto text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-light mb-4">Erreur</h1>
                    <p class="text-gray-600 mb-6">{{ error }}</p>
                    <router-link
                        to="/"
                        class="text-gold hover:underline"
                    >
                        Retour à l'accueil
                    </router-link>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { cartApi, type Order } from '@/services/cartApi'
import { API_CONFIG } from '@/config/constants'

const route = useRoute()

const order = ref<Order | null>(null)
const isLoading = ref(true)
const error = ref('')
const downloadingItem = ref<string | null>(null)
const isDownloadingAll = ref(false)
let pollInterval: number | null = null

const digitalItems = computed(() => {
    if (!order.value) return []
    return order.value.items.filter(item => !item.is_print)
})

const hasDigitalItems = computed(() => digitalItems.value.length > 0)

const hasPrintItems = computed(() => {
    if (!order.value) return false
    return order.value.has_prints
})

// Calcule le type de commande pour adapter le message
const orderType = computed(() => {
    if (hasDigitalItems.value && hasPrintItems.value) return 'mixed'
    if (hasDigitalItems.value) return 'digital'
    return 'print'
})

// Message de confirmation dynamique selon le type de commande
const confirmationMessage = computed(() => {
    if (!order.value) return ''

    switch (orderType.value) {
        case 'digital':
            return `Un email avec votre lien de téléchargement a été envoyé à <strong>${order.value.customer_email}</strong>.<br>Merci de conserver cet email.`
        case 'print':
            return `Un email de confirmation de votre commande a été envoyé à <strong>${order.value.customer_email}</strong>.<br>Merci de conserver cet email jusqu'à la réception de vos photos.`
        case 'mixed':
            return `Un email récapitulatif avec votre lien de téléchargement et les informations de livraison a été envoyé à <strong>${order.value.customer_email}</strong>.<br>Merci de conserver cet email.`
        default:
            return `Un email de confirmation a été envoyé à <strong>${order.value.customer_email}</strong>.`
    }
})

function formatPrice(price: number): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
    }).format(price)
}

async function loadOrder() {
    const orderId = route.params.id as string
    const token = route.query.token as string

    if (!orderId) {
        error.value = 'Commande non trouvée'
        isLoading.value = false
        return
    }

    try {
        // First check with SumUp callback if we have checkout data
        if (route.query.checkout_id || route.query.order) {
            await cartApi.handleSumUpCallback(
                route.query.checkout_id as string,
                route.query.order as string
            )
        }

        // Then get order details
        const response = await cartApi.getOrder(orderId, token)
        if (response.success) {
            order.value = response.order

            // If pending, start polling
            if (response.order.status === 'pending') {
                startPolling()
            }
        } else {
            error.value = 'Commande non trouvée'
        }
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Erreur lors du chargement'
    } finally {
        isLoading.value = false
    }
}

async function checkPayment() {
    if (!order.value) return

    try {
        const response = await cartApi.verifySumUpPayment(order.value.id)
        if (response.success && response.order) {
            // Reload order to get updated status
            await loadOrder()
        }
    } catch {
        // Payment verification failed
    }
}

function startPolling() {
    if (pollInterval) return

    pollInterval = window.setInterval(async () => {
        if (order.value?.status !== 'pending') {
            stopPolling()
            return
        }
        await checkPayment()
    }, 5000)
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval)
        pollInterval = null
    }
}

async function downloadPhoto(itemId: string, photoTitle?: string | null) {
    if (!order.value) return

    downloadingItem.value = itemId
    try {
        const token = route.query.token as string
        const response = await cartApi.downloadPhoto(order.value.id, itemId, token)
        if (response.success && response.download_url) {
            // Fetch the file as blob to force download
            const fileResponse = await fetch(response.download_url)
            const blob = await fileResponse.blob()

            // Create download link
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            link.download = response.filename || `${photoTitle || 'photo'}.jpg`
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            window.URL.revokeObjectURL(url)
        }
    } catch {
        alert('Erreur lors du téléchargement')
    } finally {
        downloadingItem.value = null
    }
}

async function downloadAllPhotos() {
    if (!order.value) return

    isDownloadingAll.value = true
    try {
        const token = route.query.token as string
        const params = token ? `?token=${encodeURIComponent(token)}` : ''

        // Get auth token if available
        const authToken = localStorage.getItem('auth_token')
        const headers: Record<string, string> = {}
        if (authToken) {
            headers['Authorization'] = `Bearer ${authToken}`
        }

        const response = await fetch(
            `${API_CONFIG.baseUrl}/orders/${order.value.id}/download-all${params}`,
            { headers }
        )

        if (!response.ok) {
            throw new Error('Erreur lors du téléchargement')
        }

        const blob = await response.blob()
        const url = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = `commande_${order.value.order_number}.zip`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
    } catch {
        alert('Erreur lors du téléchargement')
    } finally {
        isDownloadingAll.value = false
    }
}

onMounted(() => {
    loadOrder()
})

onUnmounted(() => {
    stopPolling()
})
</script>
