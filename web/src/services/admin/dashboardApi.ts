import { BaseAdminService } from './baseAdmin'
import type { AdminApiResponse, AuthResponse, User, DashboardStats, DashboardRecent, GiftCardStats, PromoCodeStats, Notification } from '@/types/admin'

class DashboardApiService extends BaseAdminService {
    // Auth
    async login(email: string, password: string): Promise<AdminApiResponse<AuthResponse>> {
        await this.getCsrfCookie()
        return this.adminRequest<AdminApiResponse<AuthResponse>>('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        })
    }

    async logout(): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>('/auth/logout', { method: 'POST' })
    }

    async getUser(): Promise<AdminApiResponse<User>> {
        return this.adminRequest<AdminApiResponse<User>>('/auth/user')
    }

    // Dashboard — les deux seuls appels dont il a besoin : les chiffres sont agreges en
    // base, les encarts recents deja limites a cinq lignes cote serveur.
    async getDashboardStats(): Promise<DashboardStats> {
        return this.adminRequest<DashboardStats>('/admin/dashboard/stats')
    }

    async getDashboardRecent(): Promise<DashboardRecent> {
        return this.adminRequest<DashboardRecent>('/admin/dashboard')
    }

    async getGiftCardStats(): Promise<GiftCardStats> {
        return this.adminRequest<GiftCardStats>('/admin/gift-cards/stats')
    }

    async getPromoCodeStats(): Promise<PromoCodeStats> {
        return this.adminRequest<PromoCodeStats>('/admin/gift-codes/stats')
    }

    // Notifications
    async getNotifications(): Promise<AdminApiResponse<Notification[]>> {
        return this.adminRequest<AdminApiResponse<Notification[]>>('/notifications')
    }

    async markNotificationRead(id: string): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>(`/notifications/${id}/read`, { method: 'PUT' })
    }

    async markAllNotificationsRead(): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>('/notifications/read-all', { method: 'PUT' })
    }
}

export const dashboardApi = new DashboardApiService()
