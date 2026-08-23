import { BaseAdminService } from './baseAdmin'
import type { AdminPaginatedResponse, Reservation, Client, ClientFormData, ClientGdprExport, SortState } from '@/types/admin'

class ClientApiService extends BaseAdminService {
    async getClients(
        page = 1,
        perPage = 20,
        search?: string,
        source?: string,
        sort?: SortState,
    ): Promise<AdminPaginatedResponse<Client>> {
        let query = `?page=${page}&per_page=${perPage}`
        if (search) query += `&search=${encodeURIComponent(search)}`
        if (source) query += `&source=${encodeURIComponent(source)}`
        if (sort) query += `&sort_by=${sort.by}&sort_dir=${sort.dir}`
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

    async getClientReservations(id: string, page = 1): Promise<AdminPaginatedResponse<Reservation>> {
        return this.adminRequest<AdminPaginatedResponse<Reservation>>(`/admin/clients/${id}/reservations?page=${page}`)
    }

    async exportClientGdpr(id: string): Promise<{ data: ClientGdprExport; message: string }> {
        return this.adminRequest<{ data: ClientGdprExport; message: string }>(`/admin/clients/${id}/gdpr-export`, {
            method: 'POST',
        })
    }
}

export const clientApi = new ClientApiService()
