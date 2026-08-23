import {onUnmounted, watch} from 'vue'
import type {Ref} from 'vue'

/**
 * Recherche differee, avec liberation du minuteur au demontage.
 *
 * Le minuteur etait auparavant declare a la racine de chaque vue et jamais annule :
 * quitter l'ecran pendant la temporisation laissait partir une requete sur un composant
 * detruit. Le faire porter par le composable evite d'y penser a chaque fois.
 */
export function useDebouncedSearch(source: Ref<string>, callback: () => void, delay = 300) {
    let timer: ReturnType<typeof setTimeout> | null = null

    const cancel = () => {
        if (timer) {
            clearTimeout(timer)
            timer = null
        }
    }

    watch(source, () => {
        cancel()
        timer = setTimeout(() => {
            timer = null
            callback()
        }, delay)
    })

    onUnmounted(cancel)

    return {cancel}
}
