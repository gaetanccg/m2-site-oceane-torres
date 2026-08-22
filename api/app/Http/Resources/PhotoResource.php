<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Forme publique d'une photo. Allowlist explicite : la serialisation nue dependait de
 * $fillable et de $appends, donc ajouter un accesseur au modele changeait la reponse de
 * tous les endpoints sans que rien ne le signale.
 *
 * Les chemins de stockage MinIO n'y figurent pas. `file_path` non plus : le front ne
 * s'en servait qu'en dernier terme de chaines `thumbnail_url || preview_url ||
 * display_url || file_path`, or resolveImageUrl() ne rend jamais vide pour une image —
 * le terme etait inatteignable, et un chemin de bucket n'est de toute façon pas une URL.
 */
class PhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'is_video' => (bool) $this->is_video,
            'is_liked' => (bool) $this->is_liked,
            'is_downloadable' => (bool) $this->is_downloadable,
            'price' => $this->price === null ? null : (float) $this->price,
            'display_url' => $this->display_url,
            'preview_url' => $this->preview_url,
            'thumbnail_url' => $this->thumbnail_url,
            // Ajoute a la main par l'endpoint de telechargement uniquement : cette URL
            // porte le jeton de la galerie dans sa chaine de repli.
            $this->mergeWhen($this->resource->hasAppended('clean_thumbnail_url'), fn () => [
                'clean_thumbnail_url' => $this->clean_thumbnail_url,
            ]),
        ];
    }
}
