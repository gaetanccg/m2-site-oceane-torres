<template>
    <button
        @click.stop="handleClick"
        :disabled="isLoading"
        :class="[
            'inline-flex items-center justify-center gap-2 transition-all duration-200',
            isInCart
                ? 'bg-green-500 text-white hover:bg-green-600'
                : 'bg-gold text-white hover:bg-gold/90',
            sizeClasses,
            { 'opacity-50 cursor-not-allowed': isLoading }
        ]"
        :title="isInCart ? 'Dans le panier' : 'Ajouter au panier'"
    >
        <svg
            v-if="isLoading"
            class="animate-spin"
            :class="iconSizeClasses"
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
            v-else-if="isInCart"
            :class="iconSizeClasses"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M5 13l4 4L19 7"
            />
        </svg>
        <svg
            v-else
            :class="iconSizeClasses"
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
        <span v-if="showLabel" class="whitespace-nowrap">
            {{ isInCart ? 'Dans le panier' : 'Ajouter' }}
        </span>
    </button>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useCartStore } from '@/stores/cart'

const props = withDefaults(defineProps<{
    photoId: string
    size?: 'sm' | 'md' | 'lg'
    showLabel?: boolean
}>(), {
    size: 'md',
    showLabel: false,
})

const emit = defineEmits<{
    (e: 'added'): void
    (e: 'error', message: string): void
}>()

const cartStore = useCartStore()
const isLoading = ref(false)

const isInCart = computed(() => cartStore.isPhotoInCart(props.photoId))

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'px-2 py-1 text-xs rounded'
        case 'lg':
            return 'px-5 py-3 text-base rounded-lg'
        default:
            return 'px-3 py-2 text-sm rounded-md'
    }
})

const iconSizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'w-4 h-4'
        case 'lg':
            return 'w-6 h-6'
        default:
            return 'w-5 h-5'
    }
})

async function handleClick() {
    if (isInCart.value || isLoading.value) return

    isLoading.value = true
    try {
        const success = await cartStore.addItem(props.photoId)
        if (success) {
            emit('added')
        } else {
            emit('error', cartStore.error ?? 'Erreur lors de l\'ajout')
        }
    } finally {
        isLoading.value = false
    }
}
</script>
