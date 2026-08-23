<?php

namespace App\Jobs;

use App\Exceptions\Sms\SmsPermanentFailure;
use App\Services\BrevoSmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 30;

    public function __construct(
        public string $recipient,
        public string $content,
    ) {
        $this->onQueue('realtime');
    }

    public function handle(BrevoSmsService $sms): void
    {
        try {
            $sms->send($this->recipient, $this->content);
        } catch (SmsPermanentFailure $e) {
            // Un SMS se paie : reessayer un numero invalide gaspille trois appels d'API
            // pour un refus certain.
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Envoi SMS définitivement échoué', [
            'recipient' => $this->recipient,
            'error' => $exception->getMessage(),
        ]);
    }
}
