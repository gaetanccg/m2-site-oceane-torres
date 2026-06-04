import { BaseAdminService } from './baseAdmin'
import type { AdminGiftCode, AdminPaginatedResponse, GiftCodeFormData } from '@/types/admin'

class GiftCodeApiService extends BaseAdminService {
    async getGiftCodes(page = 1, perPage = 20, search = '', isActive?: boolean): Promise<AdminPaginatedResponse<AdminGiftCode>> {
        const params = new URLSearchParams({ page: String(page), per_page: String(perPage) })
        if (search) params.set('search', search)
        if (isActive !== undefined) params.set('is_active', isActive ? '1' : '0')
        return this.adminRequest<AdminPaginatedResponse<AdminGiftCode>>(`/admin/gift-codes?${params.toString()}`)
    }

    async getGiftCode(id: string): Promise<{ gift_code: AdminGiftCode }> {
        return this.adminRequest<{ gift_code: AdminGiftCode }>(`/admin/gift-codes/${id}`)
    }

    async createGiftCode(data: GiftCodeFormData): Promise<{ gift_code: AdminGiftCode; message: string }> {
        return this.adminRequest('/admin/gift-codes', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async updateGiftCode(id: string, data: GiftCodeFormData): Promise<{ gift_code: AdminGiftCode; message: string }> {
        return this.adminRequest(`/admin/gift-codes/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async toggleGiftCode(id: string): Promise<{ gift_code: AdminGiftCode; message: string }> {
        return this.adminRequest(`/admin/gift-codes/${id}/toggle`, {
            method: 'PUT',
        })
    }

    async deleteGiftCode(id: string): Promise<{ success: boolean; message: string }> {
        return this.adminRequest(`/admin/gift-codes/${id}`, {
            method: 'DELETE',
        })
    }

    async generateCode(): Promise<{ code: string }> {
        return this.adminRequest<{ code: string }>('/admin/gift-codes/generate-code')
    }
}

export const giftCodeApi = new GiftCodeApiService()
