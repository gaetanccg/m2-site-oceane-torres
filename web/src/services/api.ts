/**
 * Service API public (non-authentifié)
 */

import { BaseApiService, ApiError as BaseApiError } from './baseApi'
import type { ApiResponse, PaginatedResponse, GalleryItem, Prestation, GiftCard } from '@/types'

export { BaseApiError as ApiServiceError }

class ApiService extends BaseApiService {
    // ========================================================================
    // Gallery
    // ========================================================================

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

    // ========================================================================
    // Prestations
    // ========================================================================

    async getPrestations(): Promise<ApiResponse<Prestation[]>> {
        return this.request<ApiResponse<Prestation[]>>('/prestations')
    }

    async getPrestation(id: number): Promise<ApiResponse<Prestation>> {
        return this.request<ApiResponse<Prestation>>(`/prestations/${id}`)
    }

    // ========================================================================
    // Gift Cards
    // ========================================================================

    async getGiftCards(): Promise<ApiResponse<GiftCard[]>> {
        return this.request<ApiResponse<GiftCard[]>>('/gift-cards')
    }

    // ========================================================================
    // Contact Form
    // ========================================================================

    async sendContactMessage(data: {
        name: string
        email: string
        phone?: string
        subject: string
        message: string
        gdpr_consent: boolean
    }): Promise<ApiResponse<{ message: string }>> {
        return this.request<ApiResponse<{ message: string }>>('/contact', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    // ========================================================================
    // Booking Request
    // ========================================================================

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

export const api = new ApiService()
export default api
