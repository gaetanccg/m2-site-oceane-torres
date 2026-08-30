import { BaseAdminService } from './baseAdmin'
import type { AdminApiResponse, DashboardStats, DashboardRecent, GiftCardStats, PromoCodeStats, Notification } from '@/types/admin'

/**
 * L'authentification ne passe PAS par ici : `authApi` porte `login`, `logout` et `getUser`,
 * sur les memes endpoints. Ce service en gardait des copies, sans appelant — elles suffisaient
 * a tirer les dix sous-services d'administration dans le bundle de chaque visiteur public.
 */
class DashboardApiService extends BaseAdminService {
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
