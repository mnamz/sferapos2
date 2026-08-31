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
 * Pushes one order's sale lines to the HQ dashboard. Runs on the queue, so a
 * slow or down HQ never affects the sale. Retries with backoff; idempotent at
 * HQ (dedupe on event_id).
 */
class PushOrderToHq implements ShouldQueue
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

        $order = Order::with(['items.product.category', 'items.serials', 'user'])->find($this->orderId);
        if (! $order) {
            return;
        }

        // Ensure the products exist at HQ before their SKUs are referenced.
        $hq->syncProducts($order->items->map->product->filter());

        $hq->pushEvents($hq->saleEventsForOrder($order));
    }
}
