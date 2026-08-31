<?php

namespace App\Console\Commands;

use App\Models\HqTransferLeg;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Services\ProductSerialService;
use App\Services\StockHq;
use App\Support\HqSyncMute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pull pending transfer commands from HQ and execute them against this branch's
 * own inventory, then confirm back. Scheduled every minute.
 *
 *  - deduct (as source): release the named serials / decrement stock.
 *  - add    (as destination): create the named serials / increment stock.
 *
 * Safety:
 *  - No-op unless STOCK_HQ_ENABLED and STOCK_HQ_TRANSFERS are both on.
 *  - Each leg is applied at most once (HqTransferLeg), so a failed confirm that
 *    leaves the transfer pending at HQ cannot double-apply on the next poll.
 *  - Local writes run under HqSyncMute so they aren't echoed back to HQ (HQ
 *    already records them via the transfer's ledger legs).
 *  - A per-branch lock stops overlapping scheduler ticks from racing.
 */
class StockTransfersPollCommand extends Command
{
    protected $signature = 'stock:transfers-poll';

    protected $description = 'Pull pending transfer commands from HQ, apply to local inventory, and confirm back';

    public function handle(StockHq $hq, ProductSerialService $serials): int
    {
        if (! $hq->enabled() || ! config('stockhq.transfers_enabled')) {
            return self::SUCCESS; // inert until explicitly enabled
        }

        $lock = Cache::lock('stock-transfers-poll-'.$hq->branchId(), 110);
        if (! $lock->get()) {
            return self::SUCCESS; // a previous tick is still running
        }

        try {
            foreach ($hq->pendingTransfers() as $transfer) {
                $this->process($hq, $serials, $transfer);
            }
        } catch (\Throwable $e) {
            Log::warning('[StockHq] transfer poll failed: '.$e->getMessage());
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $transfer
     */
    private function process(StockHq $hq, ProductSerialService $serials, array $transfer): void
    {
        $id = (int) $transfer['id'];
        $number = (string) $transfer['transfer_number'];
        $leg = (string) $transfer['action']; // 'deduct' | 'add'
        $items = $transfer['items'] ?? [];

        try {
            $alreadyApplied = HqTransferLeg::where('transfer_number', $number)->where('leg', $leg)->exists();

            if (! $alreadyApplied) {
                // Resolve + validate EVERY line before touching inventory: an
                // unfulfillable transfer is rejected whole, never half-applied.
                $resolved = $this->resolveAndValidate($hq, $leg, $items);
                if (is_string($resolved)) {
                    $hq->rejectTransfer($id, $resolved);
                    $this->warn("Rejected {$number}: {$resolved}");

                    return;
                }

                // The destination must CLAIM the add (deducted -> adding) before
                // it physically adds, so a concurrent rollback cannot fire mid-add.
                if ($leg === 'add' && ! $hq->claimTransfer($id)) {
                    $this->warn("Transfer {$number}: add no longer claimable — skipping.");

                    return;
                }

                DB::transaction(function () use ($resolved, $leg, $serials, $number) {
                    HqSyncMute::muted(function () use ($resolved, $leg, $serials) {
                        foreach ($resolved as $r) {
                            $leg === 'deduct'
                                ? $this->applyDeduct($serials, $r)
                                : $this->applyAdd($serials, $r);
                        }
                    });

                    HqTransferLeg::create([
                        'transfer_number' => $number,
                        'leg' => $leg,
                        'applied_at' => now(),
                    ]);
                });
            }

            // Confirm (idempotent at HQ). The confirmed quantity is what we truly
            // applied — for serial lines that's the serial count, which validation
            // guaranteed equals the requested quantity.
            $confirmItems = collect($items)->map(fn ($i) => [
                'product_sku' => (string) $i['product_sku'],
                'quantity_confirmed' => (int) $i['quantity'],
            ])->all();

            $hq->confirmTransfer($id, $leg === 'deduct' ? 'deducted' : 'added', $confirmItems);

            HqTransferLeg::where('transfer_number', $number)->where('leg', $leg)
                ->update(['confirmed_at' => now()]);

            $this->info("Transfer {$number}: {$leg} applied + confirmed.");
        } catch (\Throwable $e) {
            // Leave it: an unwritten HqTransferLeg means the next poll re-validates
            // and retries; a written one means it just re-confirms. Never blocks
            // the branch, never half-applies (the apply is one transaction).
            Log::warning("[StockHq] transfer {$number} {$leg} failed: ".$e->getMessage());
            $this->warn("Transfer {$number}: {$leg} deferred — ".$e->getMessage());
        }
    }

    /**
     * Resolve every line to a local product and prove the leg is fully
     * fulfillable BEFORE any inventory changes. Returns the resolved rows, or a
     * human-readable reason string when the transfer must be rejected.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{product:Product, qty:int, serials:array<int,string>}>|string
     */
    private function resolveAndValidate(StockHq $hq, string $leg, array $items): array|string
    {
        $resolved = [];

        foreach ($items as $item) {
            $sku = (string) $item['product_sku'];
            $qty = (int) $item['quantity'];
            $reqSerials = array_values(array_filter(array_map('strval', $item['serial_numbers'] ?? []), fn ($s) => $s !== ''));

            $product = $hq->productForSku($sku);
            if (! $product) {
                return "This branch has no product for SKU {$sku}.";
            }

            if ($product->serial_tracked) {
                // A serial-tracked line must carry exactly one serial per unit.
                if (count($reqSerials) !== $qty) {
                    return "Serial count for {$sku} (".count($reqSerials).") does not match quantity {$qty}.";
                }

                if ($leg === 'deduct') {
                    // Every named unit must be available here to release it.
                    $available = $product->serials()
                        ->whereIn('serial_number', $reqSerials)
                        ->where('status', 'available')
                        ->pluck('serial_number')->all();
                    $missing = array_diff($reqSerials, $available);
                    if (! empty($missing)) {
                        return "Serial(s) not available at source for {$sku}: ".implode(', ', $missing);
                    }
                } else {
                    // None of the incoming serials may already exist live anywhere
                    // here (the unique index is global among non-deleted rows).
                    $clash = ProductSerial::whereIn('serial_number', $reqSerials)->pluck('serial_number')->all();
                    if (! empty($clash)) {
                        return "Serial(s) already present at destination for {$sku}: ".implode(', ', $clash);
                    }
                }
            } elseif ($leg === 'deduct' && (int) $product->stock < $qty) {
                // Non-serial: don't drive live stock negative.
                return "Insufficient stock at source for {$sku}: have {$product->stock}, need {$qty}.";
            }

            $resolved[] = ['product' => $product, 'qty' => $qty, 'serials' => $reqSerials];
        }

        return $resolved;
    }

    /**
     * @param  array{product:Product, qty:int, serials:array<int,string>}  $r
     */
    private function applyDeduct(ProductSerialService $serials, array $r): void
    {
        $product = $r['product'];

        if ($product->serial_tracked) {
            foreach ($r['serials'] as $sn) {
                $serial = $product->serials()->where('serial_number', $sn)->where('status', 'available')->first();
                if (! $serial) {
                    // Validated moments ago; a concurrent sale must have taken it.
                    // Abort the whole leg — the transaction rolls back and the next
                    // poll re-validates and rejects, rather than under-releasing.
                    throw new \RuntimeException("Serial {$sn} no longer available to release.");
                }
                $serials->removeSerial($serial); // soft-delete + resync stock
            }

            return;
        }

        // Non-serial: re-check under lock so a concurrent sale can't drive negative.
        $locked = Product::whereKey($product->id)->lockForUpdate()->first();
        if ((int) $locked->stock < $r['qty']) {
            throw new \RuntimeException("Insufficient stock to release for product {$product->id}.");
        }
        $locked->decrement('stock', $r['qty']);
    }

    /**
     * @param  array{product:Product, qty:int, serials:array<int,string>}  $r
     */
    private function applyAdd(ProductSerialService $serials, array $r): void
    {
        $product = $r['product'];

        if ($product->serial_tracked) {
            // Receipt: create the named units without touching pending_serial_count.
            $serials->addSerials($product, $r['serials'], adjustPending: false);

            return;
        }

        $product->increment('stock', $r['qty']);
    }
}
