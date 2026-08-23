import {describe, it, expect, vi, beforeEach, afterEach} from 'vitest'
import {defineComponent, h, nextTick} from 'vue'
import {mount} from '@vue/test-utils'
import {useAdminList} from './useAdminList'
import type {AdminListPage} from './useAdminList'

const erreurs: string[] = []
vi.mock('./useToast', () => ({
    useToast: () => ({
        error: (_titre: string, message: string) => erreurs.push(message),
        success: () => {},
    }),
}))

interface Ligne {
    id: string
}

type Etat = {page: number; search: string; sort: {by: string; dir: 'asc' | 'desc'}}

function monter(fetcher: (e: Etat) => Promise<AdminListPage<Ligne>>, options = {}) {
    let liste!: ReturnType<typeof useAdminList<Ligne>>
    const wrapper = mount(defineComponent({
        setup() {
            liste = useAdminList<Ligne>({fetcher, erreur: 'Chargement impossible', ...options})

            return () => h('div')
        },
    }))

    return {liste, wrapper}
}

function page(items: Ligne[], reste: Partial<AdminListPage<Ligne>> = {}): AdminListPage<Ligne> {
    return {items, lastPage: 1, total: items.length, from: 1, to: items.length, ...reste}
}

describe('useAdminList', () => {
    beforeEach(() => {
        erreurs.length = 0
        vi.useFakeTimers()
    })
    afterEach(() => vi.useRealTimers())

    it('reporte la page reçue dans l’état de pagination', async () => {
        const {liste} = monter(async () => page([{id: 'a'}], {lastPage: 4, total: 70, from: 21, to: 40}))

        await liste.reload()

        expect(liste.items.value).toEqual([{id: 'a'}])
        expect(liste.totalPages.value).toBe(4)
        expect(liste.total.value).toBe(70)
        expect(liste.from.value).toBe(21)
        expect(liste.to.value).toBe(40)
    })

    it('transmet page, recherche et tri au chargement', async () => {
        const vus: Etat[] = []
        const {liste} = monter(async (etat) => {
            vus.push({...etat})

            return page([])
        })

        liste.currentPage.value = 3
        await nextTick()
        await vi.runAllTimersAsync()

        expect(vus[vus.length - 1]).toEqual({page: 3, search: '', sort: {by: 'created_at', dir: 'desc'}})
    })

    // Le sablier ne doit remplacer le tableau qu'au premier chargement : sinon les lignes
    // disparaissent a chaque frappe de recherche.
    it('ne signale « en chargement » qu’au premier appel', async () => {
        const {liste} = monter(async () => page([{id: 'a'}]))

        expect(liste.isLoading.value).toBe(true)
        await liste.reload()
        expect(liste.isLoading.value).toBe(false)

        const enCours = liste.reload()
        expect(liste.isLoading.value).toBe(false)
        expect(liste.isRefreshing.value).toBe(true)
        await enCours
        expect(liste.isRefreshing.value).toBe(false)
    })

    it('signale l’erreur sans laisser l’écran en chargement', async () => {
        const {liste} = monter(async () => {
            throw new Error('réseau')
        })

        await liste.reload()

        expect(erreurs).toEqual(['Chargement impossible'])
        expect(liste.isLoading.value).toBe(false)
        expect(liste.isRefreshing.value).toBe(false)
    })

    it('un changement de tri ramène en première page', async () => {
        let appels = 0
        const {liste} = monter(async () => {
            appels++

            return page([])
        })

        liste.currentPage.value = 4
        await nextTick()
        await vi.runAllTimersAsync()
        const avant = appels

        liste.applySort('total', 'asc')
        await nextTick()
        await vi.runAllTimersAsync()

        expect(liste.currentPage.value).toBe(1)
        // Un seul rechargement, pas deux : le retour en page 1 suffit a le declencher.
        expect(appels).toBe(avant + 1)
    })

    it('une recherche est différée et ramène en première page', async () => {
        const vus: Etat[] = []
        const {liste} = monter(async (etat) => {
            vus.push({...etat})

            return page([])
        })

        liste.currentPage.value = 2
        await nextTick()
        await vi.runAllTimersAsync()

        liste.searchQuery.value = 'du'
        liste.searchQuery.value = 'dupont'
        await vi.advanceTimersByTimeAsync(200)
        expect(vus.filter((v) => v.search === 'dupont')).toHaveLength(0)

        await vi.runAllTimersAsync()
        expect(liste.currentPage.value).toBe(1)
        expect(vus[vus.length - 1]).toMatchObject({page: 1, search: 'dupont'})
    })

    it('libère le minuteur de recherche au démontage', async () => {
        let appels = 0
        const {liste, wrapper} = monter(async () => {
            appels++

            return page([])
        })

        liste.searchQuery.value = 'x'
        wrapper.unmount()
        await vi.runAllTimersAsync()

        expect(appels).toBe(0)
    })
})
