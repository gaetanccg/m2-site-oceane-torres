<?php

namespace App\Jobs;

use App\Services\BrevoSmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendSchoolSessionSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 30;

    public function __construct(
        public string $recipient,
        public string $content,
    ) {}

    public function handle(BrevoSmsService $sms): void
    {
        $sent = $sms->send($this->recipient, $this->content);

        if (! $sent) {
            // Trigger retry
            throw new \RuntimeException('Brevo SMS send failed for '.$this->recipient);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendSchoolSessionSmsJob: permanently failed', [
            'recipient' => $this->recipient,
            'error' => $exception->getMessage(),
        ]);
    }
}
