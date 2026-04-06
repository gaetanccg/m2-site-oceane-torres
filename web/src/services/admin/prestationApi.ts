import { BaseAdminService } from './baseAdmin'
import type { AdminApiResponse, AdminPrestation, PrestationFormData } from '@/types/admin'

class PrestationApiService extends BaseAdminService {
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
        return this.adminRequest<AdminApiResponse<null>>(`/admin/prestations/${id}`, { method: 'DELETE' })
    }

    async togglePrestation(id: string): Promise<AdminApiResponse<AdminPrestation>> {
        return this.adminRequest<AdminApiResponse<AdminPrestation>>(`/admin/prestations/${id}/toggle`, { method: 'PUT' })
    }
}

export const prestationApi = new PrestationApiService()
