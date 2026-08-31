<?php

namespace App\Observers;

use App\Jobs\PushSerialToHq;
use App\Models\ProductSerial;
use App\Support\HqSyncMute;
use Illuminate\Support\Facades\Log;

/**
 * Live-syncs serial receipts and removals to HQ. Only two transitions matter:
 *  - created (available)  → a unit arrived  → HQ receipt (purchase +1, in_stock)
 *  - deleted (available)  → a unit removed  → HQ write-off (adjustment -1)
 *
 * A serial going available→sold is a SALE, already carried by the order's sale
 * event, so `updated` is deliberately NOT observed (avoids double-counting).
 */
class ProductSerialObserver
{
    public function created(ProductSerial $serial): void
    {
        if ($serial->status !== 'available') {
            return;
        }

        $this->push($serial->id, 'in');
    }

    public function deleted(ProductSerial $serial): void
    {
        // Only an available (in-stock) unit leaving is news to HQ; a sold unit
        // is already accounted for by its sale event.
        if ($serial->status !== 'available') {
            return;
        }

        $this->push($serial->id, 'out');
    }

    private function push(int $serialId, string $direction): void
    {
        if (! config('stockhq.enabled')) {
            return;
        }

        // Don't echo a change HQ itself drove (transfer application) back to HQ.
        if (HqSyncMute::isMuted()) {
            return;
        }

        try {
            PushSerialToHq::dispatch($serialId, $direction)
                ->onQueue(config('stockhq.queue', 'stockhq'))
                ->afterCommit();
        } catch (\Throwable $e) {
            // Syncing to HQ must never affect a serial operation.
            Log::warning('[StockHq] failed to queue serial push: '.$e->getMessage());
        }
    }
}
