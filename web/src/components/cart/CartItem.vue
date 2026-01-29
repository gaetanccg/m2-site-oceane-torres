<template>
    <div class="flex gap-4 py-4 border-b border-gray-100 last:border-0">
        <!-- Photo thumbnail -->
        <div class="w-20 h-20 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
            <img
                v-if="item.photo.display_url"
                :src="item.photo.display_url"
                :alt="item.photo.title || 'Photo'"
                class="w-full h-full object-cover"
            />
            <div
                v-else
                class="w-full h-full flex items-center justify-center text-gray-400"
            >
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
            </div>
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
            <h4 class="text-sm font-medium text-gray-900 truncate">
                {{ item.photo.title || 'Photo' }}
            </h4>
            <p v-if="item.photo.gallery_title" class="text-xs text-gray-500 mt-0.5 truncate">
                {{ item.photo.gallery_title }}
            </p>
            <p class="text-sm font-semibold text-gold mt-2">
                {{ formatPrice(item.price) }}
            </p>
        </div>

        <!-- Remove button -->
        <button
            @click="handleRemove"
            :disabled="isRemoving"
            class="flex-shrink-0 p-1 text-gray-400 hover:text-red-500 transition-colors disabled:opacity-50"
            title="Retirer"
        >
            <svg
                v-if="isRemoving"
                class="w-5 h-5 animate-spin"
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
            <svg
                v-else
                class="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
            </svg>
        </button>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useCartStore } from '@/stores/cart'
import type { CartItem } from '@/services/cartApi'

const props = defineProps<{
    item: CartItem
}>()

const cartStore = useCartStore()
const isRemoving = ref(false)

function formatPrice(price: number): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
    }).format(price)
}

async function handleRemove() {
    isRemoving.value = true
    await cartStore.removeItem(props.item.photo_id)
    isRemoving.value = false
}
</script>
