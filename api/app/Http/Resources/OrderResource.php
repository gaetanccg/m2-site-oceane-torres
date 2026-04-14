<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'detailed_status' => $this->detailed_status,
            'print_status' => $this->print_status,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'subtotal' => (float) $this->subtotal,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'has_prints' => $this->hasPrintItems(),
            'customer_email' => $this->customer_email,
            'customer_name' => $this->customer_name,
        ];
    }
}
