<?php

namespace App\Support;

/**
 * La forme canonique d'un numero francais : dix chiffres, sans separateur.
 *
 * Le tunnel demandait au navigateur `autocomplete="tel-national"` puis refusait ce qu'il
 * rendait : la validation exigeait `^0[1-9]\d{8}$` sans normalisation, des deux cotes. Un
 * numero avec des espaces — la forme que le placeholder de l'inscription affiche lui-meme,
 * « 06 XX XX XX XX » — etait donc rejete. L'autoremplissage menait a un formulaire refuse.
 *
 * Sont acceptes et ramenes a la forme canonique : les espaces (y compris insecables), les
 * points, tirets, points-mediams et parentheses, ainsi que les prefixes internationaux
 * `+33` et `0033` que les carnets d'adresses stockent couramment.
 */
final class NumeroDeTelephone
{
    /** Ce qu'un humain ou un carnet d'adresses glisse entre les chiffres. */
    private const SEPARATEURS = [' ', "\u{00A0}", "\u{202F}", '.', '-', '–', '(', ')', '/'];

    public static function normaliser(?string $numero): ?string
    {
        if ($numero === null) {
            return null;
        }

        $propre = str_replace(self::SEPARATEURS, '', trim($numero));

        if ($propre === '') {
            return '';
        }

        foreach (['+33', '0033'] as $prefixe) {
            if (str_starts_with($propre, $prefixe)) {
                $propre = '0'.substr($propre, strlen($prefixe));
                break;
            }
        }

        return $propre;
    }
}
