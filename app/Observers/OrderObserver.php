<?php

namespace App\Observers;

use App\Jobs\PushOrderToHq;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * When an order (sale) is created, queue a push to HQ — after the DB
     * transaction commits, so the order's line items are present.
     */
    public function created(Order $order): void
    {
        if (! config('stockhq.enabled')) {
            return;
        }

        try {
            PushOrderToHq::dispatch($order->id)
                ->onQueue(config('stockhq.queue', 'stockhq'))
                ->afterCommit();
        } catch (\Throwable $e) {
            // Syncing to HQ must never affect a sale.
            Log::warning('[StockHq] failed to queue order push: '.$e->getMessage());
        }
    }
}
