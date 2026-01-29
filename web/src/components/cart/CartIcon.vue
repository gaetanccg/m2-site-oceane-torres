<template>
    <button
        @click="cartStore.toggleDrawer()"
        class="relative p-2 text-gray-600 hover:text-gold transition-colors"
        title="Panier"
    >
        <svg
            class="w-6 h-6"
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
        <!-- Badge -->
        <span
            v-if="cartStore.itemsCount > 0"
            class="absolute -top-1 -right-1 flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-gold rounded-full"
        >
            {{ cartStore.itemsCount > 99 ? '99+' : cartStore.itemsCount }}
        </span>
    </button>
</template>

<script setup lang="ts">
import { useCartStore } from '@/stores/cart'

const cartStore = useCartStore()
</script>
