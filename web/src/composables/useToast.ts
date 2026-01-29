/**
 * Composable pour la gestion des notifications toast
 * Fournit un système centralisé de notifications utilisateur
 */

import { ref, readonly } from 'vue'

export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
    id: number
    type: ToastType
    title: string
    message?: string
    duration: number
}

const toasts = ref<Toast[]>([])
let toastId = 0

export function useToast() {
    function addToast(type: ToastType, title: string, message?: string, duration = 5000): number {
        const id = ++toastId
        const toast: Toast = { id, type, title, message, duration }
        toasts.value.push(toast)

        if (duration > 0) {
            setTimeout(() => removeToast(id), duration)
        }

        return id
    }

    function removeToast(id: number): void {
        const index = toasts.value.findIndex(t => t.id === id)
        if (index !== -1) {
            toasts.value.splice(index, 1)
        }
    }

    function success(title: string, message?: string, duration?: number): number {
        return addToast('success', title, message, duration)
    }

    function error(title: string, message?: string, duration?: number): number {
        return addToast('error', title, message, duration ?? 7000)
    }

    function warning(title: string, message?: string, duration?: number): number {
        return addToast('warning', title, message, duration)
    }

    function info(title: string, message?: string, duration?: number): number {
        return addToast('info', title, message, duration)
    }

    function clearAll(): void {
        toasts.value = []
    }

    return {
        toasts: readonly(toasts),
        addToast,
        removeToast,
        success,
        error,
        warning,
        info,
        clearAll,
    }
}
