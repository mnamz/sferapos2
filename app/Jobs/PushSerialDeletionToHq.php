<?php

namespace App\Jobs;

use App\Services\StockHq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends a serial-deletion request (or this outlet's own decision on one) to
 * HQ. Carries a payload built at the moment of the action, because approving
 * deletes the serial and rejecting clears the request fields.
 */
class PushSerialDeletionToHq implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120, 300, 600];

    /** @param  array<string, mixed>  $payload */
    public function __construct(public array $payload) {}

    public function handle(StockHq $hq): void
    {
        if (! $hq->enabled()) {
            return;
        }

        $hq->pushSerialDeletions([$this->payload]);
    }

    /**
     * Queue a push; never let HQ problems affect the serial action itself.
     * (Not named queue(): Laravel treats a job's queue() method as its own
     * custom-queueing hook.)
     */
    public static function send(array $payload): void
    {
        if (! config('stockhq.enabled') || empty($payload['requested_at'])) {
            return;
        }

        try {
            static::dispatch($payload)->onQueue(config('stockhq.queue', 'stockhq'))->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('[StockHq] failed to queue serial deletion push: '.$e->getMessage());
        }
    }
}
