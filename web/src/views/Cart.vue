<template>
    <div class="pt-20 min-h-screen bg-cream">
        <!-- Header -->
        <section class="py-12 px-6 lg:px-12 bg-white border-b border-gold">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl md:text-5xl font-light mb-4">Mon Panier</h1>
                <p class="text-gray-600 font-light">
                    {{ cartStore.itemsCount }} photo(s) sélectionnée(s)
                </p>
            </div>
        </section>

        <!-- Content -->
        <section class="py-12 px-6 lg:px-12">
            <div class="max-w-4xl mx-auto">
                <!-- Loading -->
                <div v-if="cartStore.isLoading && cartStore.isEmpty" class="text-center py-16">
                    <svg class="animate-spin h-10 w-10 text-gold mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                </div>

                <!-- Empty state -->
                <div v-else-if="cartStore.isEmpty" class="text-center py-16">
                    <svg class="w-24 h-24 text-gray-300 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <h2 class="text-2xl font-light text-gray-800 mb-2">Votre panier est vide</h2>
                    <p class="text-gray-500 mb-8">Parcourez nos galeries pour ajouter des photos</p>
                    <router-link
                        to="/evenements"
                        class="inline-flex items-center gap-2 px-6 py-3 bg-gold text-white rounded-lg hover:bg-gold/90 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Voir les événements
                    </router-link>
                </div>

                <!-- Cart content -->
                <div v-else class="grid lg:grid-cols-3 gap-8">
                    <!-- Items list -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                            <div class="divide-y divide-gray-100">
                                <div
                                    v-for="item in cartStore.items"
                                    :key="item.id"
                                    class="flex gap-4 p-4"
                                >
                                    <!-- Photo -->
                                    <div class="w-24 h-24 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                                        <img
                                            v-if="item.photo.display_url"
                                            :src="item.photo.display_url"
                                            :alt="item.photo.title || 'Photo'"
                                            class="w-full h-full object-cover"
                                        />
                                    </div>

                                    <!-- Info -->
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-medium text-gray-900 truncate">
                                            {{ item.photo.title || 'Photo' }}
                                        </h3>
                                        <p v-if="item.photo.gallery_title" class="text-sm text-gray-500 mt-1">
                                            {{ item.photo.gallery_title }}
                                        </p>

                                        <!-- Product type selector -->
                                        <div class="mt-2">
                                            <select
                                                :value="item.product_type"
                                                @change="updateItemType(item.id, ($event.target as HTMLSelectElement).value)"
                                                class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-gold/50 focus:border-gold"
                                                :disabled="cartStore.isLoading"
                                            >
                                                <option value="digital">
                                                    Fichier numérique - {{ formatPrice(cartStore.productTypes.digital?.price || 13) }}
                                                </option>
                                                <option value="print_10x15">
                                                    Tirage 10x15 cm - {{ formatPrice(cartStore.productTypes.print_10x15?.price || 10) }}
                                                </option>
                                                <option value="print_15x20">
                                                    Tirage 15x20 cm - {{ formatPrice(cartStore.productTypes.print_15x20?.price || 15) }}
                                                </option>
                                            </select>
                                        </div>

                                        <p class="text-gold font-semibold mt-2">
                                            {{ formatPrice(item.price) }}
                                        </p>
                                    </div>

                                    <!-- Remove -->
                                    <button
                                        @click="removeItem(item.id)"
                                        class="flex-shrink-0 p-2 text-gray-400 hover:text-red-500 transition-colors"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Clear cart -->
                        <button
                            @click="handleClear"
                            class="mt-4 text-sm text-gray-500 hover:text-red-500 transition-colors"
                        >
                            Vider le panier
                        </button>
                    </div>

                    <!-- Summary -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-xl shadow-sm p-6 sticky top-24">
                            <h2 class="text-lg font-medium mb-4">Récapitulatif</h2>

                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ cartStore.itemsCount }} article(s)</span>
                                    <span>{{ formatPrice(cartStore.total) }}</span>
                                </div>
                            </div>

                            <div class="border-t border-gray-100 mt-4 pt-4">
                                <div class="flex justify-between text-lg font-semibold">
                                    <span>Total</span>
                                    <span class="text-gold">{{ formatPrice(cartStore.total) }}</span>
                                </div>
                            </div>

                            <!-- Print notice -->
                            <div v-if="cartStore.hasPrints" class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                <p class="text-xs text-amber-800">
                                    <strong>Tirages papier</strong> : votre commande contient des tirages qui seront préparés et expédiés par la photographe.
                                </p>
                            </div>

                            <router-link
                                to="/checkout"
                                class="block w-full mt-6 py-3 bg-gold text-white text-center font-medium rounded-lg hover:bg-gold/90 transition-colors"
                            >
                                Passer commande
                            </router-link>

                            <p class="mt-4 text-xs text-gray-500 text-center">
                                Paiement sécurisé par SumUp
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useCartStore } from '@/stores/cart'
import type { ProductType } from '@/services/cartApi'

const cartStore = useCartStore()

onMounted(() => {
    if (!cartStore.isInitialized) {
        cartStore.initialize()
    }
})

function formatPrice(price: number): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
    }).format(price)
}

async function updateItemType(itemId: string, productType: string) {
    await cartStore.updateItemType(itemId, productType as ProductType)
}

async function removeItem(itemId: string) {
    await cartStore.removeItem(itemId)
}

async function handleClear() {
    if (confirm('Voulez-vous vraiment vider votre panier ?')) {
        await cartStore.clearCart()
    }
}
</script>
