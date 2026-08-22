<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Le `return_url` de SumUp est construit AVANT que le jeton de téléchargement n'existe :
 * celui-ci n'est généré qu'à la confirmation du paiement.
 */
final class OrderAccessSignature
{
    private const DEFAULT_TTL_MINUTES = 120;

    public static function for(Order|string $order, ?int $ttlMinutes = null): string
    {
        $orderId = $order instanceof Order ? $order->id : $order;
        $expiresAt = Carbon::now()->addMinutes($ttlMinutes ?? self::DEFAULT_TTL_MINUTES)->getTimestamp();

        return $expiresAt.'.'.self::digest($orderId, $expiresAt);
    }

    public static function verify(Order|string $order, ?string $candidate): bool
    {
        if ($candidate === null || ! str_contains($candidate, '.')) {
            return false;
        }

        [$expiresAt, $digest] = explode('.', $candidate, 2);

        if (! ctype_digit($expiresAt) || (int) $expiresAt < Carbon::now()->getTimestamp()) {
            return false;
        }

        $orderId = $order instanceof Order ? $order->id : $order;

        return hash_equals(self::digest($orderId, (int) $expiresAt), $digest);
    }

    private static function digest(string $orderId, int $expiresAt): string
    {
        return hash_hmac('sha256', $orderId.'|'.$expiresAt, self::key());
    }

    private static function key(): string
    {
        $key = (string) config('app.key');

        return Str::startsWith($key, 'base64:')
            ? base64_decode(Str::after($key, 'base64:'))
            : $key;
    }
}
