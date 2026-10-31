<?php

namespace App\Observers;

use App\Jobs\PushOrderHeaderToHq;
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

    /** Payment taken, status changed, totals edited — re-send the header. */
    public function updated(Order $order): void
    {
        $this->pushHeader($order);
    }

    /** Soft-deleted (voided) — HQ marks the order deleted. */
    public function deleted(Order $order): void
    {
        $this->pushHeader($order);
    }

    public function restored(Order $order): void
    {
        $this->pushHeader($order);
    }

    private function pushHeader(Order $order): void
    {
        if (! config('stockhq.enabled')) {
            return;
        }

        try {
            PushOrderHeaderToHq::dispatch($order->id)
                ->onQueue(config('stockhq.queue', 'stockhq'))
                ->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('[StockHq] failed to queue order header push: '.$e->getMessage());
        }
    }
}
