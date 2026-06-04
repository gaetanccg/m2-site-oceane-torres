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
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Prenom *
                                            </label>
                                            <input
                                                v-model="form.firstName"
                                                type="text"
                                                required
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="Votre prenom"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Nom *
                                            </label>
                                            <input
                                                v-model="form.lastName"
                                                type="text"
                                                required
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="Votre nom"
                                            />
                                        </div>
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

                                <!-- Bloc shipping (si au moins un tirage dans le panier) -->
                                <div v-if="cartStore.requiresShipping" class="mt-6 pt-6 border-t border-gray-100">
                                    <ShippingAddressFields
                                        v-model="shipping"
                                        :errors="shippingErrors"
                                    />
                                </div>

                                <!-- Account creation prompt (guests only) -->
                                <div v-if="!authStore.isAuthenticated" class="mt-6 bg-gold/5 border border-gold/20 rounded-lg p-4">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-gold mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <p class="text-sm text-gray-700">
                                                <strong>Vous avez un compte ?</strong>
                                                <button
                                                    type="button"
                                                    @click="$router.push({ path: '/', query: { login: 'true', redirect: '/checkout' } })"
                                                    class="text-gold hover:underline font-bold ml-1"
                                                >
                                                    Connectez-vous
                                                </button>
                                                pour retrouver cette commande dans votre espace client.
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Pas encore de compte ?
                                                <button
                                                    type="button"
                                                    @click="$router.push({ path: '/', query: { login: 'true', tab: 'register', redirect: '/checkout' } })"
                                                    class="text-gold hover:underline font-bold"
                                                >
                                                    Créez-en un gratuitement
                                                </button>
                                                pour accéder à vos achats et galeries.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- CGV Acceptance (RGPD) -->
                                <div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input
                                            v-model="form.cgv_accepted"
                                            type="checkbox"
                                            required
                                            class="mt-1 w-4 h-4 text-gold border-gray-300 rounded focus:ring-gold"
                                        />
                                        <span class="text-sm text-gray-600">
                                            <span class="text-red-500">*</span>
                                            J'ai lu et j'accepte les
                                            <router-link to="/cgv" target="_blank" class="text-gold hover:underline font-medium">
                                                Conditions Generales de Vente
                                            </router-link>
                                            et la
                                            <router-link to="/politique-confidentialite" target="_blank" class="text-gold hover:underline font-medium">
                                                Politique de confidentialite
                                            </router-link>.
                                        </span>
                                    </label>
                                </div>

                                <!-- Error message -->
                                <div v-if="error" class="mt-4 p-4 bg-red-50 text-red-600 rounded-lg text-sm">
                                    {{ error }}
                                </div>

                                <!-- Continue to payment button -->
                                <div class="mt-8 pt-6 border-t border-gray-100">
                                    <button
                                        @click="createOrder"
                                        :disabled="isProcessing || (!authStore.isAuthenticated && (!form.email || !form.firstName || !form.lastName)) || !form.cgv_accepted"
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

                            <!-- Step 2: Payment widget OR free order confirmation -->
                            <div v-else>
                                <div class="flex items-center justify-between mb-6">
                                    <h2 class="text-lg font-medium">{{ isFreeOrder ? 'Confirmer votre commande' : 'Paiement sécurisé' }}</h2>
                                    <button
                                        @click="resetPayment"
                                        class="text-sm text-gray-500 hover:text-gold"
                                    >
                                        Modifier mes informations
                                    </button>
                                </div>

                                <!-- Commande gratuite : double confirmation, pas de widget SumUp -->
                                <div v-if="isFreeOrder">
                                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                        <p class="text-sm text-green-800 font-medium">
                                            Votre code promo couvre l'intégralité de la commande.
                                        </p>
                                        <p class="text-sm text-green-700 mt-1">
                                            Montant à payer : <strong>0,00 €</strong> — aucun paiement ne vous sera demandé.
                                        </p>
                                    </div>
                                    <button
                                        @click="confirmFreeOrder"
                                        :disabled="isPaymentProcessing"
                                        class="w-full mt-6 py-3 bg-gold text-white font-medium rounded-lg hover:bg-gold/90 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                                    >
                                        <svg v-if="isPaymentProcessing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        <span>{{ isPaymentProcessing ? 'Confirmation...' : 'Confirmer ma commande' }}</span>
                                    </button>
                                </div>

                                <!-- SumUp Card Widget container -->
                                <div v-else id="sumup-card" class="min-h-[300px]"></div>

                                <!-- Error message -->
                                <div v-if="paymentError" class="mt-4 p-4 bg-red-50 text-red-600 rounded-lg text-sm">
                                    {{ paymentError }}
                                </div>

                                <!-- Payment processing indicator (SumUp) -->
                                <div v-if="isPaymentProcessing && !isFreeOrder" class="mt-4 flex items-center justify-center gap-2 text-gray-600">
                                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <span>Traitement du paiement en cours...</span>
                                </div>

                                <!-- Safety net : si la cliente a validé son 3DS mais que le widget reste figé
                                     (postMessage cross-origin échoué, in-app browser, etc.), elle peut forcer
                                     la vérification serveur depuis ici. -->
                                <div v-if="showManualVerify && !isPaymentProcessing && !isFreeOrder" class="mt-6 pt-4 border-t border-gray-100 text-center">
                                    <p class="text-xs text-gray-500 mb-2">
                                        La page ne se met pas à jour après votre paiement ?
                                    </p>
                                    <button
                                        @click="manualVerifyPayment"
                                        class="text-sm text-gold hover:underline"
                                    >
                                        Vérifier le statut de mon paiement
                                    </button>
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
                                            v-if="item.photo.thumbnail_url || item.photo.display_url"
                                            :src="item.photo.thumbnail_url || item.photo.display_url"
                                            :alt="item.photo.title || 'Photo'"
                                            class="w-full h-full object-cover"
                                        />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm truncate">
                                            {{ item.photo.title || 'Photo' }}
                                            <span v-if="item.quantity > 1" class="text-gray-500 text-xs">× {{ item.quantity }}</span>
                                        </p>
                                        <p class="text-sm text-gold">
                                            {{ formatPrice(item.line_total ?? item.price * item.quantity) }}
                                            <span v-if="item.quantity > 1" class="text-gray-400 text-xs ml-1">({{ item.quantity }} × {{ formatPrice(item.price) }})</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-gray-100 mt-4 pt-4 space-y-2 text-sm">
                                <div class="flex justify-between text-gray-600">
                                    <span>Sous-total</span>
                                    <span>{{ formatPrice(cartStore.subtotal) }}</span>
                                </div>
                                <div v-if="cartStore.discount > 0" class="flex justify-between text-green-700 font-medium">
                                    <span>Remise<template v-if="cartStore.appliedCode"> ({{ cartStore.appliedCode.code }})</template></span>
                                    <span>-{{ formatPrice(cartStore.discount) }}</span>
                                </div>
                                <div v-if="cartStore.shippingFee > 0" class="flex justify-between text-gray-600">
                                    <span>Frais de port</span>
                                    <span>+{{ formatPrice(cartStore.shippingFee) }}</span>
                                </div>
                                <div class="flex justify-between text-lg font-semibold pt-2 border-t border-gray-100">
                                    <span>Total</span>
                                    <!-- Toujours afficher le total live du panier, jamais celui d'une order
                                         précédente (qui pourrait être périmé si l'utilisateur a modifié son panier). -->
                                    <span class="text-gold">{{ formatPrice(cartStore.total) }}</span>
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
import {ref, reactive, nextTick, onMounted, onUnmounted, watch} from 'vue'
import {useRouter} from 'vue-router'
import {useCartStore} from '@/stores/cart'
import {useAuthStore} from '@/stores/auth'
import {cartApi, CartApiError, type ShippingAddress} from '@/services/cartApi'
import {useToast} from '@/composables/useToast'
import {useGtag} from '@/composables/useGtag'
import {formatPrice} from '@/utils/format'
import ShippingAddressFields from '@/components/cart/ShippingAddressFields.vue'

declare global {
    interface Window {
        SumUpCard: {
            mount: (config: {
                id: string
                checkoutId: string
                onResponse: (type: string, body: unknown) => void
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
const toast = useToast()
const {trackBeginCheckout} = useGtag()

const isProcessing = ref(false)
const isPaymentProcessing = ref(false)
const error = ref('')
const paymentError = ref('')
const showPaymentWidget = ref(false)
const isFreeOrder = ref(false)
const showManualVerify = ref(false)
const currentOrder = ref<{ id: string; order_number: string; total: number } | null>(null)
const checkoutId = ref('')

onMounted(async () => {
    // CRITIQUE : sync forcée du cart depuis le backend pour garantir que ce que l'utilisateur
    // voit ici correspond EXACTEMENT à ce qui sera facturé. Sans ça, un Pinia state stale
    // (issu d'un précédent test, d'un autre onglet, ou d'un merge guest→user incomplet)
    // peut afficher des items différents de ceux que la commande chargera.
    await cartStore.refresh()

    if (!cartStore.isEmpty) {
        trackBeginCheckout(
            cartStore.items.map(item => ({
                item_id: item.photo_id,
                item_name: item.photo.title || 'Photo',
                item_category: item.photo.gallery_title || undefined,
                price: item.price,
                quantity: item.quantity,
            })),
            cartStore.total
        )
    }
})

let sumupWidget: { unmount: () => void } | null = null
let pollTimeoutId: ReturnType<typeof setTimeout> | null = null
let manualVerifyTimerId: ReturnType<typeof setTimeout> | null = null
let authStuckTimerId: ReturnType<typeof setTimeout> | null = null
let isUnmounted = false

// Délai avant d'afficher le lien "vérifier mon paiement" sous le widget — laisse à la
// cliente le temps de remplir la carte sans la perturber, mais reste accessible si
// elle est bloquée après un 3DS qui n'a pas posté de message au parent.
const MANUAL_VERIFY_DELAY_MS = 20_000

// Délai après lequel on poll automatiquement le statut serveur si un écran 3DS a été
// affiché et qu'aucun onResponse de fin n'arrive — couvre les cas postMessage cassé
// (in-app browsers, certains Samsung Internet, popups bloquées).
const AUTH_STUCK_TIMEOUT_MS = 45_000

const form = reactive({
    firstName: '',
    lastName: '',
    email: '',
    cgv_accepted: false,
})

const shipping = ref<ShippingAddress>({
    shipping_phone: '',
    shipping_address_line1: '',
    shipping_address_line2: '',
    shipping_postal_code: '',
    shipping_city: '',
    shipping_country: 'FR',
})

const shippingErrors = ref<Partial<Record<keyof ShippingAddress, string>>>({})

const PHONE_REGEX = /^0[1-9]\d{8}$/
const POSTAL_REGEX = /^\d{5}$/

function validateShipping(): boolean {
    const errors: Partial<Record<keyof ShippingAddress, string>> = {}
    const s = shipping.value

    if (!s.shipping_phone?.trim()) {
        errors.shipping_phone = 'Numéro de téléphone requis'
    } else if (!PHONE_REGEX.test(s.shipping_phone.trim())) {
        errors.shipping_phone = 'Numéro français à 10 chiffres (ex: 0612345678)'
    }

    if (!s.shipping_address_line1?.trim()) {
        errors.shipping_address_line1 = 'Adresse requise'
    }

    if (!s.shipping_postal_code?.trim()) {
        errors.shipping_postal_code = 'Code postal requis'
    } else if (!POSTAL_REGEX.test(s.shipping_postal_code.trim())) {
        errors.shipping_postal_code = 'Code postal : 5 chiffres'
    }

    if (!s.shipping_city?.trim()) {
        errors.shipping_city = 'Ville requise'
    }

    shippingErrors.value = errors
    return Object.keys(errors).length === 0
}

function fillShippingFromUser(): void {
    const u = authStore.user
    if (!u) return
    shipping.value = {
        shipping_phone: u.phone ?? '',
        shipping_address_line1: u.address_line1 ?? '',
        shipping_address_line2: u.address_line2 ?? '',
        shipping_postal_code: u.postal_code ?? '',
        shipping_city: u.city ?? '',
        shipping_country: 'FR',
    }
}

watch(
    () => [cartStore.requiresShipping, authStore.isAuthenticated] as const,
    ([requiresShipping, isAuth]) => {
        if (requiresShipping && isAuth) fillShippingFromUser()
    },
    {immediate: true}
)

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
            onResponse: async (type: string, body: unknown) => {
                // Events réels exposés par le SDK SumUp (vérifiés dans le bundle sdk.js) :
                //   loaded · sent · invalid · auth-screen · success · fail · error
                // ⚠️ Le nom est `auth-screen` (pas `auth-screen-displayed`).

                // Watchdog : armé dès qu'un 3DS s'affiche OU que la carte est envoyée.
                // Si aucune réponse finale n'arrive dans le délai, on synchronise côté
                // serveur pour ne pas laisser la cliente bloquée (postMessage cassé,
                // in-app browser, popup fermée, etc.).
                if (type === 'auth-screen' || type === 'sent') {
                    if (authStuckTimerId) clearTimeout(authStuckTimerId)
                    authStuckTimerId = setTimeout(() => {
                        if (isUnmounted || !currentOrder.value) return
                        if (isPaymentProcessing.value) return
                        manualVerifyPayment()
                    }, AUTH_STUCK_TIMEOUT_MS)
                    return
                }

                // Réponse finale reçue — on désarme le watchdog.
                if (authStuckTimerId) {
                    clearTimeout(authStuckTimerId)
                    authStuckTimerId = null
                }

                if (type === 'success') {
                    // La doc SumUp précise que `success` ne garantit PAS que la
                    // transaction est PAID — on revérifie côté serveur.
                    isPaymentProcessing.value = true
                    paymentError.value = ''

                    try {
                        const result = await cartApi.verifySumUpPayment(currentOrder.value!.id)
                        if (result.status === 'paid') {
                            await cartStore.clearCart()
                            router.push(`/commande/${currentOrder.value!.id}`)
                        } else {
                            paymentError.value = 'Le paiement est en cours de vérification. Veuillez patienter...'
                            pollPaymentStatus()
                        }
                    } catch {
                        paymentError.value = 'Erreur lors de la vérification du paiement'
                        isPaymentProcessing.value = false
                    }
                } else if (type === 'fail') {
                    // Annulation utilisateur, timeout SumUp, ou échec côté checkout.
                    // Le widget reste monté : la cliente peut corriger et re-soumettre
                    // sans recharger la page.
                    const failBody = body as { message?: string } | undefined
                    paymentError.value = failBody?.message
                        || "Le paiement n'a pas abouti. Veuillez réessayer ou utiliser une autre carte."
                    isPaymentProcessing.value = false
                } else if (type === 'error') {
                    const errorBody = body as { message?: string }
                    paymentError.value = errorBody?.message || 'Erreur lors du paiement. Veuillez réessayer.'
                    isPaymentProcessing.value = false
                } else if (type === 'invalid') {
                    // Validation locale du SDK (carte/CVV invalide). Le widget affiche
                    // déjà son erreur inline ; on relâche juste isPaymentProcessing.
                    isPaymentProcessing.value = false
                }
            }
        })
    } catch {
        paymentError.value = 'Erreur lors du chargement du module de paiement'
    }
}

async function confirmFreeOrder() {
    if (!currentOrder.value || isPaymentProcessing.value) return

    isPaymentProcessing.value = true
    paymentError.value = ''

    try {
        const result = await cartApi.confirmFreeOrder(currentOrder.value.id)

        if (result.success) {
            toast.success('Commande confirmée', 'Votre commande a été validée, aucun paiement requis.')
            try {
                await cartStore.clearCart()
            } catch {
                // Best-effort : ne jamais bloquer la redirection.
            }
            // isPaymentProcessing reste true → onUnmounted n'annulera pas la commande payée.
            router.push(`/commande/${currentOrder.value.id}`)
            return
        }

        paymentError.value = result.message ?? 'La confirmation a échoué. Veuillez réessayer.'
        isPaymentProcessing.value = false
    } catch (e) {
        paymentError.value = e instanceof CartApiError
            ? e.apiError.message
            : 'La confirmation a échoué. Veuillez réessayer.'
        isPaymentProcessing.value = false
    }
}

// Filet de sécurité : la cliente peut forcer une vérification serveur si le widget
// SumUp reste figé après un 3DS validé. On reroute vers la page de commande qui
// possède déjà sa propre logique de polling/affichage (paid / pending / failed).
async function manualVerifyPayment() {
    if (!currentOrder.value || isPaymentProcessing.value) return

    isPaymentProcessing.value = true
    paymentError.value = ''

    try {
        const result = await cartApi.verifySumUpPayment(currentOrder.value.id)
        if (result.status === 'paid') {
            await cartStore.clearCart()
        }
        router.push(`/commande/${currentOrder.value.id}`)
    } catch {
        paymentError.value = "Impossible de vérifier le paiement pour le moment. Réessayez dans un instant."
        isPaymentProcessing.value = false
    }
}

// Poll payment status for pending payments
async function pollPaymentStatus(attempts = 0) {
    if (isUnmounted || !currentOrder.value) return

    if (attempts >= 10) {
        // Redirect mais NE PAS clear le cart : le paiement peut avoir failed.
        isPaymentProcessing.value = false
        toast.info('Vérification en cours', 'Redirection vers votre commande...')
        router.push(`/commande/${currentOrder.value.id}`)
        return
    }

    try {
        const result = await cartApi.verifySumUpPayment(currentOrder.value.id)
        if (isUnmounted || !currentOrder.value) return

        if (result.status === 'paid') {
            await cartStore.clearCart()
            router.push(`/commande/${currentOrder.value.id}`)
        } else {
            // On continue : un 'failed' transitoire est possible le temps qu'un retry passe.
            pollTimeoutId = setTimeout(() => pollPaymentStatus(attempts + 1), 2000)
        }
    } catch {
        if (isUnmounted || !currentOrder.value) return
        pollTimeoutId = setTimeout(() => pollPaymentStatus(attempts + 1), 2000)
    }
}

async function createOrder() {
    if (isProcessing.value) return
    if (!authStore.isAuthenticated && !form.email) {
        error.value = 'Veuillez renseigner votre email'
        return
    }
    if (!authStore.isAuthenticated && (!form.firstName || !form.lastName)) {
        error.value = 'Veuillez renseigner votre prenom et votre nom'
        return
    }
    if (!form.cgv_accepted) {
        error.value = 'Vous devez accepter les Conditions Generales de Vente'
        return
    }

    if (cartStore.requiresShipping && !validateShipping()) {
        error.value = 'Veuillez compléter l\'adresse de livraison pour vos tirages'
        return
    }

    isProcessing.value = true
    error.value = ''

    try {
        // Refresh dernière chance — garantir que cartStore reflète l'état serveur réel
        // avant de créer l'order. Si le panier a changé (autre onglet, merge tardif, etc.)
        // on bloque la création et on demande à l'utilisateur de revoir son panier.
        const previousTotal = cartStore.total
        await cartStore.refresh()
        if (Math.abs(cartStore.total - previousTotal) > 0.01) {
            error.value = "Votre panier a été mis à jour, vérifiez les montants avant de continuer."
            toast.error('Panier mis à jour', "Vérifiez les montants avant de continuer.")
            return
        }
        if (cartStore.isEmpty) {
            error.value = 'Votre panier est vide.'
            return
        }

        const shippingPayload: ShippingAddress | null = cartStore.requiresShipping
            ? {
                shipping_phone: shipping.value.shipping_phone.trim(),
                shipping_address_line1: shipping.value.shipping_address_line1.trim(),
                shipping_address_line2: shipping.value.shipping_address_line2?.trim() || null,
                shipping_postal_code: shipping.value.shipping_postal_code.trim(),
                shipping_city: shipping.value.shipping_city.trim(),
                shipping_country: 'FR',
            }
            : null

        const orderResponse = await cartApi.createOrder(
            authStore.isAuthenticated ? undefined : form.email,
            authStore.isAuthenticated ? undefined : form.firstName,
            authStore.isAuthenticated ? undefined : form.lastName,
            form.cgv_accepted,
            shippingPayload,
        )

        if (!orderResponse.success) {
            throw new Error('Erreur lors de la creation de la commande')
        }

        // Commande gratuite : reste `pending` jusqu'à la double confirmation. Pas de
        // garde-fou de totaux ici — l'écran de confirmation affiche lui-même le 0 €.
        if (orderResponse.payment.free) {
            currentOrder.value = {
                id: orderResponse.order.id,
                order_number: orderResponse.order.order_number,
                total: orderResponse.order.total,
            }
            isFreeOrder.value = true
            showPaymentWidget.value = true
            return
        }

        // Garde-fou critique (commandes payantes uniquement) : si le total renvoyé par le
        // serveur diffère du panier affiché (cart modifié dans un autre onglet entre le
        // refresh et le POST), on refuse de monter le widget — sinon l'utilisateur verrait
        // un montant et serait facturé d'un autre.
        if (Math.abs(orderResponse.order.total - cartStore.total) > 0.01) {
            cartApi.cancelCheckout(orderResponse.order.id).catch(() => { /* best-effort */ })
            await cartStore.refresh()
            error.value = "Votre panier a été modifié pendant la création de la commande. Vérifiez les montants puis recliquez sur « Continuer »."
            toast.error('Panier modifié', "Vérifiez les montants puis recliquez sur « Continuer ».")
            return
        }

        currentOrder.value = {
            id: orderResponse.order.id,
            order_number: orderResponse.order.order_number,
            total: orderResponse.order.total,
        }
        checkoutId.value = orderResponse.payment.checkout_id ?? ''

        if (authStore.isAuthenticated && cartStore.requiresShipping) {
            authStore.fetchUser().catch(() => {})
        }

        showPaymentWidget.value = true
        await nextTick()
        initPaymentWidget()

    } catch (e) {
        const msg = e instanceof Error ? e.message : 'Une erreur est survenue'
        error.value = msg
        toast.error('Erreur', msg)
    } finally {
        isProcessing.value = false
    }
}

async function resetPayment() {
    if (sumupWidget) {
        sumupWidget.unmount()
        sumupWidget = null
    }
    if (authStuckTimerId) {
        clearTimeout(authStuckTimerId)
        authStuckTimerId = null
    }

    if (currentOrder.value) {
        try {
            await cartApi.cancelCheckout(currentOrder.value.id)
        } catch {
            // best-effort
        }
    }

    showPaymentWidget.value = false
    isFreeOrder.value = false
    currentOrder.value = null
    checkoutId.value = ''
    paymentError.value = ''
}

// Affiche le lien "Vérifier le statut de mon paiement" après quelques secondes une fois
// le widget visible. On garde la première phase silencieuse pour ne pas distraire la
// cliente pendant la saisie, mais le filet reste accessible si elle se retrouve bloquée.
watch(showPaymentWidget, (visible) => {
    if (manualVerifyTimerId) {
        clearTimeout(manualVerifyTimerId)
        manualVerifyTimerId = null
    }
    showManualVerify.value = false

    if (visible) {
        manualVerifyTimerId = setTimeout(() => {
            showManualVerify.value = true
        }, MANUAL_VERIFY_DELAY_MS)
    }
})

// Quand le widget SumUp est chargé (showPaymentWidget=true) et que le panier change
// sous-jacent (ajout/retrait/modif de quantité dans un autre onglet ou via cartStore),
// on invalide automatiquement l'order en cours et on revient au formulaire. Sans ça,
// le widget reste figé sur l'ancien montant alors que le panier a évolué.
// `cartStore.subtotal` est un nombre qui résume tout le panier (somme price × qty).
watch(() => cartStore.subtotal, (newSubtotal, oldSubtotal) => {
    if (!showPaymentWidget.value || !currentOrder.value) return
    if (isPaymentProcessing.value) return  // ne pas interrompre un paiement en cours
    if (newSubtotal === oldSubtotal) return  // changement trivial (ex: refresh, prix identique)

    // Le panier a changé — l'order actuel ne reflète plus le panier. On nettoie tout.
    resetPayment().catch(() => { /* best-effort */ })
})

onUnmounted(() => {
    isUnmounted = true
    if (pollTimeoutId) {
        clearTimeout(pollTimeoutId)
        pollTimeoutId = null
    }
    if (manualVerifyTimerId) {
        clearTimeout(manualVerifyTimerId)
        manualVerifyTimerId = null
    }
    if (authStuckTimerId) {
        clearTimeout(authStuckTimerId)
        authStuckTimerId = null
    }
    if (sumupWidget) {
        sumupWidget.unmount()
        sumupWidget = null
    }

    // Si l'utilisateur quitte la page de paiement avec un order pending non payé,
    // on annule le checkout SumUp côté serveur (best-effort, fire-and-forget) pour
    // éviter qu'au retour le widget reprenne un ancien order avec l'ancien prix.
    if (currentOrder.value && !isPaymentProcessing.value) {
        cartApi.cancelCheckout(currentOrder.value.id).catch(() => { /* best-effort */ })
    }
})
</script>
