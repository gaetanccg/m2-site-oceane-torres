<?php

namespace App\Services;

use App\Exceptions\Sms\SmsPermanentFailure;
use App\Exceptions\Sms\SmsTransientFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoSmsService
{
    private const ENDPOINT = 'https://api.brevo.com/v3/transactionalSMS/sms';

    /**
     * @throws SmsPermanentFailure reessayer ne changerait rien
     * @throws SmsTransientFailure une reprise a du sens
     */
    public function send(string $recipient, string $content): void
    {
        $apiKey = config('services.brevo.api_key');
        $sender = trim((string) config('services.brevo.sms_sender', 'OceanePhoto'));

        if (! $apiKey) {
            throw new SmsPermanentFailure('BREVO_API_KEY absente.');
        }

        // Brevo requires alphanumeric sender ≤ 11 chars. Fail-fast to avoid wasted API calls and retries.
        if (strlen($sender) > 11 || ! preg_match('/^[A-Za-z0-9]+$/', $sender)) {
            throw new SmsPermanentFailure("Nom d'expéditeur invalide : « {$sender} » (alphanumérique, 11 caractères max).");
        }

        $normalized = $this->normalizePhone($recipient);
        if (! $normalized) {
            throw new SmsPermanentFailure("Numéro invalide : « {$recipient} ».");
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

            if ($response->successful()) {
                return;
            }

            Log::error('BrevoSmsService: send failed', [
                'recipient' => $normalized,
                'sender' => $sender,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // 429 et 5xx passent ; les autres 4xx sont des refus definitifs (numero
            // blackliste, credit epuise, charge utile invalide).
            $status = $response->status();
            if ($status === 429 || $status >= 500) {
                throw new SmsTransientFailure("Brevo a répondu {$status}.");
            }

            throw new SmsPermanentFailure("Brevo a refusé l'envoi ({$status}).");
        } catch (ConnectionException $e) {
            throw new SmsTransientFailure('Brevo injoignable : '.$e->getMessage());
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
