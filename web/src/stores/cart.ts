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
    const subtotal = computed(() => cart.value?.subtotal ?? 0)
    const shippingFee = computed(() => cart.value?.shipping_fee ?? 0)
    const total = computed(() => cart.value?.total ?? 0)
    const currency = computed(() => cart.value?.currency ?? 'EUR')
    const isEmpty = computed(() => itemsCount.value === 0)
    const hasPrints = computed(() => cart.value?.has_prints ?? false)
    const requiresShipping = computed(() => cart.value?.requires_shipping ?? false)
    const hasPackPricing = computed(() => cart.value?.has_pack_pricing ?? false)
    const packSavings = computed(() => cart.value?.pack_savings ?? 0)
    const discount = computed(() => cart.value?.discount_amount ?? 0)
    const appliedCode = computed(() => cart.value?.gift_code ?? null)
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

    /**
     * Get the quantity of a (photoId, productType) pair in the cart, 0 if absent.
     */
    const getQuantity = computed(() => {
        return (photoId: string, productType: ProductType): number => {
            const item = items.value.find(
                i => i.photo_id === photoId && i.product_type === productType
            )
            return item?.quantity ?? 0
        }
    })

    /**
     * Get the CartItem id for (photoId, productType), or null if not in cart.
     */
    function getItemId(photoId: string, productType: ProductType): string | null {
        const match = items.value.find(
            i => i.photo_id === photoId && i.product_type === productType
        )
        return match?.id ?? null
    }

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
    async function addItem(photoId: string, productType: ProductType = 'digital', quantity: number = 1): Promise<boolean> {
        isLoading.value = true
        error.value = null

        try {
            const response = await cartApi.addToCart(photoId, productType, quantity)
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
     * Set quantity for a cart item. Quantity 0 removes the item.
     */
    async function setItemQuantity(itemId: string, quantity: number): Promise<boolean> {
        await waitForInit()
        isLoading.value = true
        error.value = null

        try {
            const response = await cartApi.setItemQuantity(itemId, quantity)
            if (response.success) {
                cart.value = response.cart
                return true
            }
            error.value = response.message ?? 'Erreur lors de la mise a jour'
            return false
        } catch (e) {
            error.value = e instanceof CartApiError ? e.apiError.message : 'Erreur lors de la mise a jour'
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
     * Apply a promo / gift code to the cart.
     * On failure, `error` holds the server message (invalid / expired / used up).
     */
    async function applyGiftCode(code: string): Promise<boolean> {
        await waitForInit()
        isLoading.value = true
        error.value = null

        try {
            const response = await cartApi.applyGiftCode(code)
            if (response.success) {
                cart.value = response.cart
                return true
            }
            error.value = response.message ?? 'Code promo invalide.'
            return false
        } catch (e) {
            error.value = e instanceof CartApiError ? e.apiError.message : 'Code promo invalide.'
            return false
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Remove the applied promo / gift code from the cart.
     */
    async function removeGiftCode(): Promise<boolean> {
        await waitForInit()
        isLoading.value = true
        error.value = null

        try {
            const response = await cartApi.removeGiftCode()
            if (response.success) {
                cart.value = response.cart
                return true
            }
            return false
        } catch (e) {
            error.value = e instanceof CartApiError ? e.apiError.message : 'Erreur lors du retrait du code'
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
        subtotal,
        shippingFee,
        total,
        currency,
        isEmpty,
        hasPrints,
        requiresShipping,
        hasPackPricing,
        packSavings,
        discount,
        appliedCode,
        productTypes,
        isPhotoInCart,
        getQuantity,
        getItemId,
        // Actions
        initialize,
        addItem,
        setItemQuantity,
        updateItemType,
        removeItem,
        clearCart,
        applyGiftCode,
        removeGiftCode,
        mergeAfterLogin,
        refresh,
        openDrawer,
        closeDrawer,
        toggleDrawer,
        clearError,
        reset,
    }
})
