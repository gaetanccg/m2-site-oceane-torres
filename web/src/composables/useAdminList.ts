import {ref, watch} from 'vue'
import type {Ref} from 'vue'
import {useDebouncedSearch} from './useDebouncedSearch'
import {useTableSort} from './useTableSort'
import {useToast} from './useToast'
import type {SortDirection, SortState} from '@/types/admin'

/** Forme normalisee attendue d'un chargement de liste, quelle que soit celle de l'API. */
export interface AdminListPage<T> {
    items: T[]
    lastPage: number
    total: number
    from: number
    to: number
}

interface Options<T> {
    /** Charge une page. Reçoit l'etat courant, rend la forme normalisee. */
    fetcher: (etat: {page: number; search: string; sort: SortState}) => Promise<AdminListPage<T>>
    /** Message d'erreur affiche si le chargement echoue. */
    erreur: string
    sort?: SortState
    perPage?: number
    searchDelay?: number
}

/**
 * Etat commun aux listes paginees de l'administration.
 *
 * Six vues portaient les memes huit refs, le meme chargement et les memes observateurs.
 * Ce n'est pas seulement de la redondance : les deux regles qui comptent — revenir en
 * premiere page quand le filtre change, et liberer le minuteur de recherche au demontage —
 * etaient recopiees, donc fausses par endroits. Ici elles n'existent qu'une fois.
 *
 * Les noms rendus sont ceux auxquels les gabarits se lient deja.
 */
export function useAdminList<T>(options: Options<T>) {
    const toast = useToast()

    const items = ref<T[]>([]) as Ref<T[]>
    /**
     * Vrai pour le PREMIER chargement seulement. Les gabarits s'en servent pour afficher un
     * sablier a la place du tableau : le remettre a vrai a chaque rechargement ferait
     * disparaitre les lignes a chaque frappe de recherche.
     */
    const isLoading = ref(true)
    /** Vrai pendant n'importe quel chargement, pour un indicateur discret. */
    const isRefreshing = ref(false)
    const searchQuery = ref('')
    const currentPage = ref(1)
    const totalPages = ref(1)
    const total = ref(0)
    const from = ref(0)
    const to = ref(0)

    async function reload() {
        isRefreshing.value = true
        try {
            const page = await options.fetcher({
                page: currentPage.value,
                search: searchQuery.value,
                sort: sort.value,
            })
            items.value = page.items
            totalPages.value = page.lastPage
            total.value = page.total
            from.value = page.from
            to.value = page.to
        } catch {
            toast.error('Erreur', options.erreur)
        } finally {
            isRefreshing.value = false
            isLoading.value = false
        }
    }

    const {sort, applySort} = useTableSort(
        options.sort ?? {by: 'created_at', dir: 'desc' as SortDirection},
        currentPage,
        reload,
    )

    /** Tout changement de critere ramene en premiere page : y rester afficherait un vide. */
    function reloadFromFirstPage() {
        if (currentPage.value !== 1) {
            currentPage.value = 1

            return
        }

        reload()
    }

    watch(currentPage, reload)
    useDebouncedSearch(searchQuery, reloadFromFirstPage, options.searchDelay)

    return {
        items,
        isLoading,
        isRefreshing,
        searchQuery,
        currentPage,
        totalPages,
        total,
        from,
        to,
        sort,
        applySort,
        reload,
        reloadFromFirstPage,
    }
}
