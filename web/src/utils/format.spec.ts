import { describe, expect, it } from 'vitest'
import { formatPrice } from './format'

describe('formatPrice', () => {
    it('formate en euros avec la convention française', () => {
        expect(formatPrice(13)).toMatch(/13,00\s*€/)
    })

    it('formate un montant à décimales', () => {
        expect(formatPrice(6.67)).toMatch(/6,67\s*€/)
    })

    it('formate zéro', () => {
        expect(formatPrice(0)).toMatch(/0,00\s*€/)
    })

    it('sépare les milliers', () => {
        expect(formatPrice(1500)).toMatch(/1\s*500,00\s*€/)
    })

    it('formate un montant négatif, cas des avoirs', () => {
        expect(formatPrice(-25)).toMatch(/-\s*25,00\s*€/)
    })
})
