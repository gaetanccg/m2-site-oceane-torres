<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Les identifiants SumUp et le bloc utilisateur ne sortent que par cette ressource :
 * les endpoints publics de commande sont accessibles par jeton, sans compte.
 */
class AdminOrderResource extends OrderResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'email' => $this->user->email,
                'name' => trim($this->user->first_name.' '.$this->user->last_name),
            ] : null,
            'payment' => $this->payment ? [
                'id' => $this->payment->id,
                'provider' => $this->payment->provider,
                'status' => $this->payment->status,
                'provider_payment_id' => $this->payment->provider_payment_id,
            ] : null,
            'sumup_checkout_id' => $this->sumup_checkout_id,
            'sumup_transaction_id' => $this->sumup_transaction_id,
        ];
    }
}
