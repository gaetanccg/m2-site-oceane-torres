/**
 * Service API pour l'administration
 * Gère toutes les requêtes vers l'API admin Laravel
 */

import { API_CONFIG } from '@/config/constants'
import type {
    AdminApiResponse,
    AdminPaginatedResponse,
    AuthResponse,
    User,
    DashboardStats,
    RecentActivity,
    Reservation,
    ReservationStatus,
    CalendarEvent,
    Client,
    ClientFormData,
    ClientGdprExport,
    AdminPrestation,
    PrestationFormData,
    AdminGallery,
    AdminPhoto,
    GalleryFormData,
    EventGalleryFormData,
    AdminGiftCard,
    AdminOrder,
    Notification,
} from '@/types/admin'
import { extractErrorFromResponse, parseApiError, ERROR_MESSAGES, type ApiError } from '@/utils/errorHandler'

export class AdminApiError extends Error {
    public apiError: ApiError

    constructor(apiError: ApiError) {
        super(apiError.message)
        this.name = 'AdminApiError'
        this.apiError = apiError
    }
}

class AdminApiService {
    private baseUrl: string
    private apiOrigin: string
    private timeout: number

    constructor() {
        this.baseUrl = API_CONFIG.baseUrl
        // Extraire l'origine (http://localhost:8000) depuis l'URL de base
        const url = new URL(this.baseUrl)
        this.apiOrigin = url.origin
        this.timeout = API_CONFIG.timeout
    }

    private getToken(): string | null {
        return localStorage.getItem('auth_token')
    }

    /**
     * Récupère le cookie CSRF depuis Laravel Sanctum
     * Nécessaire avant toute requête authentifiée en mode SPA
     */
    async getCsrfCookie(): Promise<void> {
        await fetch(`${this.apiOrigin}/sanctum/csrf-cookie`, {
            method: 'GET',
            credentials: 'include',
        })
    }

    /**
     * Récupère la valeur du token XSRF depuis les cookies
     */
    private getXsrfToken(): string | null {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
        if (match) {
            return decodeURIComponent(match[1])
        }
        return null
    }

    private async request<T>(
        endpoint: string,
        options: RequestInit = {}
    ): Promise<T> {
        const controller = new AbortController()
        const timeoutId = setTimeout(() => controller.abort(), this.timeout)

        const token = this.getToken()
        const xsrfToken = this.getXsrfToken()

        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        }

        if (token) {
            headers['Authorization'] = `Bearer ${token}`
        }

        // Ajouter le token XSRF pour la protection CSRF
        if (xsrfToken) {
            headers['X-XSRF-TOKEN'] = xsrfToken
        }

