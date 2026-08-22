<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

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
