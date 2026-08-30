<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * L'invalidation du cache de la liste publique des evenements, en un seul endroit.
 *
 * Elle vivait dans un trait, donc chez les APPELANTS : chaque controleur devait penser a
 * l'appeler. Trois ne le faisaient pas — `toggleDownloadable`, `bulkToggleDownloadable` et
 * `updateSortOrder` — alors qu'ils changent precisement ce que la liste publique affiche :
 * quelles photos sont telechargeables, et dans quel ordre. Le listing restait perime jusqu'a
 * cinq minutes.
 *
 * Elle est desormais declenchee par les MODELES (`EventGallery::booted`, `Photo::booted`) :
 * ce qui change la donnee invalide le cache, sans que personne ait a y penser.
 */
final class EventGalleriesCache
{
    public const VERSION_KEY = 'event_galleries:version';

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * Une course perdue entre deux invalidations concurrentes est sans consequence : ce qui
     * compte est que la version CHANGE, pas qu'elle s'incremente exactement une fois.
     */
    public static function invalider(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }
}
