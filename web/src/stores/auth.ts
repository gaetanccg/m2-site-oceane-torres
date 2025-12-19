/**
 * Store d'authentification Pinia
 * Gère l'état de connexion et les tokens
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/types/admin'
import { adminApi } from '@/services/adminApi'

export const useAuthStore = defineStore('auth', () => {
    // State
    const user = ref<User | null>(null)
    const token = ref<string | null>(localStorage.getItem('auth_token'))
    const isLoading = ref(false)
    const error = ref<string | null>(null)

    // Getters
    const isAuthenticated = computed(() => !!token.value && !!user.value)
    const isAdmin = computed(() => user.value?.role === 'admin')

    // Actions
    async function login(email: string, password: string): Promise<boolean> {
        isLoading.value = true
        error.value = null

        try {
            const response = await adminApi.login(email, password)

            if (response.success && response.data) {
                token.value = response.data.token
                user.value = response.data.user
                localStorage.setItem('auth_token', response.data.token)
                return true
            }

            error.value = response.message || 'Erreur de connexion'
            return false
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Erreur de connexion'
            return false
        } finally {
            isLoading.value = false
        }
    }

    async function logout(): Promise<void> {
        try {
            await adminApi.logout()
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
            const response = await adminApi.getUser()
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
        if (!token.value) return false

        await fetchUser()
        return isAuthenticated.value
    }

    return {
        // State
        user,
        token,
        isLoading,
        error,
        // Getters
        isAuthenticated,
        isAdmin,
        // Actions
        login,
        logout,
        fetchUser,
        checkAuth,
    }
})
