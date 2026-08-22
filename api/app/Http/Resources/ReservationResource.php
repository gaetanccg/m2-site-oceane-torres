<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toIso8601String(),
            'status' => $this->status,
            'message' => $this->message,
            'date_preferences' => $this->date_preferences,
            'created_at' => $this->created_at?->toIso8601String(),
            'prestation' => new PrestationResource($this->whenLoaded('prestation')),
        ];
    }
}
