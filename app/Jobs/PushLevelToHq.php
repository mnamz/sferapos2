<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\StockHq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Pushes ONE product's current on-hand to HQ right after a manual stock change
 * (restock, withdraw, or stock edited on the product form), so HQ's figure —
 * shown as "Source @OUTLET" on transfers — is right within seconds instead of
 * waiting for the hourly stock:snapshot.
 *
 * It is the same absolute reconciliation stock:snapshot does, for one product:
 * HQ records (reported − its ledger) as an adjustment. Reading the stock when
 * the job RUNS (not when it was queued) keeps it correct if more changes land
 * in between. Sales reach HQ as their own events on the same queue, and earlier
 * ones are processed first, so a sale is never counted twice.
 */
class PushLevelToHq implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120, 300, 600];

    public function __construct(public int $productId) {}

    public function handle(StockHq $hq): void
    {
        if (! $hq->enabled()) {
            return;
        }

        $product = Product::with('category')->find($this->productId);
        // Serial-tracked stock already syncs live, unit by unit, as serials are
        // added and removed; a level push would duplicate that.
        if (! $product || $product->serial_tracked) {
            return;
        }

        // The product must exist at HQ before a level can reference its sku.
        $hq->syncProducts([$product]);
        $hq->pushLevels([$product], 'manual-'.Str::uuid());
    }
}
