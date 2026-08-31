<?php

namespace App\Observers;

use App\Jobs\PushProductToHq;
use App\Models\Product;
use App\Support\HqSyncMute;
use Illuminate\Support\Facades\Log;

/**
 * Keeps HQ's catalogue in sync with this branch's products. Pushes on create
 * and on genuine catalogue edits — but NOT on sales: a sale only decrements
 * `stock` (and `pending_serial_count`), which are excluded from HQ_FIELDS, so
 * the observer stays silent for the thousands of stock movements a day.
 */
class ProductObserver
{
    /**
     * Product columns HQ mirrors (see StockHq::syncProducts). A change to any of
     * these warrants a push; a change to only stock/pending_serial_count (a sale)
     * does not.
     *
     * @var array<int, string>
     */
    private const HQ_FIELDS = ['name', 'price', 'barcode', 'category_id', 'status', 'serial_tracked'];

    public function created(Product $product): void
    {
        $this->push($product);
    }

    public function updated(Product $product): void
    {
        if (! $product->wasChanged(self::HQ_FIELDS)) {
            return;
        }

        $this->push($product);
    }

    public function restored(Product $product): void
    {
        $this->push($product);
    }

    private function push(Product $product): void
    {
        if (! config('stockhq.enabled')) {
            return;
        }

        // Don't echo a change HQ itself drove (transfer application) back to HQ.
        if (HqSyncMute::isMuted()) {
            return;
        }

        try {
            PushProductToHq::dispatch($product->id)
                ->onQueue(config('stockhq.queue', 'stockhq'))
                ->afterCommit();
        } catch (\Throwable $e) {
            // Syncing to HQ must never affect a product save.
            Log::warning('[StockHq] failed to queue product push: '.$e->getMessage());
        }
    }
}
