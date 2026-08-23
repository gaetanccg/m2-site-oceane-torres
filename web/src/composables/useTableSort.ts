import {ref} from 'vue'
import type {Ref} from 'vue'
import type {SortDirection, SortState} from '@/types/admin'

/**
 * Etat de tri d'une liste administrable.
 *
 * Le tri part a l'API : trier la seule page recue donnerait un ordre faux des qu'il y a
 * plus d'une page. Changer de tri ramene donc en premiere page — rester en page 3 sur un
 * autre ordre afficherait une tranche arbitraire.
 */
export function useTableSort(defaults: SortState, currentPage: Ref<number>, reload: () => void) {
    const sort = ref<SortState>({...defaults})

    function applySort(by: string, dir: SortDirection) {
        sort.value = {by, dir}

        if (currentPage.value !== 1) {
            currentPage.value = 1

            return
        }

        reload()
    }

    return {sort, applySort}
}
