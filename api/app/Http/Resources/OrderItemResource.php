<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'photo_id' => $this->photo_id,
            'product_type' => $this->product_type ?? 'digital',
            'product_type_label' => $this->getProductTypeLabel(),
            'is_print' => $this->isPrint(),
            'photo_title' => $this->photo_title,
            'gallery_title' => $this->gallery_title,
            'price' => (float) $this->price,
            'is_downloaded' => $this->is_downloaded,
            'display_url' => $this->whenLoaded('photo', fn () => $this->photo?->display_url),
            'preview_url' => $this->whenLoaded('photo', fn () => $this->photo?->preview_url),
            'thumbnail_url' => $this->whenLoaded('photo', fn () => $this->photo?->thumbnail_url),
        ];
    }
}
