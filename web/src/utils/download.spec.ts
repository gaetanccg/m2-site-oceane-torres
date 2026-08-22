import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { downloadFile, saveBlob, isInAppBrowser } from './download'

const S3_ERROR = '<?xml version="1.0"?><Error><Code>AccessDenied</Code></Error>'

describe('downloadFile', () => {
    let clicked: HTMLAnchorElement[]

    beforeEach(() => {
        clicked = []
        vi.stubGlobal('URL', {
            ...URL,
            createObjectURL: vi.fn(() => 'blob:stub'),
            revokeObjectURL: vi.fn(),
        })
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
            clicked.push(this)
        })
    })

    afterEach(() => {
        vi.unstubAllGlobals()
        vi.restoreAllMocks()
    })

    it('enregistre le fichier sous le nom demandé quand la réponse est bonne', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => new Response(new Blob(['jpeg'], { type: 'image/jpeg' }), { status: 200 })))

        await downloadFile('https://minio.example/photo.jpg?sig=abc', 'portrait.jpg')

        expect(clicked).toHaveLength(1)
        expect(clicked[0].download).toBe('portrait.jpg')
    })

    it("n'enregistre rien quand MinIO refuse le lien signé", async () => {
        vi.stubGlobal('fetch', vi.fn(async () => new Response(S3_ERROR, { status: 403 })))

        await expect(downloadFile('https://minio.example/photo.jpg', 'portrait.jpg')).rejects.toThrow('403')
        expect(clicked).toHaveLength(0)
    })

    it("n'enregistre pas un corps XML servi en 200", async () => {
        vi.stubGlobal('fetch', vi.fn(async () => new Response(S3_ERROR, {
            status: 200,
            headers: { 'Content-Type': 'application/xml' },
        })))

        await expect(downloadFile('https://minio.example/photo.jpg', 'portrait.jpg')).rejects.toThrow(/expiré ou invalide/)
        expect(clicked).toHaveLength(0)
    })

    it('propage une coupure réseau au lieu de créer un fichier vide', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => { throw new TypeError('Failed to fetch') }))

        await expect(downloadFile('https://minio.example/photo.jpg', 'portrait.jpg')).rejects.toThrow()
        expect(clicked).toHaveLength(0)
    })

    it('saveBlob retire le lien du document après le clic', async () => {
        saveBlob(new Blob(['x']), 'fichier.zip')

        expect(clicked).toHaveLength(1)
        expect(document.querySelectorAll('a')).toHaveLength(0)
    })
})

describe('isInAppBrowser', () => {
    const ua = (value: string) => vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(value)

    afterEach(() => vi.restoreAllMocks())

    it.each([
        ['Mozilla/5.0 Instagram 300.0', true],
        ['Mozilla/5.0 [FBAN/FBIOS;FBAV/400]', true],
        ['Mozilla/5.0 Safari/605.1.15', false],
    ])('%s → %s', (value, expected) => {
        ua(value)
        expect(isInAppBrowser()).toBe(expected)
    })
})
