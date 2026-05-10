<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoSmsService
{
    private const ENDPOINT = 'https://api.brevo.com/v3/transactionalSMS/sms';

    public function send(string $recipient, string $content): bool
    {
        $apiKey = config('services.brevo.api_key');
        $sender = config('services.brevo.sms_sender', 'OceanePhoto');

        if (! $apiKey) {
            Log::error('BrevoSmsService: BREVO_API_KEY not configured');

            return false;
        }

        $normalized = $this->normalizePhone($recipient);
        if (! $normalized) {
            Log::warning('BrevoSmsService: invalid phone number', ['phone' => $recipient]);

            return false;
        }

        try {
            $response = Http::withHeaders([
                'api-key' => $apiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])->timeout(15)->post(self::ENDPOINT, [
                'type' => 'transactional',
                'unicodeEnabled' => false,
                'sender' => $sender,
                'recipient' => $normalized,
                'content' => $content,
            ]);

            if (! $response->successful()) {
                Log::error('BrevoSmsService: send failed', [
                    'recipient' => $normalized,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('BrevoSmsService: HTTP exception', [
                'recipient' => $normalized,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Normalize a French phone number to international format (+33...).
     * Accepts: +33XXX..., 0033XXX..., 06XXX..., 06 XX XX XX XX, etc.
     * Returns null if invalid.
     */
    public function normalizePhone(string $phone): ?string
    {
        $clean = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($clean, '+')) {
            return strlen($clean) >= 10 ? $clean : null;
        }

        if (str_starts_with($clean, '0033')) {
            return '+33'.substr($clean, 4);
        }

        if (str_starts_with($clean, '33') && strlen($clean) >= 11) {
            return '+'.$clean;
        }

        if (str_starts_with($clean, '0') && strlen($clean) === 10) {
            return '+33'.substr($clean, 1);
        }

        return null;
    }
}
