<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\StockHq;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Push this branch's CURRENT on-hand stock levels (and in-stock serials) to HQ.
 * HQ reconciles each value against its own ledger, so this is idempotent and
 * safe to re-run any time to refresh HQ's stock picture.
 *
 *   php artisan stock:snapshot
 */
class StockSnapshotCommand extends Command
{
    protected $signature = 'stock:snapshot';

    protected $description = 'Push current on-hand levels + in-stock serials to the central Stock HQ dashboard';

    public function handle(StockHq $hq): int
    {
        if (! $hq->enabled()) {
            $this->error('StockHq is not enabled/configured. Set STOCK_HQ_* in .env.');

            return self::FAILURE;
        }

        // One snapshot per branch at a time: two concurrent runs would each read
        // the same pre-reconciliation on-hand at HQ and double-apply.
        $lock = Cache::lock('stock-snapshot-'.$hq->branchId(), 900);
        if (! $lock->get()) {
            $this->error('A stock snapshot is already running for this branch.');

            return self::FAILURE;
        }

        try {
            // One run token shared by every chunk, so HQ treats a retried chunk as
            // a duplicate but a fresh run as a new reconciliation.
            $runId = (string) Str::uuid();

            $this->info("Snapshotting stock levels (run {$runId})…");
            $count = 0;
            $totals = [
                'applied' => 0, 'unchanged' => 0, 'unknown_sku' => 0,
                'serials_set' => 0, 'serials_dropped' => 0,
                'serials_skipped' => 0, 'serial_count_mismatch' => 0,
            ];

            Product::with(['category', 'serials' => fn ($q) => $q->where('status', 'available')])
                ->chunkById(200, function ($chunk) use ($hq, $runId, &$count, &$totals) {
                    // Make sure the products exist at HQ before their levels reference them.
                    $hq->syncProducts($chunk);
                    $summary = $hq->pushLevels($chunk, $runId);
                    foreach (array_keys($totals) as $k) {
                        $totals[$k] += (int) ($summary[$k] ?? 0);
                    }
                    $count += $chunk->count();
                    $this->output->write('.');
                });

            $this->newLine();
            $this->info("Stock levels snapshotted: {$count} product(s)");
            $this->line(sprintf(
                'HQ: applied=%d unchanged=%d unknown_sku=%d | serials set=%d dropped=%d skipped=%d count_mismatch=%d',
                $totals['applied'], $totals['unchanged'], $totals['unknown_sku'],
                $totals['serials_set'], $totals['serials_dropped'],
                $totals['serials_skipped'], $totals['serial_count_mismatch'],
            ));
            if ($totals['serials_skipped'] > 0 || $totals['serial_count_mismatch'] > 0 || $totals['unknown_sku'] > 0) {
                $this->warn('Some rows diverged (skipped serials / count mismatch / unknown SKU) — review the numbers above.');
            }
            $this->info('Done.');

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
