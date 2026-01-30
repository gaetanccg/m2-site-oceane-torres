/**
 * Service API pour l'authentification publique
 * Gere les requetes d'authentification pour les clients (non-admin)
 */

import { API_CONFIG } from '@/config/constants'
import type { User, AdminApiResponse } from '@/types/admin'
import type { AccountDashboard, RegisterData } from '@/types/account'
import { extractErrorFromResponse, parseApiError, type ApiError } from '@/utils/errorHandler'

export class AuthApiError extends Error {
    public apiError: ApiError

    constructor(apiError: ApiError) {
        super(apiError.message)
        this.name = 'AuthApiError'
        this.apiError = apiError
    }
}

class AuthApiService {
    private baseUrl: string
    private apiOrigin: string
    private timeout: number

    constructor() {
        this.baseUrl = API_CONFIG.baseUrl
        const url = new URL(this.baseUrl)
        this.apiOrigin = url.origin
        this.timeout = API_CONFIG.timeout
    }

    private getToken(): string | null {
        return localStorage.getItem('auth_token')
    }

    async getCsrfCookie(): Promise<void> {
        await fetch(`${this.apiOrigin}/sanctum/csrf-cookie`, {
            method: 'GET',
            credentials: 'include',
        })
    }

    private getXsrfToken(): string | null {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
        if (match) {
            return decodeURIComponent(match[1])
        }
        return null
    }

    private async request<T>(
        endpoint: string,
        options: RequestInit = {}
    ): Promise<T> {
        const controller = new AbortController()
        const timeoutId = setTimeout(() => controller.abort(), this.timeout)

        const token = this.getToken()
        const xsrfToken = this.getXsrfToken()

        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        }

        if (token) {
            headers['Authorization'] = `Bearer ${token}`
        }

        if (xsrfToken) {
            headers['X-XSRF-TOKEN'] = xsrfToken
        }

        try {
            const response = await fetch(`${this.baseUrl}${endpoint}`, {
                ...options,
                signal: controller.signal,
                credentials: 'include',
                headers: {
                    ...headers,
                    ...options.headers,
                },
            })

            clearTimeout(timeoutId)

            if (!response.ok) {
                const apiError = await extractErrorFromResponse(response)
                throw new AuthApiError(apiError)
            }

            return await response.json()
        } catch (error) {
            clearTimeout(timeoutId)

            if (error instanceof AuthApiError) {
                throw error
            }

            const apiError = parseApiError(error)
            throw new AuthApiError(apiError)
        }
    }

    // ========================================================================
    // Authentication
    // ========================================================================

    async register(data: RegisterData): Promise<{ user: User; token: string }> {
        await this.getCsrfCookie()

        return this.request<{ user: User; token: string }>('/auth/register', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async login(email: string, password: string): Promise<AdminApiResponse<{ user: User; token: string }>> {
        await this.getCsrfCookie()

        return this.request<AdminApiResponse<{ user: User; token: string }>>('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        })
    }

    async logout(): Promise<AdminApiResponse<null>> {
        return this.request<AdminApiResponse<null>>('/auth/logout', {
            method: 'POST',
        })
    }

    async getUser(): Promise<AdminApiResponse<User>> {
        return this.request<AdminApiResponse<User>>('/auth/user')
    }

    // ========================================================================
    // Account
    // ========================================================================

    async getDashboard(): Promise<AdminApiResponse<AccountDashboard>> {
        return this.request<AdminApiResponse<AccountDashboard>>('/account/dashboard')
    }
}

export const authApi = new AuthApiService()
export default authApi
