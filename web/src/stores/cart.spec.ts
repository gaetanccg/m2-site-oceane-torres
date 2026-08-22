import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { Cart } from '@/services/cartApi'

vi.mock('@/services/cartApi', () => ({
    cartApi: {
        getCart: vi.fn(),
        addToCart: vi.fn(),
        setItemQuantity: vi.fn(),
        updateItemType: vi.fn(),
        removeItem: vi.fn(),
        clearCart: vi.fn(),
        applyGiftCode: vi.fn(),
        removeGiftCode: vi.fn(),
        mergeGuestCart: vi.fn(),
    },
    CartApiError: class CartApiError extends Error {
        constructor(public apiError: { message: string }) {
            super(apiError.message)
        }
    },
}))

const { cartApi, CartApiError } = await import('@/services/cartApi')
const { useCartStore } = await import('./cart')

function makeCart(overrides: Partial<Cart> = {}): Cart {
    return {
        id: 'cart-1',
        items: [],
        items_count: 0,
        subtotal: 0,
        shipping_fee: 0,
        discount_amount: 0,
        total: 0,
        currency: 'EUR',
        has_prints: false,
        requires_shipping: false,
        has_pack_pricing: false,
        pack_savings: 0,
        gift_code: null,
        product_types: {},
        ...overrides,
    } as Cart
}

const item = (o: Record<string, unknown> = {}) => ({
    id: 'item-1',
    photo_id: 'photo-1',
    product_type: 'digital',
    quantity: 1,
    price: 13,
    ...o,
})

describe('store panier — état initial', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        vi.clearAllMocks()
    })

    it('est vide avant toute initialisation', () => {
        const s = useCartStore()
        expect(s.isEmpty).toBe(true)
        expect(s.itemsCount).toBe(0)
        expect(s.total).toBe(0)
        expect(s.currency).toBe('EUR')
    })

    it('expose un repli de types de produits qui inclut le tirage scolaire', () => {
        const s = useCartStore()
        // Le repli ne doit pas omettre print_scolaire : une galerie scolaire
        // dont le serveur ne renvoie pas la liste afficherait sinon un panier
        // sans le seul type qu'elle vend.
        expect(Object.keys(s.productTypes)).toContain('print_scolaire')
    })
})

describe('store panier — getters dérivés', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        vi.clearAllMocks()
    })

    it('reflète les totaux renvoyés par le serveur', async () => {
        vi.mocked(cartApi.addToCart).mockResolvedValue({
            success: true,
            cart: makeCart({ items: [item({ quantity: 3 })], items_count: 3, subtotal: 39, total: 41, shipping_fee: 2, has_prints: true }),
        } as never)

        const s = useCartStore()
        await s.addItem('photo-1', 'digital', 3)

        expect(s.itemsCount).toBe(3)
        expect(s.subtotal).toBe(39)
        expect(s.shippingFee).toBe(2)
        expect(s.total).toBe(41)
        expect(s.hasPrints).toBe(true)
        expect(s.isEmpty).toBe(false)
    })

    it('getQuantity distingue les types de produit pour une même photo', async () => {
        vi.mocked(cartApi.addToCart).mockResolvedValue({
            success: true,
            cart: makeCart({
                items: [
                    item({ id: 'a', product_type: 'digital', quantity: 2 }),
                    item({ id: 'b', product_type: 'print_10x15', quantity: 5 }),
                ],
                items_count: 7,
            }),
        } as never)

        const s = useCartStore()
        await s.addItem('photo-1')

        expect(s.getQuantity('photo-1', 'digital')).toBe(2)
        expect(s.getQuantity('photo-1', 'print_10x15')).toBe(5)
        expect(s.getQuantity('photo-1', 'print_15x20')).toBe(0)
        expect(s.getQuantity('photo-inconnue', 'digital')).toBe(0)
    })

    it('isPhotoInCart ignore le type de produit', async () => {
        vi.mocked(cartApi.addToCart).mockResolvedValue({
            success: true,
            cart: makeCart({ items: [item({ product_type: 'print_10x15' })], items_count: 1 }),
        } as never)

        const s = useCartStore()
        await s.addItem('photo-1', 'print_10x15')

        expect(s.isPhotoInCart('photo-1')).toBe(true)
        expect(s.isPhotoInCart('photo-2')).toBe(false)
    })
})

describe('store panier — gestion des erreurs', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        vi.clearAllMocks()
    })

    it('remonte le message du serveur sur un échec métier', async () => {
        vi.mocked(cartApi.addToCart).mockResolvedValue({
            success: false,
            message: 'Cette session scolaire est clôturée.',
        } as never)

        const s = useCartStore()
        const ok = await s.addItem('photo-1')

        expect(ok).toBe(false)
        expect(s.error).toBe('Cette session scolaire est clôturée.')
    })

    it('remonte le message d une CartApiError', async () => {
        vi.mocked(cartApi.addToCart).mockRejectedValue(new CartApiError({ message: 'Photo indisponible.' }))

        const s = useCartStore()
        const ok = await s.addItem('photo-1')

        expect(ok).toBe(false)
        expect(s.error).toBe('Photo indisponible.')
    })

    it('libère isLoading même en cas d échec', async () => {
        vi.mocked(cartApi.addToCart).mockRejectedValue(new Error('boom'))

        const s = useCartStore()
        await s.addItem('photo-1')

        expect(s.isLoading).toBe(false)
    })
})

describe('store panier — tiroir', () => {
    beforeEach(() => setActivePinia(createPinia()))

    it('ouvre, ferme et bascule', () => {
        const s = useCartStore()
        expect(s.isDrawerOpen).toBe(false)
        s.openDrawer()
        expect(s.isDrawerOpen).toBe(true)
        s.closeDrawer()
        expect(s.isDrawerOpen).toBe(false)
        s.toggleDrawer()
        expect(s.isDrawerOpen).toBe(true)
    })
})
