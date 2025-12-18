/**
 * Service API pour l'intégration avec Laravel
 * Prêt pour connecter un backend REST API
 */

import {API_CONFIG} from '@/config/constants'
import type {ApiResponse, PaginatedResponse, GalleryItem, Prestation, GiftCard} from '@/types'

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
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            return await response.json()
        } catch (error) {
            clearTimeout(timeoutId)
            if (error instanceof Error && error.name === 'AbortError') {
                throw new Error('Request timeout')
            }
            throw error
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
        message: string
        subject?: string
    }): Promise<ApiResponse<{ success: boolean }>> {
        return this.request<ApiResponse<{ success: boolean }>>('/contact', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }
}

// Export singleton instance
export const api = new ApiService()

// Export default pour compatibilité
export default api
