<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\StockHq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes one order's header (customer, totals, profit, due, payment, status)
 * to HQ after it changes — payment taken, status moved on, order deleted.
 * Reads the order fresh when it runs, so it always sends the latest state;
 * HQ ignores anything older than what it already holds.
 */
class PushOrderHeaderToHq implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120, 300, 600];

    public function __construct(public int $orderId) {}

    public function handle(StockHq $hq): void
    {
        if (! $hq->enabled()) {
            return;
        }

        // withTrashed: a deleted order must still reach HQ, flagged as deleted.
        $order = Order::withTrashed()->with(['customer' => fn ($q) => $q->withTrashed()])->find($this->orderId);
        if (! $order) {
            return;
        }

        $hq->pushOrderHeaders([$hq->orderHeader($order)]);
    }
}
