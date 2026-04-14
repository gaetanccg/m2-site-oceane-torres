import { BaseAdminService } from './baseAdmin'
import type { AdminApiResponse, AuthResponse, User, DashboardStats, RecentActivity, Notification } from '@/types/admin'

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

    // Dashboard
    async getDashboardStats(): Promise<AdminApiResponse<DashboardStats>> {
        return this.adminRequest<AdminApiResponse<DashboardStats>>('/admin/dashboard/stats')
    }

    async getRecentActivity(): Promise<AdminApiResponse<RecentActivity[]>> {
        return this.adminRequest<AdminApiResponse<RecentActivity[]>>('/admin/dashboard')
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
