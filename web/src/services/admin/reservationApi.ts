import { BaseAdminService } from './baseAdmin'
import type { AdminApiResponse, AdminPaginatedResponse, Reservation, ReservationStatus, CalendarEvent } from '@/types/admin'

class ReservationApiService extends BaseAdminService {
    async getReservations(page = 1, perPage = 20, status?: ReservationStatus): Promise<AdminPaginatedResponse<Reservation>> {
        let query = `?page=${page}&per_page=${perPage}`
        if (status) query += `&status=${status}`
        return this.adminRequest<AdminPaginatedResponse<Reservation>>(`/admin/reservations${query}`)
    }

    async getReservation(id: string): Promise<AdminApiResponse<Reservation>> {
        return this.adminRequest<AdminApiResponse<Reservation>>(`/admin/reservations/${id}`)
    }

    async updateReservationStatus(id: string, status: ReservationStatus): Promise<AdminApiResponse<Reservation>> {
        return this.adminRequest<AdminApiResponse<Reservation>>(`/admin/reservations/${id}/status`, {
            method: 'PUT',
            body: JSON.stringify({ status }),
        })
    }

    async updateReservation(id: string, data: { date?: string; time?: string; status?: ReservationStatus; notes?: string }): Promise<AdminApiResponse<Reservation>> {
        return this.adminRequest<AdminApiResponse<Reservation>>(`/admin/reservations/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async getCalendarEvents(start: string, end: string): Promise<AdminApiResponse<CalendarEvent[]>> {
        return this.adminRequest<AdminApiResponse<CalendarEvent[]>>(`/admin/reservations/calendar?start=${start}&end=${end}`)
    }

    async deleteReservation(id: string): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>(`/admin/reservations/${id}`, { method: 'DELETE' })
    }
}

export const reservationApi = new ReservationApiService()
