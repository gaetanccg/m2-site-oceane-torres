import { BaseApiService, ApiError as BaseApiError } from '../baseApi'
import { emitSessionExpired } from '@/utils/authEvents'
import type { AdminApiResponse, AdminPhoto } from '@/types/admin'

export class AdminApiError extends BaseApiError {}

export class BaseAdminService extends BaseApiService {
    protected adminRequest<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
        return this.request<T>(endpoint, options, {
            headers: this.authHeaders(),
            withCredentials: true,
            onUnauthorized: () => {
                emitSessionExpired()
            },
        })
    }

    protected async uploadFiles<T>(path: string, files: File[]): Promise<T> {
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

    async uploadPhotos(galleryId: string, files: File[]): Promise<AdminApiResponse<AdminPhoto[]>> {
        return this.uploadFiles<AdminApiResponse<AdminPhoto[]>>(`/admin/galleries/${galleryId}/photos`, files)
    }

    async uploadEventPhotos(galleryId: string, files: File[]): Promise<AdminApiResponse<AdminPhoto[]>> {
        return this.uploadFiles<AdminApiResponse<AdminPhoto[]>>(`/admin/events/${galleryId}/photos`, files)
    }
}
