<template>
    <div class="min-h-screen bg-gray-50 pt-24 pb-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Back link -->
            <router-link
                to="/mon-compte"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gold transition-colors mb-6"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Retour a mon compte
            </router-link>

            <!-- Loading -->
            <div v-if="isLoading" class="flex items-center justify-center py-24">
                <svg class="w-10 h-10 text-gold animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
            </div>

            <!-- Error -->
            <div v-else-if="error" class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <p class="text-gray-600 mb-4">{{ error }}</p>
                <router-link to="/mon-compte" class="text-gold hover:underline">Retour</router-link>
            </div>

            <template v-else-if="order">
                <!-- Header -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h1 class="text-2xl font-light text-gray-900 mb-1">
                                Commande {{ order.order_number }}
                            </h1>
                            <p class="text-sm text-gray-500">
                                {{ formatDate(order.created_at) }}
                                <span v-if="order.paid_at"> — Payée le {{ formatDate(order.paid_at) }}</span>
                            </p>
                        </div>
                        <span
                            :class="[
                                'inline-flex items-center self-start px-3 py-1 rounded-full text-sm font-medium',
                                order.status === 'paid' ? 'bg-green-100 text-green-800' :
                                order.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                'bg-red-100 text-red-800'
                            ]"
                        >
                            {{ statusLabel }}
                        </span>
                    </div>
                </div>

                <!-- Print notice -->
                <div v-if="order.has_prints" class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <p class="text-sm text-amber-800">
                            Cette commande contient des tirages papier qui seront prepares et expedies par la photographe.
                        </p>
                    </div>
                </div>

                <!-- Items -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="font-medium text-gray-900">
                            {{ order.items.length }} photo(s)
                        </h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex items-center gap-4 p-4 sm:px-6"
                        >
                            <div class="w-14 h-14 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                                <img
                                    v-if="item.thumbnail_url"
                                    :src="item.thumbnail_url"
                                    :alt="item.photo_title || 'Photo'"
                                    class="w-full h-full object-cover"
                                />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ item.photo_title || 'Photo' }}
                                </p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                            item.is_print ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'
                                        ]"
                                    >
                                        {{ item.product_type_label }}
                                    </span>
                                    <span class="text-sm text-gold font-medium">{{ formatPrice(item.price) }}</span>
                                </div>
                            </div>
                            <!-- Download button (digital items only, paid orders) -->
                            <button
                                v-if="!item.is_print && order.status === 'paid'"
                                @click="downloadPhoto(item)"
                                :disabled="downloadingItemId === item.id"
                                class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gold hover:text-white hover:bg-gold border border-gold rounded-lg transition-colors disabled:opacity-50"
                            >
                                <svg
                                    v-if="downloadingItemId === item.id"
                                    class="animate-spin w-4 h-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span class="hidden sm:inline">{{ downloadingItemId === item.id ? '...' : 'Télécharger' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Total -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex justify-between items-center">
                        <span class="font-medium text-gray-900">Total</span>
                        <span class="text-xl font-semibold text-gold">{{ formatPrice(order.total) }}</span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/services/authApi'
import { cartApi, CartApiError } from '@/services/cartApi'
import { useToast } from '@/composables/useToast'
import { formatPrice } from '@/utils/format'
import type { AccountOrder, AccountOrderItem } from '@/types/account'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()

const isLoading = ref(true)
const error = ref('')
const order = ref<AccountOrder | null>(null)
const downloadingItemId = ref<string | null>(null)

const statusLabel = computed(() => {
    switch (order.value?.status) {
        case 'paid': return 'Payée'
        case 'pending': return 'En attente'
        case 'failed': return 'Echouee'
        case 'refunded': return 'Remboursee'
        default: return order.value?.status ?? ''
    }
})

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
}

async function loadOrder() {
    const orderId = route.params.id as string
    isLoading.value = true

    try {
        const response = await authApi.getDashboard()
        if (response.success && response.data) {
            const found = response.data.orders.find(o => o.id === orderId)
            if (found) {
                order.value = found
            } else {
                error.value = 'Commande non trouvee'
            }
        }
    } catch {
        error.value = 'Erreur lors du chargement'
    } finally {
        isLoading.value = false
    }
}

async function downloadPhoto(item: AccountOrderItem) {
    if (!order.value || downloadingItemId.value) return

    downloadingItemId.value = item.id
    try {
        const token = order.value.download_token ?? undefined
        const response = await cartApi.downloadPhoto(order.value.id, item.id, token)
        if (response.success && response.download_url) {
            const fileResponse = await fetch(response.download_url)
            const blob = await fileResponse.blob()
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            link.download = response.filename || `${item.photo_title || 'photo'}.jpg`
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            window.URL.revokeObjectURL(url)
        }
    } catch (e) {
        if (e instanceof CartApiError && e.apiError.status === 403) {
            toast.error('Acces refuse', 'Le lien de téléchargement a expire.')
        } else {
            toast.error('Erreur', 'Erreur lors du téléchargement')
        }
    } finally {
        downloadingItemId.value = null
    }
}

onMounted(() => {
    if (!authStore.isAuthenticated) return
    loadOrder()
})
</script>
