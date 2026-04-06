/**
 * Composable pour envoyer des événements GA4 e-commerce
 * Respecte le consentement analytics avant tout envoi
 */

import { useConsentStore } from '@/stores/consent'

/**
 * Fonction gtag standard — DOIT utiliser `arguments` (pas de rest params)
 * car gtag.js distingue les objets Arguments des Arrays lors du traitement
 * du dataLayer. Avec des rest params (...args), les commandes sont ignorées.
 */
function gtag(..._args: unknown[]) {
    // eslint-disable-next-line prefer-rest-params
    window.dataLayer?.push(arguments)
}

interface GtagItem {
    item_id: string
    item_name: string
    item_category?: string
    price: number
    quantity: number
}

export function useGtag() {
    const consentStore = useConsentStore()

    function isAllowed(): boolean {
        return consentStore.analyticsEnabled && typeof window !== 'undefined' && !!window.dataLayer
    }

    function trackAddToCart(item: { photoId: string; title: string | null; galleryTitle: string | null; price: number; productType: string }) {
        if (!isAllowed()) return

        gtag('event', 'add_to_cart', {
            currency: 'EUR',
            value: item.price,
            items: [{
                item_id: item.photoId,
                item_name: item.title || 'Photo',
                item_category: item.galleryTitle || undefined,
                price: item.price,
                quantity: 1,
            }]
        })
    }

    function trackRemoveFromCart(item: { photoId: string; title: string | null; price: number }) {
        if (!isAllowed()) return

        gtag('event', 'remove_from_cart', {
            currency: 'EUR',
            value: item.price,
            items: [{
                item_id: item.photoId,
                item_name: item.title || 'Photo',
                price: item.price,
                quantity: 1,
            }]
        })
    }

    function trackBeginCheckout(items: GtagItem[], total: number) {
        if (!isAllowed()) return

        gtag('event', 'begin_checkout', {
            currency: 'EUR',
            value: total,
            items,
        })
    }

    function trackPurchase(order: { id: string; orderNumber: string; total: number; currency: string; items: GtagItem[] }) {
        if (!isAllowed()) return

        gtag('event', 'purchase', {
            transaction_id: order.orderNumber,
            value: order.total,
            currency: order.currency || 'EUR',
            items: order.items,
        })
    }

    return {
        trackAddToCart,
        trackRemoveFromCart,
        trackBeginCheckout,
        trackPurchase,
    }
}
