<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Une galerie CLIENTE : celle qu'une cliente ouvre avec le code recu par email.
 *
 * Ni evenement, ni session scolaire. C'etait jusqu'ici la seule nature definie par une double
 * negation — « pas de type event, pas de school_session_id » — que chaque lecteur devait
 * reconstituer. Voir `EventGallery` pour ce que le scope apporte au route-model-binding.
 */
class ClientGallery extends Gallery
{
    protected $table = 'galleries';

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('kind', fn (Builder $query) => $query->where('kind', self::KIND_CLIENT));

        static::creating(function (self $gallery) {
            $gallery->kind = self::KIND_CLIENT;
        });
    }
}
