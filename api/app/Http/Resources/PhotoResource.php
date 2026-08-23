<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            // Pas de `display_url` : l'accesseur renvoie `preview_url` a l'octet pres, et
            // ces URL signees pesent 426 octets chacune — 30 % du poids de la reponse.
            'preview_url' => $this->preview_url,
            'thumbnail_url' => $this->thumbnail_url,
            // Cette URL porte le jeton de la galerie dans sa chaine de repli.
            $this->mergeWhen($this->resource->hasAppended('clean_thumbnail_url'), fn () => [
                'clean_thumbnail_url' => $this->clean_thumbnail_url,
            ]),
        ];
    }
}
