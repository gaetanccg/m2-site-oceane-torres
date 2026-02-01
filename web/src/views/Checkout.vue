<template>
    <div class="pt-20 min-h-screen bg-cream">
        <!-- Header -->
        <section class="py-12 px-6 lg:px-12 bg-white border-b border-gold">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl md:text-5xl font-light mb-4">Finaliser la commande</h1>
            </div>
        </section>

        <!-- Content -->
        <section class="py-12 px-6 lg:px-12">
            <div class="max-w-4xl mx-auto">
                <!-- Empty cart redirect -->
                <div v-if="cartStore.isEmpty && !isProcessing && !showPaymentWidget" class="text-center py-16">
                    <p class="text-gray-600 mb-4">Votre panier est vide</p>
                    <router-link
                        to="/panier"
                        class="text-gold hover:underline"
                    >
                        Retour au panier
                    </router-link>
                </div>

                <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                    <!-- Form -->
                    <div class="lg:col-span-2 order-2 lg:order-1">
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <!-- Step 1: Customer info -->
                            <div v-if="!showPaymentWidget">
                                <h2 class="text-lg font-medium mb-6">Vos informations</h2>

                                <!-- If authenticated -->
                                <div v-if="authStore.isAuthenticated" class="mb-6 p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-600">
                                        Connecté en tant que
                                        <strong>{{ authStore.user?.email }}</strong>
                                    </p>
                                </div>

                                <!-- Guest form -->
                                <form v-else @submit.prevent="createOrder" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Nom complet
                                        </label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-transparent"
                                            placeholder="Votre nom"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Email *
                                        </label>
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-transparent"
                                            placeholder="votre@email.com"
                                        />
                                        <p class="mt-1 text-xs text-gray-500">
                                            Vous recevrez le lien de téléchargement à cette adresse
                                        </p>
                                    </div>
                                </form>

                                <!-- Error message -->
                                <div v-if="error" class="mt-4 p-4 bg-red-50 text-red-600 rounded-lg text-sm">
                                    {{ error }}
                                </div>

                                <!-- Continue to payment button -->
                                <div class="mt-8 pt-6 border-t border-gray-100">
                                    <button
                                        @click="createOrder"
                                        :disabled="isProcessing || (!authStore.isAuthenticated && !form.email)"
                                        class="w-full py-3 bg-gold text-white font-medium rounded-lg hover:bg-gold/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                                    >
                                        <svg
                                            v-if="isProcessing"
                                            class="animate-spin h-5 w-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        <span>{{ isProcessing ? 'Préparation...' : 'Continuer vers le paiement' }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 2: Payment widget -->
                            <div v-else>
                                <div class="flex items-center justify-between mb-6">
                                    <h2 class="text-lg font-medium">Paiement sécurisé</h2>
                                    <button
                                        @click="resetPayment"
                                        class="text-sm text-gray-500 hover:text-gold"
                                    >
                                        Modifier mes informations
                                    </button>
                                </div>

                                <!-- SumUp Card Widget container -->
                                <div id="sumup-card" class="min-h-[300px]"></div>

                                <!-- Error message -->
                                <div v-if="paymentError" class="mt-4 p-4 bg-red-50 text-red-600 rounded-lg text-sm">
                                    {{ paymentError }}
                                </div>

                                <!-- Payment processing indicator -->
                                <div v-if="isPaymentProcessing" class="mt-4 flex items-center justify-center gap-2 text-gray-600">
                                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <span>Traitement du paiement en cours...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="lg:col-span-1 order-1 lg:order-2">
                        <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 lg:sticky lg:top-24">
                            <h2 class="text-lg font-medium mb-4">Votre commande</h2>

                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                <div
                                    v-for="item in cartStore.items"
                                    :key="item.id"
                                    class="flex gap-3"
                                >
                                    <div class="w-12 h-12 flex-shrink-0 rounded overflow-hidden bg-gray-100">
                                        <img
                                            v-if="item.photo.display_url"
                                            :src="item.photo.display_url"
                                            :alt="item.photo.title || 'Photo'"
                                            class="w-full h-full object-cover"
                                        />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm truncate">{{ item.photo.title || 'Photo' }}</p>
                                        <p class="text-sm text-gold">{{ formatPrice(item.price) }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-gray-100 mt-4 pt-4">
                                <div class="flex justify-between text-lg font-semibold">
                                    <span>Total</span>
                                    <span class="text-gold">{{ formatPrice(currentOrder?.total ?? cartStore.total) }}</span>
                                </div>
                            </div>

                            <!-- Order number if created -->
                            <div v-if="currentOrder" class="mt-4 pt-4 border-t border-gray-100">
                                <p class="text-xs text-gray-500">
                                    Commande n° <strong>{{ currentOrder.order_number }}</strong>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { cartApi } from '@/services/cartApi'

declare global {
    interface Window {
        SumUpCard: {
            mount: (config: {
                id: string
                checkoutId: string
                onResponse: (type: string, body: unknown) => void
                onLoad?: () => void
                showSubmitButton?: boolean
                showFooter?: boolean
                showEmail?: boolean
                showInstallments?: boolean
                locale?: string
            }) => { unmount: () => void }
        }
    }
}

const router = useRouter()
const cartStore = useCartStore()
const authStore = useAuthStore()

const isProcessing = ref(false)
const isPaymentProcessing = ref(false)
const error = ref('')
const paymentError = ref('')
const showPaymentWidget = ref(false)
const currentOrder = ref<{ id: string; order_number: string; total: number } | null>(null)
const checkoutId = ref('')

let sumupWidget: { unmount: () => void } | null = null

const form = reactive({
    name: '',
    email: '',
})

function formatPrice(price: number): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
    }).format(price)
}

