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
                <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                    <!-- Items list -->
                    <div class="lg:col-span-2 order-2 lg:order-1">
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                            <div class="divide-y divide-gray-100">
                                <div
                                    v-for="item in cartStore.items"
                                    :key="item.id"
                                    class="flex gap-4 p-4"
                                >
                                    <!-- Photo (thumbnail pour performance) -->
                                    <div class="w-24 h-24 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                                        <img
                                            v-if="item.photo.thumbnail_url || item.photo.display_url"
                                            :src="item.photo.thumbnail_url || item.photo.display_url"
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
                                                @change="cartStore.updateItemType(item.id, ($event.target as HTMLSelectElement).value as ProductType)"
                                                class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-gold/50 focus:border-gold"
                                                :disabled="cartStore.isLoading"
                                            >
                                                <template v-for="(info, typeKey) in getAvailableTypes(item)" :key="typeKey">
                                                    <option v-if="info.is_enabled" :value="typeKey">
                                                        {{ info.label }} - {{ formatPrice(info.price) }}
                                                    </option>
                                                </template>
                                            </select>
                                        </div>

                                        <div class="flex items-center gap-2 mt-2 flex-wrap">
                                            <p class="text-gold font-semibold">
                                                {{ formatPrice(item.line_total ?? item.price * item.quantity) }}
                                            </p>
                                            <span v-if="item.quantity > 1" class="text-sm text-gray-500">
                                                ({{ item.quantity }} × {{ formatPrice(item.price) }})
                                            </span>
                                            <template v-if="item.has_pack_discount && item.base_price">
                                                <span class="text-gray-400 line-through text-sm">{{ formatPrice(item.base_price) }}</span>
                                                <span class="text-xs bg-gold/10 text-gold font-medium px-1.5 py-0.5 rounded">
                                                    Pack {{ item.pack_quantity }} photos
                                                </span>
                                            </template>
                                        </div>

                                        <!-- Quantity stepper -->
                                        <div class="mt-3 inline-flex items-center gap-1 bg-gray-100 rounded-md py-1">
                                            <button
                                                type="button"
                                                @click="cartStore.setItemQuantity(item.id, item.quantity - 1)"
                                                :disabled="cartStore.isLoading"
                                                class="px-2 hover:bg-gray-200 rounded-l-md disabled:opacity-50"
                                                :title="item.quantity === 1 ? 'Retirer du panier' : 'Diminuer'"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path v-if="item.quantity === 1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                                </svg>
                                            </button>
                                            <span class="px-3 font-medium tabular-nums min-w-[2rem] text-center">{{ item.quantity }}</span>
                                            <button
                                                type="button"
                                                @click="cartStore.setItemQuantity(item.id, item.quantity + 1)"
                                                :disabled="cartStore.isLoading"
                                                class="px-2 hover:bg-gray-200 rounded-r-md disabled:opacity-50"
                                                title="Ajouter une copie"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Remove all -->
                                    <button
                                        @click="cartStore.removeItem(item.id)"
                                        class="flex-shrink-0 p-2 text-gray-400 hover:text-red-500 transition-colors self-start"
                                        title="Retirer du panier"
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
                    <div class="lg:col-span-1 order-1 lg:order-2">
                        <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 lg:sticky lg:top-24">
                            <h2 class="text-lg font-medium mb-4">Récapitulatif</h2>

                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ cartStore.itemsCount }} article(s)</span>
                                    <span>{{ formatPrice(cartStore.subtotal) }}</span>
                                </div>
                                <div v-if="cartStore.shippingFee > 0" class="flex justify-between text-gray-600">
                                    <span>Frais de port</span>
                                    <span>+{{ formatPrice(cartStore.shippingFee) }}</span>
                                </div>
                            </div>

                            <!-- Pack savings -->
                            <div v-if="cartStore.hasPackPricing && cartStore.packSavings > 0" class="mt-3 p-2.5 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex justify-between text-sm text-green-700 font-medium">
                                    <span>Economie pack</span>
                                    <span>-{{ formatPrice(cartStore.packSavings) }}</span>
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
import { watch } from 'vue'
import { useCartStore } from '@/stores/cart'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { formatPrice } from '@/utils/format'
import type { ProductType, CartItem, AvailableProductType } from '@/services/cartApi'

const cartStore = useCartStore()
const toast = useToast()
const { confirm } = useConfirm()

// Watch for cart errors and show as toast
watch(() => cartStore.error, (newError) => {
    if (newError) {
        toast.error('Erreur panier', newError)
    }
})

const DEFAULT_TYPES: Record<ProductType, AvailableProductType> = {
    digital: { label: 'Fichier numerique', price: 13, is_print: false, is_enabled: true },
    print_10x15: { label: 'Tirage 10x15 cm', price: 10, is_print: true, is_enabled: true },
    print_15x20: { label: 'Tirage 15x20 cm', price: 15, is_print: true, is_enabled: true },
    print_scolaire: { label: 'Tirage scolaire', price: 6, is_print: true, is_enabled: true },
}

function getAvailableTypes(item: CartItem): Record<ProductType, AvailableProductType> {
    return item.available_product_types ?? DEFAULT_TYPES
}

// Cart init is handled by App.vue + waitForInit() in store actions

async function handleClear() {
    if (await confirm('Voulez-vous vraiment vider votre panier ?')) {
        await cartStore.clearCart()
    }
}
</script>
