<template>
    <div class="flex gap-4 py-4 border-b border-gray-100 last:border-0">
        <!-- Photo thumbnail -->
        <div class="w-20 h-20 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
            <img
                v-if="item.photo.thumbnail_url || item.photo.display_url"
                :src="item.photo.thumbnail_url || item.photo.display_url"
                :alt="item.photo.title || 'Photo'"
                class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
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
            <div class="flex items-center gap-2 mt-2 flex-wrap">
                <p class="text-sm font-semibold text-gold">
                    {{ formatPrice(lineTotal) }}
                </p>
                <span v-if="quantity > 1" class="text-xs text-gray-500">
                    ({{ quantity }} × {{ formatPrice(item.price) }})
                </span>
                <template v-if="item.has_pack_discount && item.base_price">
                    <span class="text-gray-400 line-through text-xs">{{ formatPrice(item.base_price) }}</span>
                    <span class="text-[10px] bg-gold/10 text-gold font-medium px-1.5 py-0.5 rounded">
                        Pack {{ item.pack_quantity }} photos
                    </span>
                </template>
            </div>

            <!-- Quantity stepper -->
            <div class="mt-2 inline-flex items-center gap-1 bg-gray-100 rounded-md py-0.5">
                <button
                    type="button"
                    @click="decrement"
                    :disabled="isLoading"
                    class="px-2 hover:bg-gray-200 rounded-l-md disabled:opacity-50"
                    :title="quantity === 1 ? 'Retirer' : 'Diminuer'"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="quantity === 1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                    </svg>
                </button>
                <span class="px-2 text-sm font-medium tabular-nums min-w-[1.5rem] text-center">{{ quantity }}</span>
                <button
                    type="button"
                    @click="increment"
                    :disabled="isLoading"
                    class="px-2 hover:bg-gray-200 rounded-r-md disabled:opacity-50"
                    title="Ajouter une copie"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Remove all button -->
        <button
            @click="handleRemoveAll"
            :disabled="isLoading"
            class="flex-shrink-0 p-1 text-gray-400 hover:text-red-500 transition-colors disabled:opacity-50 self-start"
            title="Tout retirer"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </button>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useCartStore } from '@/stores/cart'
import type { CartItem } from '@/services/cartApi'
import { formatPrice } from '@/utils/format'

const props = defineProps<{
    item: CartItem
}>()

const cartStore = useCartStore()
const isLoading = ref(false)

const quantity = computed(() => props.item.quantity ?? 1)
const lineTotal = computed(() => props.item.line_total ?? props.item.price * quantity.value)

async function increment() {
    if (isLoading.value) return
    isLoading.value = true
    try {
        await cartStore.setItemQuantity(props.item.id, quantity.value + 1)
    } finally {
        isLoading.value = false
    }
}

async function decrement() {
    if (isLoading.value) return
    isLoading.value = true
    try {
        await cartStore.setItemQuantity(props.item.id, quantity.value - 1)
    } finally {
        isLoading.value = false
    }
}

async function handleRemoveAll() {
    if (isLoading.value) return
    isLoading.value = true
    try {
        await cartStore.removeItem(props.item.id)
    } finally {
        isLoading.value = false
    }
}
</script>
