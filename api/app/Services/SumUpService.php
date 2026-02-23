<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        // Use order ID (UUID) as checkout_reference to ensure uniqueness
        // even if order_number gets reused after deletion
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl.'/v0.1/checkouts', [
            'checkout_reference' => $order->id,
            'amount' => (float) $order->total,
            'currency' => $order->currency,
            'merchant_code' => $this->merchantCode,
            'description' => 'Commande photos - '.$order->order_number,
            'return_url' => $this->returnUrl.'?order='.$order->id,
        ]);

        if (! $response->successful()) {
            Log::error('SumUp checkout creation failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new \Exception('Erreur lors de la création du checkout SumUp: '.($response->json()['message'] ?? 'Unknown error'));
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
        $response = Http::withHeaders([
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
     * Process a checkout with card details (for server-side processing)
     * Note: For PCI compliance, prefer using the SumUp Card Widget on frontend
     */
    public function processCheckout(string $checkoutId, array $cardData): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->put($this->apiUrl.'/v0.1/checkouts/'.$checkoutId, [
            'payment_type' => 'card',
            'card' => $cardData,
        ]);

        if (! $response->successful()) {
            Log::error('SumUp process checkout failed', [
                'checkout_id' => $checkoutId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new \Exception('Erreur lors du traitement du paiement');
        }

        return $response->json();
    }

    /**
     * Complete checkout from callback/redirect
     */
    public function completeCheckout(string $checkoutId): array
    {
        $checkout = $this->getCheckout($checkoutId);

        return [
            'status' => $checkout['status'] ?? 'UNKNOWN',
            'transaction_id' => $checkout['transaction_id'] ?? null,
            'amount' => $checkout['amount'] ?? null,
        ];
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

    /**
     * Get available payment methods for merchant
     */
    public function getPaymentMethods(): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->get($this->apiUrl.'/v0.1/merchants/'.$this->merchantCode.'/payment-methods');

        if (! $response->successful()) {
            return [];
        }

        return $response->json();
    }
}
