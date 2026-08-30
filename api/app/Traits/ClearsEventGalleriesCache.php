<?php

namespace App\Traits;

use App\Support\EventGalleriesCache;
use Illuminate\Support\Facades\Cache;

/**
 * Cache de la liste publique des galeries d'evenement, et son invalidation.
 *
 * Trois defauts corriges ici, dont deux que le plan ne voyait pas :
 *
 * 1. La purge bouclait sur les pages 1 a 10. Au-dela, la page restait perimee jusqu'a
 *    l'expiration du TTL.
 * 2. La cle ignorait `per_page`. Une demande en `per_page=100` recevait la reponse mise en
 *    cache pour 12 elements — donc la mauvaise page, pas seulement une page perimee.
 * 3. `page` n'etait pas normalise avant d'entrer dans la cle : `?page=01`, `?page=1.0`,
 *    `?page=abc`, `?page[]=1` creaient autant d'entrees distinctes. De quoi remplir le
 *    magasin de cache depuis l'exterieur.
 *
 * Pourquoi un compteur de version et NON des tags, que le plan proposait : les tags exigent
 * Redis ou Memcached. Ce projet utilise `database` en local, `file` en preproduction et
 * `redis` en production — `tags()` leve BadMethodCallException sur les deux premiers.
 * Verifie, pas suppose. Une incrementation invalide toutes les variantes d'un coup, sur
 * n'importe quel driver.
 */
trait ClearsEventGalleriesCache
{
    private const TTL = 300;

    /** Nombre d'elements par page mis en cache. Toute autre valeur contourne le cache. */
    private const CACHED_PER_PAGE = 12;

    /** Au-dela, on ne met plus en cache : borne le nombre de cles possibles. */
    private const MAX_CACHED_PAGE = 20;

    protected function clearEventGalleriesCache(): void
    {
        EventGalleriesCache::invalider();
    }

    protected function eventGalleriesCacheVersion(): int
    {
        return EventGalleriesCache::version();
    }

    /**
     * Rend la valeur mise en cache, ou la calcule.
     *
     * Hors du perimetre cachable (page trop lointaine, taille de page inhabituelle), la
     * valeur est calculee sans etre stockee : une reponse juste et non mise en cache vaut
     * mieux qu'une cle de plus dans le magasin.
     *
     * @template T
     *
     * @param  callable(): T  $calcul
     * @return T
     */
    protected function rememberEventGalleries(int $page, int $perPage, callable $calcul): mixed
    {
        if ($perPage !== self::CACHED_PER_PAGE || $page > self::MAX_CACHED_PAGE) {
            return $calcul();
        }

        return Cache::remember(
            sprintf('event_galleries:v%d:p%d', $this->eventGalleriesCacheVersion(), $page),
            self::TTL,
            $calcul,
        );
    }
}
