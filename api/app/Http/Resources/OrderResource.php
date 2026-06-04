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
            'discount_amount' => (float) $this->discount_amount,
            'gift_code' => $this->gift_code,
            'shipping_fee' => (float) $this->shipping_fee,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'has_prints' => $this->hasPrintItems(),
            'customer_email' => $this->customer_email,
            'customer_name' => $this->customer_name,
            'shipping' => $this->shipping_fee > 0 ? [
                'phone' => $this->shipping_phone,
                'address_line1' => $this->shipping_address_line1,
                'address_line2' => $this->shipping_address_line2,
                'postal_code' => $this->shipping_postal_code,
                'city' => $this->shipping_city,
                'country' => $this->shipping_country,
            ] : null,
        ];
    }
}
