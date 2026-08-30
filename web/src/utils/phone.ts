/**
 * La forme canonique d'un numéro français : dix chiffres, sans séparateur.
 *
 * Le champ de livraison demande `autocomplete="tel-national"`, et la validation refusait ce
 * que le navigateur rendait : espaces et forme internationale étaient rejetés des deux côtés,
 * sans normalisation. L'autoremplissage menait donc à un formulaire refusé — alors que le
 * placeholder de l'inscription affiche lui-même « 06 XX XX XX XX ».
 *
 * Le pendant serveur est `App\Support\NumeroDeTelephone` : les deux doivent accepter la même
 * chose, sinon le front laisse passer ce que l'API refuse.
 */

/** Ce qu'un humain ou un carnet d'adresses glisse entre les chiffres. */
const SEPARATEURS = /[\s  .\-–()/]/g

export function normalizePhone(numero: string | null | undefined): string {
    if (!numero) return ''

    let propre = numero.trim().replace(SEPARATEURS, '')

    for (const prefixe of ['+33', '0033']) {
        if (propre.startsWith(prefixe)) {
            propre = '0' + propre.slice(prefixe.length)
            break
        }
    }

    return propre
}

const FRENCH_PHONE = /^0[1-9]\d{8}$/

export function isValidFrenchPhone(numero: string | null | undefined): boolean {
    return FRENCH_PHONE.test(normalizePhone(numero))
}
