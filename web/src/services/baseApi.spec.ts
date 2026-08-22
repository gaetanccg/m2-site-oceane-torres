import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { BaseApiService, ApiError } from './baseApi'

class Probe extends BaseApiService {
    public call<T>(endpoint: string, options?: RequestInit, config?: Parameters<BaseApiService['request']>[2]) {
        return this.request<T>(endpoint, options, config)
    }

    public callBlob(endpoint: string) {
        return this.requestBlob(endpoint)
    }
}

const ok = (body: unknown = { ok: true }) =>
    vi.fn(async () => new Response(JSON.stringify(body), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
    }))

function sentHeaders(mock: ReturnType<typeof vi.fn>): Record<string, string> {
    return (mock.mock.calls[0][1] as RequestInit).headers as Record<string, string>
}

describe('BaseApiService — en-têtes', () => {
    let probe: Probe

    beforeEach(() => { probe = new Probe() })
    afterEach(() => vi.unstubAllGlobals())

    it('pose Content-Type: application/json sur un corps JSON', async () => {
        const f = ok()
        vi.stubGlobal('fetch', f)

        await probe.call('/x', { method: 'POST', body: JSON.stringify({ a: 1 }) })

        expect(sentHeaders(f)['Content-Type']).toBe('application/json')
    })

    /**
     * Le navigateur doit poser lui-meme `multipart/form-data; boundary=…`. Si on force
     * application/json sur un FormData, le serveur ne peut plus decouper le corps et
     * tout envoi de fichiers echoue.
     */
    it('ne pose aucun Content-Type sur un FormData', async () => {
        const f = ok()
        vi.stubGlobal('fetch', f)
        const body = new FormData()
        body.append('chunk', new Blob(['x']), 'chunk.zip')

        await probe.call('/x', { method: 'PUT', body })

        expect(sentHeaders(f)['Content-Type']).toBeUndefined()
    })
})

describe('BaseApiService — délai', () => {
    afterEach(() => { vi.unstubAllGlobals(); vi.useRealTimers() })

    // Une requete qui ne se termine pas : `request()` annule la minuterie des qu'une
    // reponse arrive, donc seul un appel en cours expose le delai reellement arme.
    const pending = () => {
        const signals: AbortSignal[] = []
        vi.stubGlobal('fetch', vi.fn((_url: string, init: RequestInit) => {
            signals.push(init.signal as AbortSignal)
            return new Promise<Response>(() => {})
        }))
        return signals
    }

    it('respecte un délai surchargé au lieu des 10 s par défaut', () => {
        vi.useFakeTimers()
        const signals = pending()

        void new Probe().call('/x', {}, { timeout: 300000 })

        vi.advanceTimersByTime(11000)
        expect(signals[0].aborted).toBe(false)

        vi.advanceTimersByTime(290000)
        expect(signals[0].aborted).toBe(true)
    })

    it('coupe à 10 s en l\'absence de surcharge', () => {
        vi.useFakeTimers()
        const signals = pending()

        void new Probe().call('/x')

        vi.advanceTimersByTime(9999)
        expect(signals[0].aborted).toBe(false)

        vi.advanceTimersByTime(2)
        expect(signals[0].aborted).toBe(true)
    })
})

describe('BaseApiService — erreurs', () => {
    afterEach(() => vi.unstubAllGlobals())

    it('emballe une réponse en échec dans une ApiError qui porte le statut', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => new Response(
            JSON.stringify({ message: 'Session scolaire clôturée.' }),
            { status: 409, headers: { 'Content-Type': 'application/json' } },
        )))

        await expect(new Probe().call('/x')).rejects.toMatchObject({
            name: 'ApiError',
            apiError: { status: 409, message: 'Session scolaire clôturée.' },
        })
    })

    it('appelle onUnauthorized sur un 401', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => new Response('{}', { status: 401 })))
        const onUnauthorized = vi.fn()

        await expect(new Probe().call('/x', {}, { onUnauthorized })).rejects.toBeInstanceOf(ApiError)
        expect(onUnauthorized).toHaveBeenCalledOnce()
    })

    it('requestBlob refuse une réponse en échec au lieu de rendre le corps', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => new Response('<Error/>', { status: 410 })))

        await expect(new Probe().callBlob('/x')).rejects.toMatchObject({ apiError: { status: 410 } })
    })
})
