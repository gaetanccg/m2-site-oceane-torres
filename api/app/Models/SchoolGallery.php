<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Une galerie de SESSION SCOLAIRE — une par eleve.
 *
 * Attention en la creant : la nature scolaire l'emporte sur le type, et une galerie scolaire
 * porte `type = 'private'`. C'est la regle que `Gallery::natureDeduite()` applique et qu'un
 * test verrouille. Voir `EventGallery` pour ce que le scope apporte.
 */
class SchoolGallery extends Gallery
{
    protected $table = 'galleries';

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('kind', fn (Builder $query) => $query->where('kind', self::KIND_SCHOOL));

        static::creating(function (self $gallery) {
            $gallery->kind = self::KIND_SCHOOL;
        });
    }
}