// Load SumUp SDK
function loadSumUpSDK(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (window.SumUpCard) {
            resolve()
            return
        }

        const script = document.createElement('script')
        script.src = 'https://gateway.sumup.com/gateway/ecom/card/v2/sdk.js'
        script.async = true
        script.onload = () => resolve()
        script.onerror = () => reject(new Error('Failed to load SumUp SDK'))
        document.head.appendChild(script)
    })
}

// Initialize the payment widget
async function initPaymentWidget() {
    if (!checkoutId.value) return

    try {
        await loadSumUpSDK()

        sumupWidget = window.SumUpCard.mount({
            id: 'sumup-card',
            checkoutId: checkoutId.value,
            locale: 'fr-FR',
            showSubmitButton: true,
            showFooter: true,
            showEmail: false,
            showInstallments: false,
            onLoad: () => {
                console.log('SumUp widget loaded')
            },
            onResponse: async (type: string, body: unknown) => {
                console.log('SumUp response:', type, body)

                if (type === 'success') {
                    isPaymentProcessing.value = true
                    paymentError.value = ''

                    // Verify payment and redirect
                    try {
                        const result = await cartApi.verifySumUpPayment(currentOrder.value!.id)
                        if (result.status === 'paid' || result.status === 'PAID') {
                            // Clear cart and redirect to confirmation
                            await cartStore.clearCart()
                            router.push(`/commande/${currentOrder.value!.id}`)
                        } else {
                            paymentError.value = 'Le paiement est en cours de verification. Veuillez patienter...'
                            // Poll for status
                            pollPaymentStatus()
                        }
                    } catch {
                        paymentError.value = 'Erreur lors de la verification du paiement'
                        isPaymentProcessing.value = false
                    }
                } else if (type === 'error') {
                    const errorBody = body as { message?: string }
                    paymentError.value = errorBody?.message || 'Erreur lors du paiement. Veuillez reessayer.'
                    isPaymentProcessing.value = false
                }
            }
        })
    } catch {
        paymentError.value = 'Erreur lors du chargement du module de paiement'
    }
}

// Poll payment status for pending payments
async function pollPaymentStatus(attempts = 0) {
    if (attempts >= 10) {
        paymentError.value = 'Impossible de confirmer le paiement. Verifiez votre email pour le statut.'
        isPaymentProcessing.value = false
        return
    }

    try {
        const result = await cartApi.verifySumUpPayment(currentOrder.value!.id)
        if (result.status === 'paid' || result.status === 'PAID') {
            await cartStore.clearCart()
            router.push(`/commande/${currentOrder.value!.id}`)
        } else if (result.status === 'failed' || result.status === 'FAILED') {
            paymentError.value = 'Le paiement a echoue. Veuillez reessayer.'
            isPaymentProcessing.value = false
        } else {
            // Continue polling
            setTimeout(() => pollPaymentStatus(attempts + 1), 2000)
        }
    } catch {
        setTimeout(() => pollPaymentStatus(attempts + 1), 2000)
    }
}

// Create order and show payment widget
async function createOrder() {
    if (isProcessing.value) return
    if (!authStore.isAuthenticated && !form.email) {
        error.value = 'Veuillez renseigner votre email'
        return
    }

    isProcessing.value = true
    error.value = ''

    try {
        const orderResponse = await cartApi.createOrder(
            authStore.isAuthenticated ? undefined : form.email,
            authStore.isAuthenticated ? undefined : form.name
        )

        if (!orderResponse.success) {
            throw new Error('Erreur lors de la creation de la commande')
        }

        currentOrder.value = {
            id: orderResponse.order.id,
            order_number: orderResponse.order.order_number,
            total: orderResponse.order.total,
        }
        checkoutId.value = orderResponse.payment.checkout_id

        // Show payment widget
        showPaymentWidget.value = true

        // Wait for DOM update then init widget
        setTimeout(() => {
            initPaymentWidget()
        }, 100)

    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Une erreur est survenue'
    } finally {
        isProcessing.value = false
    }
}

// Reset to info form
function resetPayment() {
    if (sumupWidget) {
        sumupWidget.unmount()
        sumupWidget = null
    }
    showPaymentWidget.value = false
    currentOrder.value = null
    checkoutId.value = ''
    paymentError.value = ''
}

onUnmounted(() => {
    if (sumupWidget) {
        sumupWidget.unmount()
    }
})
</script>
