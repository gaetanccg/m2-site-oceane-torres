import { describe, expect, it } from 'vitest'
import { ERROR_MESSAGES, formatValidationErrors, getErrorMessage, parseApiError } from './errorHandler'

describe('parseApiError', () => {
    it('reconnaît une panne réseau', () => {
        const e = parseApiError(new TypeError('Failed to fetch'))
        expect(e.isNetworkError).toBe(true)
        expect(e.status).toBe(0)
    })

    it('reconnaît un timeout', () => {
        const err = new Error('Request timeout')
        err.name = 'AbortError'
        const e = parseApiError(err)
        expect(e.isNetworkError).toBe(true)
        expect(e.status).toBe(408)
    })

    it('marque une 422 comme erreur de validation', () => {
        const e = parseApiError(new Error('x'), new Response(null, { status: 422 }))
        expect(e.isValidationError).toBe(true)
        expect(e.isAuthError).toBe(false)
    })

    it('marque une 401 comme erreur d authentification', () => {
        const e = parseApiError(new Error('x'), new Response(null, { status: 401 }))
        expect(e.isAuthError).toBe(true)
    })

    it('marque une 500 comme erreur serveur', () => {
        const e = parseApiError(new Error('x'), new Response(null, { status: 500 }))
        expect(e.isServerError).toBe(true)
    })

    it('ne confond pas 429 et erreur serveur', () => {
        const e = parseApiError(new Error('x'), new Response(null, { status: 429 }))
        expect(e.isServerError).toBe(false)
        expect(e.status).toBe(429)
    })
})

describe('formatValidationErrors', () => {
    it('ne garde que le premier message par champ', () => {
        const out = formatValidationErrors({ email: ['Premier', 'Second'] })
        expect(out.email).toBe('Premier')
    })

    it('traite plusieurs champs', () => {
        const out = formatValidationErrors({ email: ['A'], password: ['B'] })
        expect(Object.keys(out)).toEqual(['email', 'password'])
    })

    it('renvoie un objet vide pour une entrée vide', () => {
        expect(formatValidationErrors({})).toEqual({})
    })
})

describe('getErrorMessage', () => {
    it('privilégie le message du serveur', () => {
        const msg = getErrorMessage({
            status: 400, message: 'Message du serveur',
            isNetworkError: false, isValidationError: false, isAuthError: false, isServerError: false,
        })
        expect(msg).toBe('Message du serveur')
    })

    it('retombe sur le message contextuel pour une erreur d authentification', () => {
        const msg = getErrorMessage({
            status: 401, message: ERROR_MESSAGES.generic.unknownError,
            isNetworkError: false, isValidationError: false, isAuthError: true, isServerError: false,
        }, 'auth')
        expect(msg).toBe(ERROR_MESSAGES.auth.unauthorized)
    })
})
