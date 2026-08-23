import {describe, it, expect, vi} from 'vitest'
import {ref} from 'vue'
import {useTableSort} from './useTableSort'

describe('useTableSort', () => {
    it('mémorise la colonne et le sens demandés', () => {
        const page = ref(1)
        const reload = vi.fn()
        const {sort, applySort} = useTableSort({by: 'created_at', dir: 'desc'}, page, reload)

        applySort('total', 'asc')

        expect(sort.value).toEqual({by: 'total', dir: 'asc'})
        expect(reload).toHaveBeenCalledOnce()
    })

    // Rester en page 3 sur un autre ordre afficherait une tranche arbitraire.
    it('ramène en première page et laisse le watch de page recharger', () => {
        const page = ref(3)
        const reload = vi.fn()
        const {applySort} = useTableSort({by: 'created_at', dir: 'desc'}, page, reload)

        applySort('total', 'asc')

        expect(page.value).toBe(1)
        expect(reload).not.toHaveBeenCalled()
    })

    it('ne partage pas l’objet de défauts entre instances', () => {
        const defauts = {by: 'created_at', dir: 'desc' as const}
        const a = useTableSort(defauts, ref(1), () => {})
        const b = useTableSort(defauts, ref(1), () => {})

        a.applySort('code', 'asc')

        expect(b.sort.value).toEqual({by: 'created_at', dir: 'desc'})
        expect(defauts).toEqual({by: 'created_at', dir: 'desc'})
    })
})
