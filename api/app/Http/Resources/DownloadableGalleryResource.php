<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Ajoute `access_token` : l'appelant de l'endpoint de telechargement le detient deja
 * dans son URL, et le front s'en sert pour construire les URL d'images.
 */
class DownloadableGalleryResource extends GalleryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'access_token' => $this->access_token,
        ];
    }
}
