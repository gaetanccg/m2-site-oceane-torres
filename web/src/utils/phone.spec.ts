import {describe, it, expect} from 'vitest'
import {normalizePhone, isValidFrenchPhone} from './phone'

/**
 * Le champ demande `autocomplete="tel-national"`. Ce que le navigateur rend doit être accepté,
 * sinon l'autoremplissage aboutit à un formulaire refusé.
 */
describe('normalizePhone', () => {
    it('accepte la forme que le placeholder du site affiche lui-même', () => {
        expect(normalizePhone('06 12 34 56 78')).toBe('0612345678')
    })

    it('accepte les séparateurs usuels des carnets d’adresses', () => {
        expect(normalizePhone('06.12.34.56.78')).toBe('0612345678')
        expect(normalizePhone('06-12-34-56-78')).toBe('0612345678')
        expect(normalizePhone('06 12 34 56 78')).toBe('0612345678') // espaces insécables
    })

    it('ramène la forme internationale à la forme nationale', () => {
        expect(normalizePhone('+33612345678')).toBe('0612345678')
        expect(normalizePhone('+33 6 12 34 56 78')).toBe('0612345678')
        expect(normalizePhone('0033612345678')).toBe('0612345678')
    })

    it('laisse intact un numéro déjà canonique', () => {
        expect(normalizePhone('0612345678')).toBe('0612345678')
    })

    it('rend une chaîne vide pour une absence de valeur', () => {
        expect(normalizePhone(null)).toBe('')
        expect(normalizePhone(undefined)).toBe('')
        expect(normalizePhone('   ')).toBe('')
    })
})

describe('isValidFrenchPhone', () => {
    it('accepte ce que l’autoremplissage rend', () => {
        expect(isValidFrenchPhone('06 12 34 56 78')).toBe(true)
        expect(isValidFrenchPhone('+33 6 12 34 56 78')).toBe(true)
    })

    it('refuse ce qui n’est pas un numéro français', () => {
        expect(isValidFrenchPhone('0012345678')).toBe(false)   // second chiffre à 0
        expect(isValidFrenchPhone('061234567')).toBe(false)    // neuf chiffres
        expect(isValidFrenchPhone('06123456789')).toBe(false)  // onze chiffres
        expect(isValidFrenchPhone('06 12 34 56 7A')).toBe(false)
        expect(isValidFrenchPhone('')).toBe(false)
    })
})
