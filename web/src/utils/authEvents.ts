/**
 * Système d'events pour la gestion de l'authentification
 * Permet aux services API de notifier l'app des changements de session
 */

export const AUTH_EVENTS = {
    SESSION_EXPIRED: 'auth:session-expired',
} as const

/**
 * Émet un event quand la session a expiré (401)
 */
export function emitSessionExpired(): void {
    window.dispatchEvent(new CustomEvent(AUTH_EVENTS.SESSION_EXPIRED))
}

/**
 * Écoute l'event de session expirée
 */
export function onSessionExpired(callback: () => void): () => void {
    const handler = () => callback()
    window.addEventListener(AUTH_EVENTS.SESSION_EXPIRED, handler)
    return () => window.removeEventListener(AUTH_EVENTS.SESSION_EXPIRED, handler)
}
