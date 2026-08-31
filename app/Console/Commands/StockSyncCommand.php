<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use App\Services\StockHq;
use Illuminate\Console\Command;

/**
 * One-off backfill of this branch's catalogue + order history to HQ.
 * Ongoing sales are pushed automatically by the OrderObserver.
 *
 *   php artisan stock:sync                 # products + all orders
 *   php artisan stock:sync --products      # catalogue only
 *   php artisan stock:sync --orders        # orders only
 *   php artisan stock:sync --since=2026-08-01
 */
class StockSyncCommand extends Command
{
    protected $signature = 'stock:sync {--products : only sync products} {--orders : only sync orders} {--since= : only orders on/after this date}';

    protected $description = 'Backfill products and/or orders to the central Stock HQ dashboard';

    public function handle(StockHq $hq): int
    {
        if (! $hq->enabled()) {
            $this->error('StockHq is not enabled/configured. Set STOCK_HQ_* in .env.');

            return self::FAILURE;
        }

        $doProducts = $this->option('products') || ! $this->option('orders');
        $doOrders = $this->option('orders') || ! $this->option('products');

        if ($doProducts) {
            $this->info('Syncing products…');
            $count = 0;
            Product::with('category')->chunkById(200, function ($chunk) use ($hq, &$count) {
                $hq->syncProducts($chunk);
                $count += $chunk->count();
                $this->output->write('.');
            });
            $this->newLine();
            $this->info("Products synced: {$count}");
        }

        if ($doOrders) {
            $this->info('Syncing orders…');
            $query = Order::with(['items.product.category', 'items.serials', 'user'])->orderBy('id');
            if ($since = $this->option('since')) {
                $query->whereDate('created_at', '>=', $since);
            }

            $orders = 0;
            $lines = 0;
            $query->chunkById(100, function ($chunk) use ($hq, &$orders, &$lines) {
                // Make sure this batch's products exist at HQ first (robust even
                // if --orders was run without --products).
                $hq->syncProducts($chunk->flatMap->items->map->product->filter());

                $batch = [];
                foreach ($chunk as $order) {
                    foreach ($hq->saleEventsForOrder($order) as $event) {
                        $batch[] = $event;
                    }
                    $orders++;
                }

                $hq->pushEvents($batch);
                $lines += count($batch);
                $this->output->write('.');
            });
            $this->newLine();
            $this->info("Orders synced: {$orders} ({$lines} sale lines)");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
