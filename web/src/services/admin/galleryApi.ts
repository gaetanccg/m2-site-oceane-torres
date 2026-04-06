import { BaseAdminService } from './baseAdmin'
import type {
    AdminApiResponse,
    AdminPaginatedResponse,
    AdminGallery,
    AdminPhoto,
    GalleryFormData,
    EventGalleryFormData,
    EventCategory,
    EventCategoryFormData,
    AdminGiftCard,
} from '@/types/admin'

class GalleryApiService extends BaseAdminService {
    // Client galleries
    async getGalleries(page = 1, perPage = 20): Promise<AdminPaginatedResponse<AdminGallery>> {
        return this.adminRequest<AdminPaginatedResponse<AdminGallery>>(`/admin/galleries?page=${page}&per_page=${perPage}`)
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
        return this.adminRequest<AdminApiResponse<null>>(`/admin/galleries/${id}`, { method: 'DELETE' })
    }

    async regenerateGalleryToken(id: string): Promise<AdminApiResponse<{ token: string }>> {
        return this.adminRequest<AdminApiResponse<{ token: string }>>(`/admin/galleries/${id}/regenerate-token`, { method: 'PUT' })
    }

    async regenerateGalleryCode(id: string): Promise<AdminApiResponse<{ share_code: string }>> {
        return this.adminRequest<AdminApiResponse<{ share_code: string }>>(`/admin/galleries/${id}/regenerate-code`, { method: 'POST' })
    }

    async sendGalleryAccessEmail(galleryId: string, email: string, recipientName: string): Promise<AdminApiResponse<{ message: string }>> {
        return this.adminRequest<AdminApiResponse<{ message: string }>>(`/admin/galleries/${galleryId}/send-email`, {
            method: 'POST',
            body: JSON.stringify({ email, recipient_name: recipientName }),
        })
    }

    async togglePhotoDownloadable(id: string): Promise<AdminApiResponse<{ is_downloadable: boolean }>> {
        return this.adminRequest<AdminApiResponse<{ is_downloadable: boolean }>>(`/admin/photos/${id}/toggle-downloadable`, { method: 'PUT' })
    }

    async deletePhoto(id: string): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>(`/admin/photos/${id}`, { method: 'DELETE' })
    }

    // Event galleries
    async getEventGalleries(page = 1, perPage = 20): Promise<AdminPaginatedResponse<AdminGallery>> {
        return this.adminRequest<AdminPaginatedResponse<AdminGallery>>(`/admin/events?page=${page}&per_page=${perPage}`)
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
        return this.adminRequest<AdminApiResponse<null>>(`/admin/events/${id}`, { method: 'DELETE' })
    }

    async getEventGalleryChildren(parentId: string): Promise<AdminApiResponse<AdminGallery[]>> {
        return this.adminRequest<AdminApiResponse<AdminGallery[]>>(`/admin/events/${parentId}/children`)
    }

    async setEventThumbnail(galleryId: string, photoId: string | null): Promise<AdminApiResponse<AdminGallery>> {
        return this.adminRequest<AdminApiResponse<AdminGallery>>(`/admin/events/${galleryId}/thumbnail`, {
            method: 'PUT',
            body: JSON.stringify({ photo_id: photoId }),
        })
    }

    // Event categories
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
        return this.adminRequest<AdminApiResponse<null>>(`/admin/event-categories/${id}`, { method: 'DELETE' })
    }

    async reorderEventCategories(categories: Array<{ id: string; sort_order: number }>): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>('/admin/event-categories/reorder', {
            method: 'PUT',
            body: JSON.stringify({ categories }),
        })
    }

    // Gift cards
    async getGiftCards(page = 1, perPage = 20): Promise<AdminPaginatedResponse<AdminGiftCard>> {
        return this.adminRequest<AdminPaginatedResponse<AdminGiftCard>>(`/admin/gift-cards?page=${page}&per_page=${perPage}`)
    }

    async updateGiftCard(id: string, data: Partial<AdminGiftCard>): Promise<AdminApiResponse<AdminGiftCard>> {
        return this.adminRequest<AdminApiResponse<AdminGiftCard>>(`/admin/gift-cards/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }
}

export const galleryApi = new GalleryApiService()
