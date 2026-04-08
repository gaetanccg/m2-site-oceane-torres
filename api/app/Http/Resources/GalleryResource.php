<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryResource extends JsonResource
{
    /**
     * Admin gallery listing format.
     * Expects withCount('photos', 'downloadable', 'downloadedPhotos', 'likedPhotos')
     * and withSum('photos', 'downloads_count') to be loaded.
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        // Compute download_status from pre-loaded counts
        $downloadable = $this->downloadable_count ?? 0;
        $downloaded = $this->downloaded_photos_count ?? 0;
        $data['download_status'] = $downloadable === 0 || $downloaded === 0
            ? 'none'
            : ($downloaded >= $downloadable ? 'complete' : 'partial');

        $data['total_downloads_count'] = $this->photos_sum_downloads_count ?? 0;

        // Cover photo for event galleries
        if ($this->relationLoaded('thumbnailPhoto') || $this->relationLoaded('photos')) {
            $data['cover_photo'] = $this->thumbnailPhoto ?? $this->photos->first();
        }

        return $data;
    }
}
