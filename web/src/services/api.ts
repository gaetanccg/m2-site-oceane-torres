/**
 * Service API public (non-authentifié)
 */

import { BaseApiService, ApiError as BaseApiError } from './baseApi'
import type { ApiResponse, PaginatedResponse, Prestation } from '@/types'

export { BaseApiError as ApiServiceError }

class ApiService extends BaseApiService {
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

    // ========================================================================
    // Galeries publiques
    // ========================================================================

    async getGalleryByShareCode<T>(code: string): Promise<T> {
        return this.request<T>(`/galleries/code/${encodeURIComponent(code)}`)
    }

    async getDownloadableGallery<T>(token: string): Promise<T> {
        return this.request<T>(`/galleries/download/${encodeURIComponent(token)}`)
    }

    // ========================================================================
    // Photos publiques
    // ========================================================================

    async getPhotoDownloadUrl(
        photoId: string,
        token: string
    ): Promise<{ download_url: string; filename: string }> {
        return this.request(`/photos/${photoId}/download?token=${encodeURIComponent(token)}`)
    }

    async likePhoto(photoId: string): Promise<{ success: boolean; is_liked: boolean }> {
        return this.request(`/photos/${photoId}/like`, { method: 'POST' })
    }

    // ========================================================================
    // Galeries d'evenements (publiques)
    // ========================================================================

    async getEvents<T>(page = 1): Promise<PaginatedResponse<T>> {
        return this.request<PaginatedResponse<T>>(`/events?page=${page}`)
    }

    async getEvent<T>(id: string): Promise<T> {
        return this.request<T>(`/events/${id}`)
    }
}

export const api = new ApiService()
export default api
