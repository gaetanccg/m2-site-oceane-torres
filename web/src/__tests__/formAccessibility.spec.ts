import {describe, it, expect} from 'vitest'
import {readFileSync, readdirSync, statSync} from 'node:fs'
import {join} from 'node:path'

/**
 * Deux invariants sur les formulaires publics, verifies sur le source des vues.
 *
 * Ils n'y etaient pas : les libelles n'etaient rattaches a aucun champ, et les navigateurs
 * ne pouvaient donc rien pre-remplir. C'est de la friction directe dans le tunnel d'achat,
 * et un formulaire inutilisable au lecteur d'ecran.
 */
const RACINE = join(import.meta.dirname ?? __dirname, '..')

function fichiersVue(dossier: string): string[] {
    return readdirSync(dossier).flatMap((entree) => {
        const chemin = join(dossier, entree)
        if (statSync(chemin).isDirectory()) return fichiersVue(chemin)

        return chemin.endsWith('.vue') ? [chemin] : []
    })
}

function template(chemin: string): string {
    return readFileSync(chemin, 'utf-8').split('<script')[0]
}

const VUES = fichiersVue(join(RACINE, 'components')).concat(fichiersVue(join(RACINE, 'views')))

describe('accessibilité des formulaires', () => {
    // `v-for="x in y"` contient la sous-chaine `for="` : l'exclure explicitement.
    const LIBELLE = /(?<![-\w])for="([^"{}]+)"/g
    const IDENTIFIANT = /(?<![-\w])id="([^"{}]+)"/g

    it('aucun libellé ne pointe vers un identifiant inexistant', () => {
        const fautifs: string[] = []

        for (const chemin of VUES) {
            const source = template(chemin)
            const libelles = [...source.matchAll(LIBELLE)].map((m) => m[1])
            const identifiants = new Set([...source.matchAll(IDENTIFIANT)].map((m) => m[1]))
            const orphelins = libelles.filter((l) => !identifiants.has(l))

            if (orphelins.length > 0) {
                fautifs.push(`${chemin.replace(RACINE, '')} → ${orphelins.join(', ')}`)
            }
        }

        expect(fautifs).toEqual([])
    })

    it('tout champ de saisie public est rattaché à un libellé', () => {
        const CHAMP = /<(?:input|select|textarea)\b[^>]*>/gs
        const EXCLUS = ['type="hidden"', 'type="submit"', 'type="button"']
        const fautifs: string[] = []

        for (const chemin of VUES) {
            // Les ecrans d'administration ne sont pas dans le perimetre de #66/#67.
            if (chemin.includes('/admin/')) continue

            for (const champ of template(chemin).match(CHAMP) ?? []) {
                if (EXCLUS.some((e) => champ.includes(e))) continue
                const rattache = ['id="', 'aria-label', 'aria-labelledby'].some((a) => champ.includes(a))

                if (!rattache) {
                    fautifs.push(`${chemin.replace(RACINE, '')} → ${champ.replace(/\s+/g, ' ').slice(0, 80)}`)
                }
            }
        }

        expect(fautifs).toEqual([])
    })

    it('les champs d’identité et d’adresse portent un autocomplete', () => {
        const ATTENDUS: Record<string, string[]> = {
            'components/auth/AuthModal.vue': ['username', 'current-password', 'new-password', 'given-name', 'family-name', 'email', 'tel'],
            'views/Checkout.vue': ['given-name', 'family-name', 'email'],
            'views/Contact.vue': ['name', 'email', 'tel'],
            'components/BookingRequestModal.vue': ['name', 'email', 'tel'],
            'components/cart/ShippingAddressFields.vue': ['tel-national', 'address-line1', 'address-line2', 'postal-code', 'address-level2', 'country-name'],
        }

        for (const [fichier, valeurs] of Object.entries(ATTENDUS)) {
            const source = template(join(RACINE, fichier))

            for (const valeur of valeurs) {
                expect(source, `${fichier} — autocomplete="${valeur}"`).toContain(`autocomplete="${valeur}"`)
            }
        }
    })

    /**
     * Le formulaire promettait 8 caracteres la ou le serveur en exige 12
     * (Password::defaults() => min(12)) : l'inscription echouait apres envoi.
     */
    it('la contrainte de mot de passe affichée est celle du serveur', () => {
        const source = template(join(RACINE, 'components/auth/AuthModal.vue'))

        expect(source).toContain('minlength="12"')
        expect(source).toContain('Minimum 12 caractères')
    })
})
