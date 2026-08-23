import {describe, it, expect} from 'vitest'
import {mount} from '@vue/test-utils'
import DataTable from './DataTable.vue'
import type {TableColumn} from '@/types/admin'

// `mount` ne sait pas inferer le generique d'un composant generique : on le fixe.
type Ligne = object

const colonnes: TableColumn<Ligne>[] = [
    {key: 'code', label: 'Code', sortable: true},
    {key: 'validity', label: 'Validité', sortable: true, sortKey: 'valid_until'},
    {key: 'status', label: 'Statut'},
]

const donnees: Ligne[] = [{id: '1', code: 'ABC'}]

function monter(props: {sortBy?: string; sortDir?: 'asc' | 'desc'} = {}) {
    return mount(DataTable, {
        props: {columns: colonnes, data: donnees, ...props},
    })
}

function enTetes(wrapper: ReturnType<typeof monter>) {
    return wrapper.findAll('th')
}

describe('DataTable — tri', () => {
    // Le defaut d'origine : handleSort mettait a jour un etat local et n'emettait jamais
    // rien. La fleche tournait, les donnees ne changeaient pas.
    it('émet le tri au clic sur un en-tête triable', async () => {
        const wrapper = monter({sortBy: 'created_at', sortDir: 'desc'})

        await enTetes(wrapper)[0].find('button').trigger('click')

        expect(wrapper.emitted('sort')).toEqual([['code', 'asc']])
    })

    it('utilise sortKey quand la clé d’affichage diffère du nom serveur', async () => {
        const wrapper = monter({sortBy: 'created_at', sortDir: 'desc'})

        await enTetes(wrapper)[1].find('button').trigger('click')

        expect(wrapper.emitted('sort')).toEqual([['valid_until', 'asc']])
    })

    it('inverse le sens sur la colonne déjà triée', async () => {
        const wrapper = monter({sortBy: 'code', sortDir: 'asc'})

        await enTetes(wrapper)[0].find('button').trigger('click')

        expect(wrapper.emitted('sort')).toEqual([['code', 'desc']])
    })

    it('repart en ascendant quand on change de colonne', async () => {
        const wrapper = monter({sortBy: 'code', sortDir: 'desc'})

        await enTetes(wrapper)[1].find('button').trigger('click')

        expect(wrapper.emitted('sort')).toEqual([['valid_until', 'asc']])
    })

    it('ne rend pas cliquable une colonne non triable', () => {
        const wrapper = monter()

        expect(enTetes(wrapper)[2].find('button').exists()).toBe(false)
    })

    it('annonce le sens du tri aux lecteurs d’écran', () => {
        const wrapper = monter({sortBy: 'code', sortDir: 'asc'})
        const [code, validity, status] = enTetes(wrapper)

        expect(code.attributes('aria-sort')).toBe('ascending')
        expect(validity.attributes('aria-sort')).toBe('none')
        expect(status.attributes('aria-sort')).toBeUndefined()
    })

    // La fleche doit refleter l'etat transmis par le parent, seul detenteur du tri
    // reellement applique par l'API.
    it('la flèche suit l’état reçu, pas un état interne', async () => {
        const wrapper = monter({sortBy: 'code', sortDir: 'asc'})
        expect(enTetes(wrapper)[0].find('svg').classes()).not.toContain('rotate-180')

        await wrapper.setProps({sortDir: 'desc'})
        expect(enTetes(wrapper)[0].find('svg').classes()).toContain('rotate-180')
    })
})
