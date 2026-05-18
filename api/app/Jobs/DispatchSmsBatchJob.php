<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrator job: takes a batch of SMS messages and re-dispatches them with
 * a progressive delay to stay under Brevo's rate limits and avoid HTTP timeouts
 * on the calling endpoint.
 */
class DispatchSmsBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  array<int, array{phone: string, content: string}>  $messages
     * @param  int  $perSecond  Target throughput. 10/s is conservative for Brevo.
     */
    public function __construct(
        public array $messages,
        public int $perSecond = 10,
    ) {}

    public function handle(): void
    {
        $perSecond = max(1, $this->perSecond);

        foreach ($this->messages as $i => $message) {
            $delaySeconds = intdiv($i, $perSecond);

            SendSchoolSessionSmsJob::dispatch($message['phone'], $message['content'])
                ->delay(now()->addSeconds($delaySeconds));
        }

        Log::info('DispatchSmsBatchJob: dispatched batch', [
            'count' => count($this->messages),
            'per_second' => $perSecond,
            'spread_seconds' => intdiv(count($this->messages), $perSecond),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DispatchSmsBatchJob: orchestration failed', [
            'error' => $exception->getMessage(),
            'count' => count($this->messages),
        ]);
    }
}
