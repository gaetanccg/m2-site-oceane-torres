/**
 * Service API pour l'administration
 */

import { BaseApiService, ApiError as BaseApiError } from './baseApi'
import { emitSessionExpired } from '@/utils/authEvents'
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
    EventCategory,
    EventCategoryFormData,
    AdminGiftCard,
    AdminOrder,
    Notification,
} from '@/types/admin'

export class AdminApiError extends BaseApiError {}

class AdminApiService extends BaseApiService {
    private adminRequest<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
        return this.request<T>(endpoint, options, {
            headers: this.authHeaders(),
            withCredentials: true,
            onUnauthorized: () => {
                emitSessionExpired()
            },
        })
    }

    /** Upload files via FormData (no JSON content-type) */
    private async uploadFiles<T>(path: string, files: File[]): Promise<T> {
        const formData = new FormData()
        files.forEach((file) => formData.append('photos[]', file))

        const headers: Record<string, string> = {
            'Accept': 'application/json',
            ...this.authHeaders(),
        }

        const response = await fetch(`${this.baseUrl}${path}`, {
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
    // Auth
    // ========================================================================

    async login(email: string, password: string): Promise<AdminApiResponse<AuthResponse>> {
        await this.getCsrfCookie()
        return this.adminRequest<AdminApiResponse<AuthResponse>>('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        })
    }

    async logout(): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>('/auth/logout', {
            method: 'POST',
        })
    }

    async getUser(): Promise<AdminApiResponse<User>> {
        return this.adminRequest<AdminApiResponse<User>>('/auth/user')
    }

    // ========================================================================
    // Dashboard
    // ========================================================================

    async getDashboardStats(): Promise<AdminApiResponse<DashboardStats>> {
        return this.adminRequest<AdminApiResponse<DashboardStats>>('/admin/dashboard/stats')
    }

    async getRecentActivity(): Promise<AdminApiResponse<RecentActivity[]>> {
        return this.adminRequest<AdminApiResponse<RecentActivity[]>>('/admin/dashboard')
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
        return this.adminRequest<AdminPaginatedResponse<Reservation>>(`/admin/reservations${query}`)
    }

    async getReservation(id: string): Promise<AdminApiResponse<Reservation>> {
        return this.adminRequest<AdminApiResponse<Reservation>>(`/admin/reservations/${id}`)
    }

    async updateReservationStatus(
        id: string,
        status: ReservationStatus
    ): Promise<AdminApiResponse<Reservation>> {
        return this.adminRequest<AdminApiResponse<Reservation>>(`/admin/reservations/${id}/status`, {
            method: 'PUT',
            body: JSON.stringify({ status }),
        })
    }

    async updateReservation(
        id: string,
        data: { date?: string; time?: string; status?: ReservationStatus; notes?: string }
    ): Promise<AdminApiResponse<Reservation>> {
        return this.adminRequest<AdminApiResponse<Reservation>>(`/admin/reservations/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async getCalendarEvents(
        start: string,
        end: string
    ): Promise<AdminApiResponse<CalendarEvent[]>> {
        return this.adminRequest<AdminApiResponse<CalendarEvent[]>>(
            `/admin/reservations/calendar?start=${start}&end=${end}`
        )
    }

    async deleteReservation(id: string): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>(`/admin/reservations/${id}`, {
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
        return this.adminRequest<AdminPaginatedResponse<Client>>(`/admin/clients${query}`)
    }

    async getClient(id: string): Promise<{ client: Client }> {
        return this.adminRequest<{ client: Client }>(`/admin/clients/${id}`)
    }

    async createClient(data: ClientFormData): Promise<{ client: Client; message: string }> {
        return this.adminRequest<{ client: Client; message: string }>('/admin/clients', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async updateClient(id: string, data: Partial<ClientFormData>): Promise<{ client: Client; message: string }> {
        return this.adminRequest<{ client: Client; message: string }>(`/admin/clients/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async deleteClient(id: string): Promise<{ message: string }> {
        return this.adminRequest<{ message: string }>(`/admin/clients/${id}`, {
            method: 'DELETE',
        })
    }

    async getClientReservations(id: string, page = 1): Promise<AdminPaginatedResponse<Reservation>> {
        return this.adminRequest<AdminPaginatedResponse<Reservation>>(`/admin/clients/${id}/reservations?page=${page}`)
    }

    async exportClientGdpr(id: string): Promise<{ data: ClientGdprExport; message: string }> {
        return this.adminRequest<{ data: ClientGdprExport; message: string }>(`/admin/clients/${id}/gdpr-export`, {
            method: 'POST',
        })
    }

    // ========================================================================
    // Prestations
    // ========================================================================

    async getPrestations(): Promise<AdminApiResponse<AdminPrestation[]>> {
        return this.adminRequest<AdminApiResponse<AdminPrestation[]>>('/admin/prestations')
    }

    async getPrestation(id: string): Promise<AdminApiResponse<AdminPrestation>> {
        return this.adminRequest<AdminApiResponse<AdminPrestation>>(`/prestations/${id}`)
    }

    async createPrestation(data: PrestationFormData): Promise<AdminApiResponse<AdminPrestation>> {
        return this.adminRequest<AdminApiResponse<AdminPrestation>>('/admin/prestations', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async updatePrestation(id: string, data: PrestationFormData): Promise<AdminApiResponse<AdminPrestation>> {
        return this.adminRequest<AdminApiResponse<AdminPrestation>>(`/admin/prestations/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async deletePrestation(id: string): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>(`/admin/prestations/${id}`, {
            method: 'DELETE',
        })
    }

    async togglePrestation(id: string): Promise<AdminApiResponse<AdminPrestation>> {
        return this.adminRequest<AdminApiResponse<AdminPrestation>>(`/admin/prestations/${id}/toggle`, {
            method: 'PUT',
        })
    }

    // ========================================================================
    // Galleries
    // ========================================================================

    async getGalleries(page = 1, perPage = 20): Promise<AdminPaginatedResponse<AdminGallery>> {
        const query = `?page=${page}&per_page=${perPage}`
        return this.adminRequest<AdminPaginatedResponse<AdminGallery>>(`/admin/galleries${query}`)
    }

    async getGallery(id: string): Promise<AdminApiResponse<AdminGallery>> {
        return this.adminRequest<AdminApiResponse<AdminGallery>>(`/admin/galleries/${id}`)
    }

    async createGallery(data: GalleryFormData): Promise<AdminApiResponse<AdminGallery>> {
        return this.adminRequest<AdminApiResponse<AdminGallery>>('/admin/galleries', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async updateGallery(id: string, data: GalleryFormData): Promise<AdminApiResponse<AdminGallery>> {
        return this.adminRequest<AdminApiResponse<AdminGallery>>(`/admin/galleries/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async deleteGallery(id: string): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>(`/admin/galleries/${id}`, {
            method: 'DELETE',
        })
    }

    async regenerateGalleryToken(id: string): Promise<AdminApiResponse<{ token: string }>> {
        return this.adminRequest<AdminApiResponse<{ token: string }>>(
            `/admin/galleries/${id}/regenerate-token`,
            { method: 'PUT' }
        )
    }

    async regenerateGalleryCode(id: string): Promise<AdminApiResponse<{ share_code: string }>> {
        return this.adminRequest<AdminApiResponse<{ share_code: string }>>(
            `/admin/galleries/${id}/regenerate-code`,
            { method: 'POST' }
        )
    }

    async sendGalleryAccessEmail(
        galleryId: string,
        email: string,
        recipientName: string
    ): Promise<AdminApiResponse<{ message: string }>> {
        return this.adminRequest<AdminApiResponse<{ message: string }>>(
            `/admin/galleries/${galleryId}/send-email`,
            {
                method: 'POST',
                body: JSON.stringify({ email, recipient_name: recipientName }),
            }
        )
    }

    async togglePhotoDownloadable(id: string): Promise<AdminApiResponse<{ is_downloadable: boolean }>> {
        return this.adminRequest<AdminApiResponse<{ is_downloadable: boolean }>>(
            `/admin/photos/${id}/toggle-downloadable`,
            { method: 'PUT' }
        )
    }

    async uploadPhotos(galleryId: string, files: File[]): Promise<AdminApiResponse<AdminPhoto[]>> {
        return this.uploadFiles<AdminApiResponse<AdminPhoto[]>>(`/admin/galleries/${galleryId}/photos`, files)
    }

    async deletePhoto(id: string): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>(`/admin/photos/${id}`, {
            method: 'DELETE',
        })
    }

    // ========================================================================
    // Event Galleries
    // ========================================================================

    async getEventGalleries(page = 1, perPage = 20): Promise<AdminPaginatedResponse<AdminGallery>> {
        const query = `?page=${page}&per_page=${perPage}`
        return this.adminRequest<AdminPaginatedResponse<AdminGallery>>(`/admin/events${query}`)
    }

    async getEventGallery(id: string): Promise<AdminApiResponse<AdminGallery>> {
        return this.adminRequest<AdminApiResponse<AdminGallery>>(`/admin/events/${id}`)
    }

    async createEventGallery(data: EventGalleryFormData): Promise<AdminApiResponse<AdminGallery>> {
        return this.adminRequest<AdminApiResponse<AdminGallery>>('/admin/events', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async updateEventGallery(id: string, data: EventGalleryFormData): Promise<AdminApiResponse<AdminGallery>> {
        return this.adminRequest<AdminApiResponse<AdminGallery>>(`/admin/events/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async deleteEventGallery(id: string): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>(`/admin/events/${id}`, {
            method: 'DELETE',
        })
    }

    async uploadEventPhotos(galleryId: string, files: File[]): Promise<AdminApiResponse<AdminPhoto[]>> {
        return this.uploadFiles<AdminApiResponse<AdminPhoto[]>>(`/admin/events/${galleryId}/photos`, files)
    }

    async getEventGalleryChildren(parentId: string): Promise<AdminApiResponse<AdminGallery[]>> {
        return this.adminRequest<AdminApiResponse<AdminGallery[]>>(`/admin/events/${parentId}/children`)
    }

    async setEventThumbnail(
        galleryId: string,
        photoId: string | null
    ): Promise<AdminApiResponse<AdminGallery>> {
        return this.adminRequest<AdminApiResponse<AdminGallery>>(
            `/admin/events/${galleryId}/thumbnail`,
            {
                method: 'PUT',
                body: JSON.stringify({ photo_id: photoId }),
            }
        )
    }

    // ========================================================================
    // Event Categories
    // ========================================================================

    async getEventCategories(): Promise<AdminApiResponse<EventCategory[]>> {
        return this.adminRequest<AdminApiResponse<EventCategory[]>>('/admin/event-categories')
    }

    async createEventCategory(data: EventCategoryFormData): Promise<AdminApiResponse<EventCategory>> {
        return this.adminRequest<AdminApiResponse<EventCategory>>('/admin/event-categories', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async updateEventCategory(id: string, data: EventCategoryFormData): Promise<AdminApiResponse<EventCategory>> {
        return this.adminRequest<AdminApiResponse<EventCategory>>(`/admin/event-categories/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async deleteEventCategory(id: string): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>(`/admin/event-categories/${id}`, {
            method: 'DELETE',
        })
    }

    async reorderEventCategories(categories: Array<{ id: string; sort_order: number }>): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>('/admin/event-categories/reorder', {
            method: 'PUT',
            body: JSON.stringify({ categories }),
        })
    }

    // ========================================================================
    // Gift Cards
    // ========================================================================

    async getGiftCards(page = 1, perPage = 20): Promise<AdminPaginatedResponse<AdminGiftCard>> {
        return this.adminRequest<AdminPaginatedResponse<AdminGiftCard>>(
            `/admin/gift-cards?page=${page}&per_page=${perPage}`
        )
    }

    async updateGiftCard(id: string, data: Partial<AdminGiftCard>): Promise<AdminApiResponse<AdminGiftCard>> {
        return this.adminRequest<AdminApiResponse<AdminGiftCard>>(`/admin/gift-cards/${id}`, {
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

        return this.adminRequest(`/admin/orders?${params.toString()}`)
    }

    async getOrder(id: string): Promise<{ success: boolean; order: AdminOrder }> {
        return this.adminRequest(`/admin/orders/${id}`)
    }

    async deleteOrder(id: string): Promise<{ success: boolean; message: string }> {
        return this.adminRequest(`/admin/orders/${id}`, {
            method: 'DELETE',
        })
    }

    async markOrderShipped(id: string): Promise<{ success: boolean; message: string; order: AdminOrder }> {
        return this.adminRequest(`/admin/orders/${id}/ship`, {
            method: 'PUT',
        })
    }

    async getOrderDownloadLink(id: string): Promise<{ success: boolean; download_link: string }> {
        return this.adminRequest(`/admin/orders/${id}/download-link`)
    }

    async retryOrderPayment(id: string): Promise<{ success: boolean; message: string; order: AdminOrder }> {
        return this.adminRequest(`/admin/orders/${id}/retry-payment`, {
            method: 'POST',
        })
    }

    // ========================================================================
    // Notifications
    // ========================================================================

    async getNotifications(): Promise<AdminApiResponse<Notification[]>> {
        return this.adminRequest<AdminApiResponse<Notification[]>>('/notifications')
    }

    async markNotificationRead(id: string): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>(`/notifications/${id}/read`, {
            method: 'PUT',
        })
    }

    async markAllNotificationsRead(): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>('/notifications/read-all', {
            method: 'PUT',
        })
    }
}

export const adminApi = new AdminApiService()
export default adminApi
