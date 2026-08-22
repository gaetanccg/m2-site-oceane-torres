/**
 * Service API public (non-authentifié)
 */

import { BaseApiService, ApiError as BaseApiError } from './baseApi'
import type { ApiResponse, Prestation } from '@/types'

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
}

export const api = new ApiService()
export default api
