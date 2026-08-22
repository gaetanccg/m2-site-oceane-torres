<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Le cast `decimal:2` du modele serialise `price` et `background_opacity` en CHAINES
 * (« 150.00 », « 0.50 »), alors que les types front les declarent `number`. Rien ne
 * cassait — Intl.NumberFormat coerce — mais la forme envoyee ne correspondait pas au
 * contrat declare.
 */
class PrestationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'icon' => $this->icon,
            'title' => $this->title,
            'description' => $this->description,
            'features' => $this->features,
            'price' => $this->price === null ? null : (float) $this->price,
            'price_text' => $this->price_text,
            'price_unit' => $this->price_unit,
            'duration' => $this->duration,
            'category' => $this->category,
            'background_image' => $this->background_image,
            'background_opacity' => $this->background_opacity === null ? null : (float) $this->background_opacity,
            'disclaimer' => $this->disclaimer,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
