import { BaseAdminService } from './baseAdmin'
import type { AdminOrder } from '@/types/admin'

class OrderApiService extends BaseAdminService {
    async getOrders(page = 1, perPage = 20, status?: string, search?: string): Promise<{
        success: boolean
        orders: AdminOrder[]
        pagination: { current_page: number; last_page: number; per_page: number; total: number }
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
        return this.adminRequest(`/admin/orders/${id}`, { method: 'DELETE' })
    }

    async markOrderShipped(id: string): Promise<{ success: boolean; message: string; order: AdminOrder }> {
        return this.adminRequest(`/admin/orders/${id}/ship`, { method: 'PUT' })
    }

    async getOrderDownloadLink(id: string): Promise<{ success: boolean; download_link: string }> {
        return this.adminRequest(`/admin/orders/${id}/download-link`)
    }

    async retryOrderPayment(id: string): Promise<{ success: boolean; message: string; order: AdminOrder }> {
        return this.adminRequest(`/admin/orders/${id}/retry-payment`, { method: 'POST' })
    }
}

export const orderApi = new OrderApiService()
