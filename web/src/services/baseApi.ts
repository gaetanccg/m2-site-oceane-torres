/**
 * Base API service - shared request logic for all API services
 */

import { API_CONFIG } from '@/config/constants'
import { extractErrorFromResponse, parseApiError, type ApiError } from '@/utils/errorHandler'

export class ApiError extends Error {
    public apiError: ApiError

    constructor(apiError: ApiError) {
        super(apiError.message)
        this.name = 'ApiError'
        this.apiError = apiError
    }
}

export interface RequestConfig {
    /** Extra headers to merge (e.g. Authorization, X-Cart-Session) */
    headers?: Record<string, string>
    /** Send cookies (credentials: 'include') */
    withCredentials?: boolean
    /** Called on 401 responses before throwing */
    onUnauthorized?: () => void
}

export class BaseApiService {
    protected baseUrl: string
    protected apiOrigin: string
    protected timeout: number

    constructor() {
        this.baseUrl = API_CONFIG.baseUrl
        const url = new URL(this.baseUrl)
        this.apiOrigin = url.origin
        this.timeout = API_CONFIG.timeout
    }

    protected async request<T>(
        endpoint: string,
        options: RequestInit = {},
        config: RequestConfig = {}
    ): Promise<T> {
        const controller = new AbortController()
        const timeoutId = setTimeout(() => controller.abort(), this.timeout)

        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...config.headers,
            ...(options.headers as Record<string, string>),
        }

        try {
            const response = await fetch(`${this.baseUrl}${endpoint}`, {
                ...options,
                signal: controller.signal,
                credentials: config.withCredentials ? 'include' : undefined,
                headers,
            })

            clearTimeout(timeoutId)

            if (response.status === 401 && config.onUnauthorized) {
                config.onUnauthorized()
            }

            if (!response.ok) {
                const apiError = await extractErrorFromResponse(response)
                throw new ApiError(apiError)
            }

            return await response.json()
        } catch (error) {
            clearTimeout(timeoutId)

            if (error instanceof ApiError) {
                throw error
            }

            const apiError = parseApiError(error)
            throw new ApiError(apiError)
        }
    }

    protected getToken(): string | null {
        return localStorage.getItem('auth_token')
    }

    protected getXsrfToken(): string | null {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
        return match ? decodeURIComponent(match[1]) : null
    }

    protected async getCsrfCookie(): Promise<void> {
        await fetch(`${this.apiOrigin}/sanctum/csrf-cookie`, {
            method: 'GET',
            credentials: 'include',
        })
    }

    /** Build auth headers (Bearer + XSRF) for authenticated requests */
    protected authHeaders(): Record<string, string> {
        const headers: Record<string, string> = {}
        const token = this.getToken()
        if (token) headers['Authorization'] = `Bearer ${token}`
        const xsrf = this.getXsrfToken()
        if (xsrf) headers['X-XSRF-TOKEN'] = xsrf
        return headers
    }
}