        try {
            const response = await fetch(`${this.baseUrl}${endpoint}`, {
                ...options,
                signal: controller.signal,
                credentials: 'include', // Important pour envoyer les cookies
                headers: {
                    ...headers,
                    ...options.headers,
                },
            })

            clearTimeout(timeoutId)

            if (response.status === 401) {
                localStorage.removeItem('auth_token')
                const apiError: ApiError = {
                    status: 401,
                    message: ERROR_MESSAGES.auth.sessionExpired,
                    isNetworkError: false,
                    isValidationError: false,
                    isAuthError: true,
                    isServerError: false,
                }
                window.location.href = '/?login=true'
                throw new AdminApiError(apiError)
            }

            if (!response.ok) {
                const apiError = await extractErrorFromResponse(response)
                throw new AdminApiError(apiError)
            }

            return await response.json()
        } catch (error) {
            clearTimeout(timeoutId)

            if (error instanceof AdminApiError) {
                throw error
            }

            const apiError = parseApiError(error)
            throw new AdminApiError(apiError)
        }
    }

    // ========================================================================
    // Auth
    // ========================================================================

    async login(email: string, password: string): Promise<AdminApiResponse<AuthResponse>> {
        // Récupérer le cookie CSRF avant le login
        await this.getCsrfCookie()

        return this.request<AdminApiResponse<AuthResponse>>('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        })
    }

    async logout(): Promise<AdminApiResponse<null>> {
        return this.request<AdminApiResponse<null>>('/auth/logout', {
            method: 'POST',
        })
    }

    async getUser(): Promise<AdminApiResponse<User>> {
        return this.request<AdminApiResponse<User>>('/auth/user')
    }

    // ========================================================================
    // Dashboard
    // ========================================================================

    async getDashboardStats(): Promise<AdminApiResponse<DashboardStats>> {
        return this.request<AdminApiResponse<DashboardStats>>('/admin/dashboard/stats')
    }

    async getRecentActivity(): Promise<AdminApiResponse<RecentActivity[]>> {
        return this.request<AdminApiResponse<RecentActivity[]>>('/admin/dashboard')
    }

    // ========================================================================
    // Reservations
    // ========================================================================

    async getReservations(
        page = 1,
        perPage = 20,
        status?: ReservationStatus
    ): Promise<AdminPaginatedResponse<Reservation>> {
        let query = `?page=${page}&per_page=${perPage}`
        if (status) query += `&status=${status}`
        return this.request<AdminPaginatedResponse<Reservation>>(`/admin/reservations${query}`)
    }

    async getReservation(id: string): Promise<AdminApiResponse<Reservation>> {
        return this.request<AdminApiResponse<Reservation>>(`/admin/reservations/${id}`)
    }

    async updateReservationStatus(
        id: string,
        status: ReservationStatus
    ): Promise<AdminApiResponse<Reservation>> {
        return this.request<AdminApiResponse<Reservation>>(`/admin/reservations/${id}/status`, {
            method: 'PUT',
            body: JSON.stringify({ status }),
        })
    }

    async updateReservation(
        id: string,
        data: { date?: string; time?: string; status?: ReservationStatus; notes?: string }
    ): Promise<AdminApiResponse<Reservation>> {
        return this.request<AdminApiResponse<Reservation>>(`/admin/reservations/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async getCalendarEvents(
        start: string,
        end: string
    ): Promise<AdminApiResponse<CalendarEvent[]>> {
        return this.request<AdminApiResponse<CalendarEvent[]>>(
            `/admin/reservations/calendar?start=${start}&end=${end}`
        )
    }

    async deleteReservation(id: string): Promise<AdminApiResponse<null>> {
        return this.request<AdminApiResponse<null>>(`/admin/reservations/${id}`, {
            method: 'DELETE',
        })
    }

    // ========================================================================
    // Clients
    // ========================================================================

    async getClients(
        page = 1,
        perPage = 20,
        search?: string,
        source?: string
    ): Promise<AdminPaginatedResponse<Client>> {
        let query = `?page=${page}&per_page=${perPage}`
        if (search) query += `&search=${encodeURIComponent(search)}`
        if (source) query += `&source=${encodeURIComponent(source)}`
        return this.request<AdminPaginatedResponse<Client>>(`/admin/clients${query}`)
    }

    async getClient(id: string): Promise<{ client: Client }> {
        return this.request<{ client: Client }>(`/admin/clients/${id}`)
    }

    async createClient(data: ClientFormData): Promise<{ client: Client; message: string }> {
        return this.request<{ client: Client; message: string }>('/admin/clients', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async updateClient(id: string, data: Partial<ClientFormData>): Promise<{ client: Client; message: string }> {
        return this.request<{ client: Client; message: string }>(`/admin/clients/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async deleteClient(id: string): Promise<{ message: string }> {
        return this.request<{ message: string }>(`/admin/clients/${id}`, {
            method: 'DELETE',
        })
    }

    async getClientReservations(id: string, page = 1): Promise<AdminPaginatedResponse<Reservation>> {
        return this.request<AdminPaginatedResponse<Reservation>>(`/admin/clients/${id}/reservations?page=${page}`)
    }

    async exportClientGdpr(id: string): Promise<{ data: ClientGdprExport; message: string }> {
        return this.request<{ data: ClientGdprExport; message: string }>(`/admin/clients/${id}/gdpr-export`, {
            method: 'POST',
        })
    }

    // ========================================================================
    // Prestations
    // ========================================================================

    async getPrestations(): Promise<AdminApiResponse<AdminPrestation[]>> {
        return this.request<AdminApiResponse<AdminPrestation[]>>('/admin/prestations')
    }

    async getPrestation(id: string): Promise<AdminApiResponse<AdminPrestation>> {
        return this.request<AdminApiResponse<AdminPrestation>>(`/prestations/${id}`)
    }

    async createPrestation(data: PrestationFormData): Promise<AdminApiResponse<AdminPrestation>> {
        return this.request<AdminApiResponse<AdminPrestation>>('/admin/prestations', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async updatePrestation(
        id: string,
        data: PrestationFormData
    ): Promise<AdminApiResponse<AdminPrestation>> {
        return this.request<AdminApiResponse<AdminPrestation>>(`/admin/prestations/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async deletePrestation(id: string): Promise<AdminApiResponse<null>> {
        return this.request<AdminApiResponse<null>>(`/admin/prestations/${id}`, {
            method: 'DELETE',
        })
    }

    async togglePrestation(id: string): Promise<AdminApiResponse<AdminPrestation>> {
        return this.request<AdminApiResponse<AdminPrestation>>(`/admin/prestations/${id}/toggle`, {
            method: 'PUT',
        })
    }

    // ========================================================================
    // Galleries
    // ========================================================================

    async getGalleries(
        page = 1,
        perPage = 20
    ): Promise<AdminPaginatedResponse<AdminGallery>> {
        const query = `?page=${page}&per_page=${perPage}`
        return this.request<AdminPaginatedResponse<AdminGallery>>(`/admin/galleries${query}`)
    }

    async getGallery(id: string): Promise<AdminApiResponse<AdminGallery>> {
        return this.request<AdminApiResponse<AdminGallery>>(`/admin/galleries/${id}`)
    }

    async createGallery(data: GalleryFormData): Promise<AdminApiResponse<AdminGallery>> {
        return this.request<AdminApiResponse<AdminGallery>>('/admin/galleries', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async updateGallery(id: string, data: GalleryFormData): Promise<AdminApiResponse<AdminGallery>> {
        return this.request<AdminApiResponse<AdminGallery>>(`/admin/galleries/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async deleteGallery(id: string): Promise<AdminApiResponse<null>> {
        return this.request<AdminApiResponse<null>>(`/admin/galleries/${id}`, {
            method: 'DELETE',
        })
    }

    async regenerateGalleryToken(id: string): Promise<AdminApiResponse<{ token: string }>> {
        return this.request<AdminApiResponse<{ token: string }>>(
            `/admin/galleries/${id}/regenerate-token`,
            { method: 'PUT' }
        )
    }

    async regenerateGalleryCode(id: string): Promise<AdminApiResponse<{ share_code: string }>> {
        return this.request<AdminApiResponse<{ share_code: string }>>(
            `/admin/galleries/${id}/regenerate-code`,
            { method: 'POST' }
        )
    }

    async sendGalleryAccessEmail(
        galleryId: string,
        email: string,
        recipientName: string
    ): Promise<AdminApiResponse<{ message: string }>> {
        return this.request<AdminApiResponse<{ message: string }>>(
            `/admin/galleries/${galleryId}/send-email`,
            {
                method: 'POST',
                body: JSON.stringify({ email, recipient_name: recipientName }),
            }
        )
    }

    async togglePhotoDownloadable(id: string): Promise<AdminApiResponse<{ is_downloadable: boolean }>> {
        return this.request<AdminApiResponse<{ is_downloadable: boolean }>>(
            `/admin/photos/${id}/toggle-downloadable`,
            { method: 'PUT' }
        )
    }

    async uploadPhotos(galleryId: string, files: File[]): Promise<AdminApiResponse<AdminPhoto[]>> {
        const formData = new FormData()
        files.forEach((file) => formData.append('photos[]', file))

        const token = this.getToken()
        const xsrfToken = this.getXsrfToken()
        const headers: Record<string, string> = {
            'Accept': 'application/json',
        }
        if (token) {
            headers['Authorization'] = `Bearer ${token}`
        }
        if (xsrfToken) {
            headers['X-XSRF-TOKEN'] = xsrfToken
        }

        const response = await fetch(`${this.baseUrl}/admin/galleries/${galleryId}/photos`, {
            method: 'POST',
            headers,
            credentials: 'include',
            body: formData,
        })

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }

        return response.json()
    }

    async deletePhoto(id: string): Promise<AdminApiResponse<null>> {
        return this.request<AdminApiResponse<null>>(`/admin/photos/${id}`, {
            method: 'DELETE',
        })
    }

    // ========================================================================
    // Event Galleries
    // ========================================================================

    async getEventGalleries(
        page = 1,
        perPage = 20
    ): Promise<AdminPaginatedResponse<AdminGallery>> {
        const query = `?page=${page}&per_page=${perPage}`
        return this.request<AdminPaginatedResponse<AdminGallery>>(`/admin/events${query}`)
    }

    async getEventGallery(id: string): Promise<AdminApiResponse<AdminGallery>> {
        return this.request<AdminApiResponse<AdminGallery>>(`/admin/events/${id}`)
    }

    async createEventGallery(data: EventGalleryFormData): Promise<AdminApiResponse<AdminGallery>> {
        return this.request<AdminApiResponse<AdminGallery>>('/admin/events', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async updateEventGallery(id: string, data: EventGalleryFormData): Promise<AdminApiResponse<AdminGallery>> {
        return this.request<AdminApiResponse<AdminGallery>>(`/admin/events/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async deleteEventGallery(id: string): Promise<AdminApiResponse<null>> {
        return this.request<AdminApiResponse<null>>(`/admin/events/${id}`, {
            method: 'DELETE',
        })
    }

    async uploadEventPhotos(galleryId: string, files: File[]): Promise<AdminApiResponse<AdminPhoto[]>> {
        const formData = new FormData()
        files.forEach((file) => formData.append('photos[]', file))

        const token = this.getToken()
        const xsrfToken = this.getXsrfToken()
        const headers: Record<string, string> = {
            'Accept': 'application/json',
        }
        if (token) {
            headers['Authorization'] = `Bearer ${token}`
        }
        if (xsrfToken) {
            headers['X-XSRF-TOKEN'] = xsrfToken
        }

        const response = await fetch(`${this.baseUrl}/admin/events/${galleryId}/photos`, {
            method: 'POST',
            headers,
            credentials: 'include',
            body: formData,
        })

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }

        return response.json()
    }

    // ========================================================================
    // Gift Cards
    // ========================================================================

    async getGiftCards(page = 1, perPage = 20): Promise<AdminPaginatedResponse<AdminGiftCard>> {
        return this.request<AdminPaginatedResponse<AdminGiftCard>>(
            `/admin/gift-cards?page=${page}&per_page=${perPage}`
        )
    }

    async updateGiftCard(
        id: string,
        data: Partial<AdminGiftCard>
    ): Promise<AdminApiResponse<AdminGiftCard>> {
        return this.request<AdminApiResponse<AdminGiftCard>>(`/admin/gift-cards/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    // ========================================================================
    // Orders
    // ========================================================================

    async getOrders(
        page = 1,
        perPage = 20,
        status?: string,
        search?: string
    ): Promise<{
        success: boolean
        orders: AdminOrder[]
        pagination: {
            current_page: number
            last_page: number
            per_page: number
            total: number
        }
    }> {
        const params = new URLSearchParams()
        params.append('page', page.toString())
        params.append('per_page', perPage.toString())
        if (status) params.append('status', status)
        if (search) params.append('search', search)

        return this.request(`/admin/orders?${params.toString()}`)
    }

    async getOrder(id: string): Promise<{ success: boolean; order: AdminOrder }> {
        return this.request(`/admin/orders/${id}`)
    }

    async deleteOrder(id: string): Promise<{ success: boolean; message: string }> {
        return this.request(`/admin/orders/${id}`, {
            method: 'DELETE',
        })
    }

    // ========================================================================
    // Notifications
    // ========================================================================

    async getNotifications(): Promise<AdminApiResponse<Notification[]>> {
        return this.request<AdminApiResponse<Notification[]>>('/notifications')
    }

    async markNotificationRead(id: string): Promise<AdminApiResponse<null>> {
        return this.request<AdminApiResponse<null>>(`/notifications/${id}/read`, {
            method: 'PUT',
        })
    }

    async markAllNotificationsRead(): Promise<AdminApiResponse<null>> {
        return this.request<AdminApiResponse<null>>('/notifications/read-all', {
            method: 'PUT',
        })
    }
}

export const adminApi = new AdminApiService()
export default adminApi
