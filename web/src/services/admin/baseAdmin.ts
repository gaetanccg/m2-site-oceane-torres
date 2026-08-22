import { BaseApiService, ApiError as BaseApiError } from '../baseApi'
import { emitSessionExpired } from '@/utils/authEvents'

export class AdminApiError extends BaseApiError {}

export class BaseAdminService extends BaseApiService {
    protected adminRequest<T>(endpoint: string, options: RequestInit = {}, timeout?: number): Promise<T> {
        return this.request<T>(endpoint, options, {
            headers: this.authHeaders(),
            withCredentials: true,
            timeout,
            onUnauthorized: () => {
                emitSessionExpired()
            },
        })
    }

    protected adminBlob(endpoint: string, options: RequestInit = {}): Promise<Blob> {
        return this.requestBlob(endpoint, options, {
            headers: this.authHeaders(),
            withCredentials: true,
            onUnauthorized: () => {
                emitSessionExpired()
            },
        })
    }
}
