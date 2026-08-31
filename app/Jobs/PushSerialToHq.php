<?php

namespace App\Jobs;

use App\Models\ProductSerial;
use App\Services\StockHq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a single serial's arrival ('in', a receipt) or removal ('out', a
 * write-off) to HQ as a stock event. Queued so a slow/down HQ never affects the
 * branch; idempotent at HQ (deterministic per-serial event_id). Sales are NOT
 * handled here — a serial going to 'sold' rides its order's sale event.
 */
class PushSerialToHq implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120, 300, 600];

    public function __construct(public int $serialId, public string $direction) {}

    public function handle(StockHq $hq): void
    {
        if (! $hq->enabled()) {
            return;
        }

        // withTrashed so an 'out' (soft-deleted) serial can still be loaded.
        $serial = ProductSerial::withTrashed()->with('product')->find($this->serialId);
        if (! $serial) {
            return;
        }

        $hq->pushSerialEvent($serial, $this->direction);
    }
}
