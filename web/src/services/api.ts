/**
 * Service API pour l'intégration avec Laravel
 * Prêt pour connecter un backend REST API
 */

import {API_CONFIG} from '@/config/constants'
import type {ApiResponse, PaginatedResponse, GalleryItem, Prestation, GiftCard} from '@/types'
import {extractErrorFromResponse, parseApiError, type ApiError} from '@/utils/errorHandler'

export class ApiServiceError extends Error {
    public apiError: ApiError

    constructor(apiError: ApiError) {
        super(apiError.message)
        this.name = 'ApiServiceError'
        this.apiError = apiError
    }
}

class ApiService {
    private baseUrl: string
    private timeout: number

    constructor() {
        this.baseUrl = API_CONFIG.baseUrl
        this.timeout = API_CONFIG.timeout
    }

    private async request<T>(
        endpoint: string,
        options: RequestInit = {}
    ): Promise<T> {
        const controller = new AbortController()
        const timeoutId = setTimeout(() => controller.abort(), this.timeout)

        try {
            const response = await fetch(`${this.baseUrl}${endpoint}`, {
                ...options,
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...options.headers,
                },
            })

            clearTimeout(timeoutId)

            if (!response.ok) {
                const apiError = await extractErrorFromResponse(response)
                throw new ApiServiceError(apiError)
            }

            return await response.json()
        } catch (error) {
            clearTimeout(timeoutId)

            // Si c'est déjà une ApiServiceError, la propager
            if (error instanceof ApiServiceError) {
                throw error
            }

            // Sinon, parser l'erreur
            const apiError = parseApiError(error)
            throw new ApiServiceError(apiError)
        }
    }

    // ============================================================================
    // Gallery
    // ============================================================================

    async getGalleryItems(category?: string): Promise<ApiResponse<GalleryItem[]>> {
        const query = category ? `?category=${encodeURIComponent(category)}` : ''
        return this.request<ApiResponse<GalleryItem[]>>(`/galleries${query}`)
    }

    async getGalleryItemsPaginated(
        page = 1,
        perPage = 20,
        category?: string
    ): Promise<PaginatedResponse<GalleryItem>> {
        let query = `?page=${page}&per_page=${perPage}`
        if (category) {
            query += `&category=${encodeURIComponent(category)}`
        }
        return this.request<PaginatedResponse<GalleryItem>>(`/galleries${query}`)
    }

    // ============================================================================
    // Prestations
    // ============================================================================

    async getPrestations(): Promise<ApiResponse<Prestation[]>> {
        return this.request<ApiResponse<Prestation[]>>('/prestations')
    }

    async getPrestation(id: number): Promise<ApiResponse<Prestation>> {
        return this.request<ApiResponse<Prestation>>(`/prestations/${id}`)
    }

    // ============================================================================
    // Gift Cards
    // ============================================================================

    async getGiftCards(): Promise<ApiResponse<GiftCard[]>> {
        return this.request<ApiResponse<GiftCard[]>>('/gift-cards')
    }

    // ============================================================================
    // Contact Form (pour une future implémentation)
    // ============================================================================

    async sendContactMessage(data: {
        name: string
        email: string
        phone?: string
        subject: string
        message: string
    }): Promise<ApiResponse<{ message: string }>> {
        return this.request<ApiResponse<{ message: string }>>('/contact', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    // ============================================================================
    // Booking Request (demande de reservation)
    // ============================================================================

    async sendBookingRequest(data: {
        name: string
        email: string
        phone?: string
        prestation_id: string
        date_preferences: string
        message?: string
        gdpr_consent: boolean
    }): Promise<ApiResponse<{ success: boolean; message: string }>> {
        return this.request<ApiResponse<{ success: boolean; message: string }>>('/booking-request', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }
}

// Export singleton instance
export const api = new ApiService()

// Export default pour compatibilité
export default api
