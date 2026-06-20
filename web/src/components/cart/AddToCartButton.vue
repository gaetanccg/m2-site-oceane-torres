<template>
    <!-- Quantity stepper when item is in cart -->
    <div
        v-if="hasEnabledProducts && quantity > 0"
        @click.stop
        :class="[
            'inline-flex items-center gap-1 bg-green-500 text-white rounded-md',
            stepperSizeClasses,
        ]"
    >
        <button
            type="button"
            :disabled="isLoading"
            @click="decrement"
            class="px-2 hover:bg-green-600 rounded-l-md transition-colors disabled:opacity-50"
            :title="quantity === 1 ? 'Retirer du panier' : 'Diminuer'"
        >
            <svg :class="iconSizeClasses" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path v-if="quantity === 1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
            </svg>
        </button>
        <span class="px-2 font-medium tabular-nums min-w-[1.5rem] text-center">{{ quantity }}</span>
        <button
            type="button"
            :disabled="isLoading"
            @click="increment"
            class="px-2 hover:bg-green-600 rounded-r-md transition-colors disabled:opacity-50"
            title="Ajouter une copie"
        >
            <svg :class="iconSizeClasses" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </button>
    </div>

    <!-- Initial add button (hidden for view-only galleries with no purchasable product) -->
    <button
        v-else-if="hasEnabledProducts"
        @click.stop="handleAdd"
        :disabled="isLoading"
        :class="[
            'inline-flex items-center justify-center gap-2 transition-all duration-200 bg-gold text-white hover:bg-gold/90',
            sizeClasses,
            { 'opacity-50 cursor-not-allowed': isLoading }
        ]"
        title="Ajouter au panier"
    >
        <svg
            v-if="isLoading"
            class="animate-spin"
            :class="iconSizeClasses"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <svg v-else :class="iconSizeClasses" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span v-if="showLabel" class="whitespace-nowrap">Ajouter</span>
    </button>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useCartStore } from '@/stores/cart'
import { useGtag } from '@/composables/useGtag'

interface AvailableProductType {
    label: string
    price: number
    is_print: boolean
    is_enabled: boolean
}

type ProductTypeKey = 'digital' | 'print_10x15' | 'print_15x20' | 'print_scolaire'

const props = withDefaults(defineProps<{
    photoId: string
    size?: 'sm' | 'md' | 'lg'
    showLabel?: boolean
    availableProductTypes?: Record<ProductTypeKey, AvailableProductType> | null
}>(), {
    size: 'md',
    showLabel: false,
    availableProductTypes: null,
})

const emit = defineEmits<{
    (e: 'added'): void
    (e: 'error', message: string): void
}>()

const cartStore = useCartStore()
const { trackAddToCart } = useGtag()
const isLoading = ref(false)

const productType = computed<ProductTypeKey>(() => getDefaultProductType())

// Vitrine : aucun produit activé => pas de vente. availableProductTypes null = legacy/chargement,
// on préserve le comportement actuel (bouton affiché).
const hasEnabledProducts = computed(() => {
    if (!props.availableProductTypes) return true
    return Object.values(props.availableProductTypes).some(p => p.is_enabled)
})

const quantity = computed(() => cartStore.getQuantity(props.photoId, productType.value))

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm': return 'px-2 py-1 text-xs rounded'
        case 'lg': return 'px-5 py-3 text-base rounded-lg'
        default: return 'px-3 py-2 text-sm rounded-md'
    }
})

const stepperSizeClasses = computed(() => {
    switch (props.size) {
        case 'sm': return 'py-0.5 text-xs'
        case 'lg': return 'py-2 text-base'
        default: return 'py-1 text-sm'
    }
})

const iconSizeClasses = computed(() => {
    switch (props.size) {
        case 'sm': return 'w-4 h-4'
        case 'lg': return 'w-6 h-6'
        default: return 'w-5 h-5'
    }
})

function getDefaultProductType(): ProductTypeKey {
    if (!props.availableProductTypes) return 'digital'
    for (const key of ['digital', 'print_10x15', 'print_15x20', 'print_scolaire'] as ProductTypeKey[]) {
        if (props.availableProductTypes[key]?.is_enabled) return key
    }
    return 'digital'
}

async function handleAdd() {
    if (isLoading.value) return
    isLoading.value = true
    try {
        const success = await cartStore.addItem(props.photoId, productType.value, 1)
        if (success) {
            const addedItem = cartStore.items.find(i => i.photo_id === props.photoId)
            if (addedItem) {
                trackAddToCart({
                    photoId: addedItem.photo_id,
                    title: addedItem.photo.title,
                    galleryTitle: addedItem.photo.gallery_title,
                    price: addedItem.price,
                    productType: productType.value,
                })
            }
            emit('added')
        } else {
            emit('error', cartStore.error ?? 'Erreur lors de l\'ajout')
        }
    } finally {
        isLoading.value = false
    }
}

async function increment() {
    if (isLoading.value) return
    isLoading.value = true
    try {
        await cartStore.addItem(props.photoId, productType.value, 1)
    } finally {
        isLoading.value = false
    }
}

async function decrement() {
    if (isLoading.value) return
    const itemId = cartStore.getItemId(props.photoId, productType.value)
    if (!itemId) return
    isLoading.value = true
    try {
        await cartStore.setItemQuantity(itemId, quantity.value - 1)
    } finally {
        isLoading.value = false
    }
}
</script>
