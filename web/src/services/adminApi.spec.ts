import { describe, it, expect } from 'vitest'
import { adminApi } from './adminApi'
import { api } from './api'
import { cartApi } from './cartApi'

/**
 * La facade admin est construite par `.bind()` sur des methodes de sous-services.
 * Retirer une methode d'un sous-service sans toucher la facade ne fait echouer ni
 * vue-tsc ni ESLint : `undefined.bind()` explose au chargement du module et emporte
 * toute la partie admin.
 */
describe('façade adminApi', () => {
    const entries = Object.entries(adminApi)

    it('expose des méthodes, pas des undefined', () => {
        const broken = entries.filter(([, value]) => typeof value !== 'function')
        expect(broken.map(([name]) => name)).toEqual([])
    })

    it('couvre les domaines admin', () => {
        expect(entries.length).toBeGreaterThan(50)
    })
})

describe('couche d\'accès publique', () => {
    it.each([
        'getGalleryByShareCode',
        'getDownloadableGallery',
        'getPhotoDownloadUrl',
        'likePhoto',
        'getEvents',
        'getEvent',
    ])('api.%s existe', (name) => {
        expect(typeof (api as unknown as Record<string, unknown>)[name]).toBe('function')
    })

    it('cartApi.downloadAllPhotos existe', () => {
        expect(typeof cartApi.downloadAllPhotos).toBe('function')
    })
})
