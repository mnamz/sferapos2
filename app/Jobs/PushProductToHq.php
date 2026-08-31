<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\StockHq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes one product's catalogue row to HQ. Runs on the queue, so a slow or
 * down HQ never affects the product save. Retries with backoff; idempotent at
 * HQ (upsert on sku).
 */
class PushProductToHq implements ShouldQueue
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
        if (! $product) {
            return;
        }

        $hq->syncProducts([$product]);
    }
}
