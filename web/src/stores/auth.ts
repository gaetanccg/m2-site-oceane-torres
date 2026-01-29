/**
 * Store d'authentification Pinia
 * Gere l'etat de connexion et les tokens pour admin et clients
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/types/admin'
import type { RegisterData } from '@/types/account'
import { adminApi, AdminApiError } from '@/services/adminApi'
import { authApi, AuthApiError } from '@/services/authApi'
import { ERROR_MESSAGES, type ApiError } from '@/utils/errorHandler'

export const useAuthStore = defineStore('auth', () => {
    // State
    const user = ref<User | null>(null)
    const token = ref<string | null>(localStorage.getItem('auth_token'))
    const isLoading = ref(false)
    const isInitialized = ref(false)
    const error = ref<string | null>(null)
    const fieldErrors = ref<Record<string, string>>({})

    /**
     * Extrait le message d'erreur approprié
     */
    function extractErrorMessage(e: unknown, defaultMessage: string): string {
        if (e instanceof AdminApiError || e instanceof AuthApiError) {
            return e.apiError.message
        }
        if (e instanceof Error) {
            return e.message
        }
        return defaultMessage
    }

    /**
     * Extrait les erreurs par champ pour les formulaires
     */
    function extractFieldErrors(e: unknown): Record<string, string> {
        if (e instanceof AdminApiError || e instanceof AuthApiError) {
            const apiError = e.apiError
            if (apiError.errors) {
                const result: Record<string, string> = {}
                for (const [field, messages] of Object.entries(apiError.errors)) {
                    result[field] = messages[0]
                }
                return result
            }
        }
        return {}
    }

    // Getters
    const isAuthenticated = computed(() => !!token.value && !!user.value)
    const isAdmin = computed(() => user.value?.role === 'admin')
    const isClient = computed(() => user.value?.role === 'client')
    const userFullName = computed(() => {
        if (!user.value) return ''
        return `${user.value.first_name} ${user.value.last_name}`
    })
    const userInitials = computed(() => {
        if (!user.value) return ''
        const first = user.value.first_name?.[0] || ''
        const last = user.value.last_name?.[0] || ''
        return `${first}${last}`.toUpperCase()
    })

    // Actions

    /**
     * Login pour admin (utilise adminApi pour redirection vers /admin/login)
     */
    async function login(email: string, password: string): Promise<boolean> {
        isLoading.value = true
        error.value = null
        fieldErrors.value = {}

        try {
            const response = await adminApi.login(email, password)

            if (response.success && response.data) {
                token.value = response.data.token
                user.value = response.data.user
                localStorage.setItem('auth_token', response.data.token)
                return true
            }

            error.value = response.message || ERROR_MESSAGES.auth.invalidCredentials
            return false
        } catch (e) {
            error.value = extractErrorMessage(e, ERROR_MESSAGES.auth.invalidCredentials)
            fieldErrors.value = extractFieldErrors(e)
            return false
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Login pour client (utilise authApi)
     */
    async function loginClient(email: string, password: string): Promise<boolean> {
        isLoading.value = true
        error.value = null
        fieldErrors.value = {}

        try {
            const response = await authApi.login(email, password)

            if (response.success && response.data) {
                token.value = response.data.token
                user.value = response.data.user
                localStorage.setItem('auth_token', response.data.token)
                return true
            }

            error.value = response.message || ERROR_MESSAGES.auth.invalidCredentials
            return false
        } catch (e) {
            error.value = extractErrorMessage(e, ERROR_MESSAGES.auth.invalidCredentials)
            fieldErrors.value = extractFieldErrors(e)
            return false
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Inscription d'un nouveau client
     */
    async function register(data: RegisterData): Promise<boolean> {
        isLoading.value = true
        error.value = null
        fieldErrors.value = {}

        try {
            const response = await authApi.register(data)

            if (response.user && response.token) {
                token.value = response.token
                user.value = response.user
                localStorage.setItem('auth_token', response.token)
                return true
            }

            error.value = 'Une erreur est survenue lors de l\'inscription.'
            return false
        } catch (e) {
            error.value = extractErrorMessage(e, 'Une erreur est survenue lors de l\'inscription.')
            fieldErrors.value = extractFieldErrors(e)
            return false
        } finally {
            isLoading.value = false
        }
    }

    async function logout(): Promise<void> {
        try {
            await authApi.logout()
        } catch {
            // Ignorer les erreurs de logout
        } finally {
            token.value = null
            user.value = null
            localStorage.removeItem('auth_token')
        }
    }

    async function fetchUser(): Promise<void> {
        if (!token.value) return

        isLoading.value = true
        try {
            const response = await authApi.getUser()
            if (response.success && response.data) {
                user.value = response.data
            } else {
                // Token invalide
                await logout()
            }
        } catch {
            await logout()
        } finally {
            isLoading.value = false
        }
    }

    async function checkAuth(): Promise<boolean> {
        if (!token.value) {
            isInitialized.value = true
            return false
        }

        // Si déjà initialisé et user présent, pas besoin de refaire l'appel API
        if (isInitialized.value && user.value) {
            return isAuthenticated.value
        }

        await fetchUser()
        isInitialized.value = true
        return isAuthenticated.value
    }

    /**
     * Initialise l'auth au démarrage de l'app
     * À appeler une seule fois dans main.ts
     */
    async function initialize(): Promise<void> {
        if (isInitialized.value) return

        if (token.value) {
            await fetchUser()
        }
        isInitialized.value = true
    }

    function clearError(): void {
        error.value = null
        fieldErrors.value = {}
    }

    return {
        // State
        user,
        token,
        isLoading,
        isInitialized,
        error,
        fieldErrors,
        // Getters
        isAuthenticated,
        isAdmin,
        isClient,
        userFullName,
        userInitials,
        // Actions
        login,
        loginClient,
        register,
        logout,
        fetchUser,
        checkAuth,
        initialize,
        clearError,
    }
})
