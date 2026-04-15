/**
 * Service API pour l'authentification publique (clients)
 */

import { BaseApiService, ApiError as BaseApiError } from './baseApi'
import type { User, AdminApiResponse } from '@/types/admin'
import type { AccountDashboard, RegisterData } from '@/types/account'

export { BaseApiError as AuthApiError }

class AuthApiService extends BaseApiService {
    private authRequest<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
        return this.request<T>(endpoint, options, {
            headers: this.authHeaders(),
            withCredentials: true,
        })
    }

    // ========================================================================
    // Authentication
    // ========================================================================

    async register(data: RegisterData): Promise<{ user: User; token: string }> {
        await this.getCsrfCookie()
        return this.authRequest<{ user: User; token: string }>('/auth/register', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async login(email: string, password: string): Promise<AdminApiResponse<{ user: User; token: string }>> {
        await this.getCsrfCookie()
        return this.authRequest<AdminApiResponse<{ user: User; token: string }>>('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        })
    }

    async logout(): Promise<AdminApiResponse<null>> {
        return this.authRequest<AdminApiResponse<null>>('/auth/logout', {
            method: 'POST',
        })
    }

    async getUser(): Promise<AdminApiResponse<User>> {
        return this.authRequest<AdminApiResponse<User>>('/auth/user')
    }

    // ========================================================================
    // Account
    // ========================================================================

    async getDashboard(): Promise<AdminApiResponse<AccountDashboard>> {
        return this.authRequest<AdminApiResponse<AccountDashboard>>('/account/dashboard')
    }
}

export const authApi = new AuthApiService()
export default authApi
