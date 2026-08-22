<?php

namespace App\Services;

use App\Models\Order;
use App\Support\OrderAccessSignature;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SumUpService
{
    private string $apiKey;

    private string $merchantCode;

    private string $apiUrl;

    private string $returnUrl;

    public function __construct()
    {
        $this->apiKey = config('sumup.api_key') ?? throw new \RuntimeException('SUMUP_API_KEY non configuré');
        $this->merchantCode = config('sumup.merchant_code') ?? throw new \RuntimeException('SUMUP_MERCHANT_CODE non configuré');
        $this->apiUrl = config('sumup.api_url') ?? 'https://api.sumup.com';
        $this->returnUrl = config('sumup.checkout.return_url') ?? throw new \RuntimeException('FRONTEND_URL non configuré');
    }

    /**
     * Create a checkout session for an order
     */
    public function createCheckout(Order $order): array
    {
        // Use order ID + random suffix to ensure unique reference per attempt
        // (SumUp rejects duplicate references even for FAILED checkouts)
        $reference = $order->id.'_'.Str::random(8);

        $payload = [
            'checkout_reference' => $reference,
            'amount' => (float) $order->total,
            'currency' => $order->currency,
            'merchant_code' => $this->merchantCode,
            'description' => 'Commande photos - '.$order->order_number,
            'return_url' => $this->returnUrl.'?order='.$order->id.'&token='.OrderAccessSignature::for($order),
        ];

        // Include customer info for SumUp dashboard tracking
        $customerEmail = $order->customer_email;
        if ($customerEmail) {
            $payload['personal_details'] = array_filter([
                'email' => $customerEmail,
                'first_name' => $order->user?->first_name ?? $order->guest_first_name,
                'last_name' => $order->user?->last_name ?? $order->guest_last_name,
            ]);
        }

        $response = Http::timeout(15)->withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl.'/v0.1/checkouts', $payload);

        if (! $response->successful()) {
            Log::error('SumUp checkout creation failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new \Exception('Erreur lors de la création du checkout SumUp.');
        }

        $data = $response->json();

        // Update order with checkout ID
        $order->update([
            'sumup_checkout_id' => $data['id'],
        ]);

        return $data;
    }

    /**
     * Get checkout status
     */
    public function getCheckout(string $checkoutId): array
    {
        $response = Http::timeout(10)->withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->get($this->apiUrl.'/v0.1/checkouts/'.$checkoutId);

        if (! $response->successful()) {
            Log::error('SumUp get checkout failed', [
                'checkout_id' => $checkoutId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new \Exception('Erreur lors de la récupération du checkout SumUp');
        }

        return $response->json();
    }

    /**
     * Deactivate/cancel a checkout
     */
    public function deactivateCheckout(string $checkoutId): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->delete($this->apiUrl.'/v0.1/checkouts/'.$checkoutId);

        return $response->successful();
    }
}
