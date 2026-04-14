<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $startDate = $this->date;
        $duration = $this->prestation?->duration ?? 60;
        $endDate = $startDate->copy()->addMinutes($duration);

        return [
            'id' => $this->id,
            'title' => $this->prestation?->title ?? 'Reservation',
            'start' => $startDate->toIso8601String(),
            'end' => $endDate->toIso8601String(),
            'status' => $this->status,
            'client' => $this->client_name ?? 'Client',
            'prestation' => $this->prestation?->title ?? 'N/A',
        ];
    }
}
