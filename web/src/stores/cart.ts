/**
 * Store Pinia pour la gestion du panier
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { cartApi, type Cart, type ProductType, CartApiError } from '@/services/cartApi'
import { useAuthStore } from './auth'

export const useCartStore = defineStore('cart', () => {
    // State
    const cart = ref<Cart | null>(null)
    const isLoading = ref(false)
    const isInitialized = ref(false)
    const error = ref<string | null>(null)
    const isDrawerOpen = ref(false)

    // Shared promise so concurrent callers and actions wait for init to finish
    let initPromise: Promise<void> | null = null

    // Getters
    const items = computed(() => cart.value?.items ?? [])
    const itemsCount = computed(() => cart.value?.items_count ?? 0)
    const total = computed(() => cart.value?.total ?? 0)
    const currency = computed(() => cart.value?.currency ?? 'EUR')
    const isEmpty = computed(() => itemsCount.value === 0)
    const hasPrints = computed(() => cart.value?.has_prints ?? false)
    const hasPackPricing = computed(() => cart.value?.has_pack_pricing ?? false)
    const packSavings = computed(() => cart.value?.pack_savings ?? 0)
    const productTypes = computed(() => cart.value?.product_types ?? {
        digital: { label: 'Fichier numérique', price: 13, is_print: false },
        print_10x15: { label: 'Tirage 10x15 cm', price: 10, is_print: true },
        print_15x20: { label: 'Tirage 15x20 cm', price: 15, is_print: true },
    })

    const isPhotoInCart = computed(() => {
        return (photoId: string) => {
            return items.value.some(item => item.photo_id === photoId)
        }
    })

    // Actions

    /**
     * Initialize cart from server.
     * Returns a shared promise so concurrent calls and dependent actions
     * all wait for the same in-flight request.
     */
    function initialize(): Promise<void> {
        if (isInitialized.value) return Promise.resolve()
        if (initPromise) return initPromise

        isLoading.value = true
        error.value = null

        initPromise = cartApi.getCart()
            .then(response => {
                if (response.success) {
                    cart.value = response.cart
                }
            })
            .catch(e => {
                error.value = e instanceof CartApiError ? e.apiError.message : 'Erreur lors du chargement du panier'
            })
            .finally(() => {
                isLoading.value = false
                isInitialized.value = true
            })

        return initPromise
    }

    /**
     * Wait for initialize() to complete (no-op if already done).
     */
    function waitForInit(): Promise<void> {
        return initPromise ?? Promise.resolve()
    }

    /**
     * Add a photo to cart.
     * Does NOT wait for init — the backend creates the cart if needed (getOrCreateCart).
     * This makes "Add to cart" instant even if the cart hasn't finished loading.
     */
    async function addItem(photoId: string, productType: ProductType = 'digital'): Promise<boolean> {
        isLoading.value = true
        error.value = null

        try {
            const response = await cartApi.addToCart(photoId, productType)
            if (response.success) {
                cart.value = response.cart
                isInitialized.value = true
                return true
            }
            error.value = response.message ?? 'Erreur lors de l\'ajout au panier'
            return false
        } catch (e) {
            error.value = e instanceof CartApiError ? e.apiError.message : 'Erreur lors de l\'ajout au panier'
            return false
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Update item product type
     */
    async function updateItemType(itemId: string, productType: ProductType): Promise<boolean> {
        await waitForInit()
        isLoading.value = true
        error.value = null

        try {
            const response = await cartApi.updateItemType(itemId, productType)
            if (response.success) {
                cart.value = response.cart
                return true
            }
            error.value = response.message ?? 'Erreur lors de la mise à jour'
            return false
        } catch (e) {
            error.value = e instanceof CartApiError ? e.apiError.message : 'Erreur lors de la mise à jour'
            return false
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Remove an item from cart
     */
    async function removeItem(itemId: string): Promise<boolean> {
        await waitForInit()
        isLoading.value = true
        error.value = null

        try {
            const response = await cartApi.removeFromCart(itemId)
            if (response.success) {
                cart.value = response.cart
                return true
            }
            error.value = response.message ?? 'Erreur lors de la suppression'
            return false
        } catch (e) {
            error.value = e instanceof CartApiError ? e.apiError.message : 'Erreur lors de la suppression'
            return false
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Clear entire cart
     */
    async function clearCart(): Promise<boolean> {
        await waitForInit()
        isLoading.value = true
        error.value = null

        try {
            const response = await cartApi.clearCart()
            if (response.success) {
                cart.value = response.cart
                return true
            }
            return false
        } catch (e) {
            error.value = e instanceof CartApiError ? e.apiError.message : 'Erreur lors du vidage du panier'
            return false
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Merge guest cart after login
     */
    async function mergeAfterLogin(): Promise<void> {
        const authStore = useAuthStore()
        if (!authStore.isAuthenticated) return

        try {
            const response = await cartApi.mergeCart()
            if (response.success && response.cart) {
                cart.value = response.cart
            }
        } catch {
            // Silently fail merge
        }
    }

    /**
     * Refresh cart from server
     */
    async function refresh(): Promise<void> {
        isLoading.value = true
        error.value = null

        try {
            const response = await cartApi.getCart()
            if (response.success) {
                cart.value = response.cart
            }
        } catch (e) {
            error.value = e instanceof CartApiError ? e.apiError.message : 'Erreur lors du rafraîchissement'
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Open cart drawer
     */
    function openDrawer(): void {
        isDrawerOpen.value = true
    }

    /**
     * Close cart drawer
     */
    function closeDrawer(): void {
        isDrawerOpen.value = false
    }

    /**
     * Toggle cart drawer
     */
    function toggleDrawer(): void {
        isDrawerOpen.value = !isDrawerOpen.value
    }

    /**
     * Clear error
     */
    function clearError(): void {
        error.value = null
    }

    /**
     * Reset cart state (for logout)
     */
    function reset(): void {
        cart.value = null
        isInitialized.value = false
        initPromise = null
        error.value = null
        localStorage.removeItem('cart_session_id')
    }

    return {
        // State
        cart,
        isLoading,
        isInitialized,
        error,
        isDrawerOpen,
        // Getters
        items,
        itemsCount,
        total,
        currency,
        isEmpty,
        hasPrints,
        hasPackPricing,
        packSavings,
        productTypes,
        isPhotoInCart,
        // Actions
        initialize,
        addItem,
        updateItemType,
        removeItem,
        clearCart,
        mergeAfterLogin,
        refresh,
        openDrawer,
        closeDrawer,
        toggleDrawer,
        clearError,
        reset,
    }
})
