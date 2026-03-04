<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition name="fade">
            <div
                v-if="cartStore.isDrawerOpen"
                class="fixed inset-0 bg-black/50 z-40"
                @click="cartStore.closeDrawer()"
            />
        </Transition>

        <!-- Drawer -->
        <Transition name="slide">
            <div
                v-if="cartStore.isDrawerOpen"
                class="fixed top-0 right-0 bottom-0 w-full sm:max-w-md bg-white shadow-xl z-50 flex flex-col"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 class="text-lg font-medium text-gray-900">
                        Panier
                        <span v-if="cartStore.itemsCount > 0" class="text-gray-500 font-normal">
                            ({{ cartStore.itemsCount }})
                        </span>
                    </h2>
                    <button
                        @click="cartStore.closeDrawer()"
                        class="p-2 text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto px-6">
                    <!-- Loading -->
                    <div v-if="cartStore.isLoading && cartStore.isEmpty" class="py-12 text-center">
                        <svg
                            class="animate-spin h-8 w-8 text-gold mx-auto"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="cartStore.isEmpty" class="py-12 text-center">
                        <svg
                            class="w-16 h-16 text-gray-300 mx-auto mb-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
                            />
                        </svg>
                        <p class="text-gray-500">Votre panier est vide</p>
                        <p class="text-sm text-gray-400 mt-1">
                            Ajoutez des photos pour commencer
                        </p>
                    </div>

                    <!-- Items list -->
                    <div v-else class="py-4">
                        <CartItem
                            v-for="item in cartStore.items"
                            :key="item.id"
                            :item="item"
                        />
                    </div>
                </div>

                <!-- Footer -->
                <div v-if="!cartStore.isEmpty" class="border-t border-gray-100 px-6 py-4 space-y-4">
                    <!-- Total -->
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Total</span>
                        <span class="text-xl font-semibold text-gray-900">
                            {{ formatPrice(cartStore.total) }}
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="space-y-2">
                        <router-link
                            to="/panier"
                            @click="cartStore.closeDrawer()"
                            class="block w-full py-3 bg-gold text-white text-center font-medium rounded-lg hover:bg-gold/90 transition-colors"
                        >
                            Voir le panier
                        </router-link>
                        <button
                            @click="handleClear"
                            :disabled="cartStore.isLoading"
                            class="block w-full py-2 text-gray-500 text-center text-sm hover:text-red-500 transition-colors disabled:opacity-50"
                        >
                            Vider le panier
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import { useCartStore } from '@/stores/cart'
import { useConfirm } from '@/composables/useConfirm'
import CartItem from './CartItem.vue'

const cartStore = useCartStore()
const { confirm } = useConfirm()

function formatPrice(price: number): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
    }).format(price)
}

async function handleClear() {
    if (await confirm('Voulez-vous vraiment vider votre panier ?')) {
        await cartStore.clearCart()
    }
}
</script>

<style scoped>
/* Fade transition for backdrop */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Slide transition for drawer */
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}
</style>
