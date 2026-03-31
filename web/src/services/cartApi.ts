/**
 * Service API pour le panier et les commandes
 */

import { BaseApiService, ApiError as BaseApiError } from './baseApi'

export class CartApiError extends BaseApiError {}

// Types
export type ProductType = 'digital' | 'print_10x15' | 'print_15x20'

export interface ProductTypeInfo {
    label: string
    price: number
    is_print: boolean
}

export interface AvailableProductType {
    label: string
    price: number
    is_print: boolean
    is_enabled: boolean
}

export interface CartItem {
    id: string
    photo_id: string
    photo: {
        id: string
        title: string | null
        display_url?: string
        preview_url?: string
        thumbnail_url?: string
        gallery_title: string | null
        gallery_id?: string
    }
    product_type: ProductType
    product_type_label: string
    is_print: boolean
    price: number
    base_price?: number
    has_pack_discount?: boolean
    pack_quantity?: number | null
    available_product_types?: Record<ProductType, AvailableProductType>
}

export interface Cart {
    id: string
    items: CartItem[]
    items_count: number
    total: number
    has_prints: boolean
    has_pack_pricing?: boolean
    pack_savings?: number
    currency: string
    product_types: Record<ProductType, ProductTypeInfo>
}

export interface CartResponse {
    success: boolean
    cart: Cart
    session_id?: string
    message?: string
}

export interface OrderItem {
    id: string
    photo_id: string
    product_type: ProductType
    product_type_label: string
    is_print: boolean
    photo_title: string | null
    gallery_title: string | null
    price: number
    is_downloaded: boolean
    display_url?: string
    preview_url?: string
    thumbnail_url?: string
}

export interface Order {
    id: string
    order_number: string
    status: 'pending' | 'paid' | 'failed' | 'refunded' | 'expired'
    subtotal: number
    total: number
    currency: string
    paid_at: string | null
    created_at: string
    items: OrderItem[]
    has_prints: boolean
    customer_email: string
    customer_name: string
}

export interface CheckoutResponse {
    success: boolean
    order: {
        id: string
        order_number: string
        total: number
        currency: string
        items_count: number
    }
    payment: {
        checkout_id: string
        order_id: string
        order_number: string
    }
}

export interface SumUpConfig {
    public_key: string
    merchant_code: string
    environment: string
    currency: string
    locale: string
}

class CartApiService extends BaseApiService {
    private getSessionId(): string | null {
        return localStorage.getItem('cart_session_id')
    }

    private setSessionId(sessionId: string): void {
        localStorage.setItem('cart_session_id', sessionId)
    }

    private cartRequest<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
        const headers: Record<string, string> = {}
        const token = this.getToken()
        if (token) headers['Authorization'] = `Bearer ${token}`
        const sessionId = this.getSessionId()
        if (sessionId) headers['X-Cart-Session'] = sessionId
        return this.request<T>(endpoint, options, { headers })
    }

    // ============================================================================
    // Cart
    // ============================================================================

    async getCart(): Promise<CartResponse> {
        const response = await this.cartRequest<CartResponse>('/cart')
        if (response.session_id) {
            this.setSessionId(response.session_id)
        }
        return response
    }

    async addToCart(photoId: string, productType: ProductType = 'digital'): Promise<CartResponse> {
        const response = await this.cartRequest<CartResponse>('/cart/add', {
            method: 'POST',
            body: JSON.stringify({ photo_id: photoId, product_type: productType }),
        })
        if (response.session_id) {
            this.setSessionId(response.session_id)
        }
        return response
    }

    async updateItemType(itemId: string, productType: ProductType): Promise<CartResponse> {
        return this.cartRequest<CartResponse>(`/cart/item/${itemId}/type`, {
            method: 'PUT',
            body: JSON.stringify({ product_type: productType }),
        })
    }

    async removeFromCart(itemId: string): Promise<CartResponse> {
        return this.cartRequest<CartResponse>(`/cart/item/${itemId}`, {
            method: 'DELETE',
        })
    }

    async clearCart(): Promise<CartResponse> {
        return this.cartRequest<CartResponse>('/cart/clear', {
            method: 'DELETE',
        })
    }

    async updateCartEmail(email: string): Promise<{ success: boolean; message: string }> {
        return this.cartRequest('/cart/email', {
            method: 'PUT',
            body: JSON.stringify({ email }),
        })
    }

    async mergeCart(): Promise<CartResponse> {
        return this.cartRequest<CartResponse>('/cart/merge', {
            method: 'POST',
        })
    }

    // ============================================================================
    // Checkout & Orders
    // ============================================================================

    async createOrder(guestEmail?: string, guestName?: string, cgvAccepted?: boolean): Promise<CheckoutResponse> {
        return this.cartRequest<CheckoutResponse>('/checkout', {
            method: 'POST',
            body: JSON.stringify({
                guest_email: guestEmail,
                guest_name: guestName,
                cgv_accepted: cgvAccepted ?? true,
            }),
        })
    }

    async getOrder(orderId: string, token?: string): Promise<{ success: boolean; order: Order }> {
        const params = token ? `?token=${encodeURIComponent(token)}` : ''
        return this.cartRequest(`/orders/${orderId}${params}`)
    }

    async getUserOrders(): Promise<{ success: boolean; orders: Order[] }> {
        return this.cartRequest('/orders')
    }

    async getOrdersByEmail(email: string): Promise<{ success: boolean; orders: Order[] }> {
        return this.cartRequest('/orders/by-email', {
            method: 'POST',
            body: JSON.stringify({ email }),
        })
    }

    async downloadPhoto(orderId: string, itemId: string, token?: string): Promise<{ success: boolean; download_url: string; filename: string }> {
        const params = token ? `?token=${encodeURIComponent(token)}` : ''
        return this.cartRequest(`/orders/${orderId}/download/${itemId}${params}`)
    }

    // ============================================================================
    // SumUp Payment
    // ============================================================================

    async getSumUpConfig(): Promise<{ success: boolean; config: SumUpConfig }> {
        return this.cartRequest('/payments/sumup/config')
    }

    async createSumUpCheckout(orderId: string): Promise<{ success: boolean; checkout_id: string; order_id: string }> {
        return this.cartRequest('/payments/sumup/create-checkout', {
            method: 'POST',
            body: JSON.stringify({ order_id: orderId }),
        })
    }

    async verifySumUpPayment(orderId: string): Promise<{ success: boolean; status: string; order?: { id: string; order_number: string; status: string } }> {
        return this.cartRequest('/payments/sumup/verify', {
            method: 'POST',
            body: JSON.stringify({ order_id: orderId }),
        })
    }

    async handleSumUpCallback(checkoutId?: string, orderId?: string): Promise<{ success: boolean; order: { id: string; order_number: string; status: string; total: number; currency: string } }> {
        const params = new URLSearchParams()
        if (checkoutId) params.append('checkout_id', checkoutId)
        if (orderId) params.append('order', orderId)
        return this.cartRequest(`/payments/sumup/callback?${params.toString()}`)
    }

    async cancelCheckout(orderId: string): Promise<{ success: boolean }> {
        return this.cartRequest('/payments/sumup/cancel-checkout', {
            method: 'POST',
            body: JSON.stringify({ order_id: orderId }),
        })
    }
}

// Export singleton instance
export const cartApi = new CartApiService()
export default cartApi
