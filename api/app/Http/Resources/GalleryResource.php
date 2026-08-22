<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'class_name' => $this->class_name,
            'event_date' => $this->event_date,
            'event_link' => $this->event_link,
            'photos' => PhotoResource::collection($this->whenLoaded('photos')),
            'photos_count' => $this->whenCounted('photos'),
            'children_count' => $this->whenCounted('children'),
            'sort_order' => $this->sort_order,
            'event_category_id' => $this->event_category_id,
            'event_category' => $this->whenLoaded('eventCategory'),
            'children' => self::collection($this->whenLoaded('children')),
            'parent' => new static($this->whenLoaded('parent')),
            // `isset()` ne convient pas : il declenche la resolution d'attribut du modele.
            $this->mergeWhen(array_key_exists('cover_photo', $this->resource->getAttributes()), fn () => [
                'cover_photo' => $this->cover_photo ? new PhotoResource($this->cover_photo) : null,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
