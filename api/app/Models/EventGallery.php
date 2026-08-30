<?php

namespace App\Models;

use App\Support\EventGalleriesCache;
use Illuminate\Database\Eloquent\Builder;

/**
 * Une galerie d'EVENEMENT, et rien d'autre.
 *
 * Ce que cette sous-classe remplace : sept `if ($gallery->type !== 'event') { 404 }` recopies
 * a la main dans `EventGalleryController`. Recopier une garde, c'est se donner rendez-vous
 * avec celle qu'on oubliera — et c'etait deja arrive : `GalleryController` n'en avait AUCUNE,
 * si bien que `DELETE /api/admin/galleries/{id_d_un_evenement}` supprimait la galerie sans
 * invalider le listing public et laissait les fichiers MinIO des sous-galeries orphelins.
 *
 * Avec un type de parametre `EventGallery` sur la route, le route-model-binding cherche dans
 * le perimetre du scope : un identifiant de mauvaise nature rend 404 **sans une ligne dans le
 * controleur**. La garde n'est plus une intention, c'est une propriete.
 *
 * La table reste `galleries` : c'est un heritage a table unique, pas une table par nature.
 * Une galerie porte sa nature dans `kind`, pose a la creation.
 */
class EventGallery extends Gallery
{
    protected $table = 'galleries';

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('kind', fn (Builder $query) => $query->where('kind', self::KIND_EVENT));

        static::creating(function (self $gallery) {
            $gallery->kind = self::KIND_EVENT;
            if (empty($gallery->type)) {
                $gallery->type = 'event';
            }
        });

        // Ce qui change la donnee invalide le cache. L'invalidation vivait chez les
        // appelants, et quatre controleurs sur sept y pensaient.
        static::saved(fn () => EventGalleriesCache::invalider());
        static::deleted(fn () => EventGalleriesCache::invalider());
    }
}
