<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\HqTransferLeg;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Services\ProductSerialService;
use App\Services\StockHq;
use App\Support\HqSyncMute;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pull pending transfer commands — plus serial-deletion decisions, and products
 * made or edited at HQ — from HQ and execute them against this branch's own inventory,
 * then confirm back.
 * Scheduled every minute.
 *
 *  - deduct (as source): release the named serials / decrement stock.
 *  - add    (as destination): create the named serials / increment stock.
 *
 * Safety:
 *  - No-op unless STOCK_HQ_ENABLED; transfers additionally need STOCK_HQ_TRANSFERS.
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
        if (! $hq->enabled()) {
            return self::SUCCESS; // inert until explicitly enabled
        }

        $lock = Cache::lock('stock-transfers-poll-'.$hq->branchId(), 110);
        if (! $lock->get()) {
            return self::SUCCESS; // a previous tick is still running
        }

        try {
            if (config('stockhq.transfers_enabled')) {
                try {
                    foreach ($hq->pendingTransfers() as $transfer) {
                        $this->process($hq, $serials, $transfer);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[StockHq] transfer poll failed: '.$e->getMessage());
                }
            }

            // Serial-deletion decisions made at HQ ride on the same tick, so no
            // extra cron entry is needed at any outlet.
            try {
                foreach ($hq->serialDeletionDecisions() as $decision) {
                    $this->applySerialDeletionDecision($hq, $serials, $decision);
                }
            } catch (\Throwable $e) {
                Log::warning('[StockHq] serial-deletion poll failed: '.$e->getMessage());
            }

            // Products made at HQ: created here with no stock, so the shop can
            // sell and transfer them before any arrives.
            try {
                foreach ($hq->productPushes() as $push) {
                    $this->applyProductPush($hq, $push);
                }
            } catch (\Throwable $e) {
                Log::warning('[StockHq] product push poll failed: '.$e->getMessage());
            }

            // Edits made at HQ to products this outlet carries.
            try {
                foreach ($hq->productEdits() as $edit) {
                    $this->applyProductEdit($hq, $edit);
                }
            } catch (\Throwable $e) {
                Log::warning('[StockHq] product edit poll failed: '.$e->getMessage());
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /**
     * Create a product HQ sent, unless this outlet already has its barcode, and
     * report back. Not muted: the new product's catalogue push is how HQ learns
     * this outlet carries it.
     *
     * @param  array<string, mixed>  $p
     */
    private function applyProductPush(StockHq $hq, array $p): void
    {
        $sku = trim((string) $p['sku']);

        if ($sku === '' || preg_match('/^B\d+-P\d+$/', $sku)) {
            [$outcome, $message] = ['failed', 'No barcode to create it under.'];
        } elseif ($hq->productForSku($sku)) {
            [$outcome, $message] = ['existed', null];
        } else {
            try {
                $this->createFromHq([
                    'barcode' => $sku,
                    'name' => (string) $p['name'],
                    'category' => (string) ($p['category'] ?? ''),
                    'description' => $p['description'] ?? null,
                    'price' => (float) ($p['price'] ?? 0),
                    'serial_tracked' => (bool) ($p['serial_tracked'] ?? false),
                ], null);
                [$outcome, $message] = ['created', null];
                $this->info("Product {$sku}: created from HQ.");
            } catch (\Throwable $e) {
                Log::warning("[StockHq] product {$sku} from HQ not created: ".$e->getMessage());
                [$outcome, $message] = ['failed', mb_substr($e->getMessage(), 0, 450)];
            }
        }

        $hq->productPushApplied((int) $p['id'], $outcome, $message);
    }

    /**
     * Apply an HQ edit to the product with that barcode: name, plus category,
     * description and price when HQ sent them (price only when HQ set it for
     * every outlet). Not muted, so the catalogue push brings HQ this outlet's
     * values.
     *
     * @param  array<string, mixed>  $e
     */
    private function applyProductEdit(StockHq $hq, array $e): void
    {
        $sku = trim((string) $e['sku']);
        $fields = (array) ($e['fields'] ?? []);
        $product = $sku !== '' ? $hq->productForSku($sku) : null;

        if (! $product) {
            [$outcome, $message] = ['missing', null];
        } else {
            try {
                $changes = [];
                if (filled($fields['name'] ?? null)) {
                    $changes['name'] = (string) $fields['name'];
                }
                if (filled($fields['category'] ?? null)) {
                    $changes['category_id'] = $this->categoryFor((string) $fields['category'])->id;
                }
                if (array_key_exists('description', $fields)) {
                    $changes['description'] = $fields['description'];
                }
                if (isset($fields['price']) && is_numeric($fields['price'])) {
                    $changes['price'] = (float) $fields['price'];
                }
                $product->update($changes);
                [$outcome, $message] = ['applied', null];
                $this->info("Product {$sku}: HQ edit applied.");
            } catch (\Throwable $ex) {
                Log::warning("[StockHq] HQ edit of {$sku} not applied: ".$ex->getMessage());
                [$outcome, $message] = ['failed', mb_substr($ex->getMessage(), 0, 450)];
            }
        }

        $hq->productEditApplied((int) $e['id'], $outcome, $message);
    }

    /** A category by name, any case; created when this outlet has none by that name. */
    private function categoryFor(string $name): Category
    {
        $name = trim($name) ?: 'Uncategorized';

        return Category::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? Category::create(['name' => $name, 'status' => 'active']);
    }

    /**
     * Carry out an HQ approve/reject on a serial-deletion request, then tell HQ
     * what actually happened. Idempotent: re-running after a lost confirmation
     * reports the same outcome without touching the serial again. Only acts on
     * the exact request HQ decided — if the outlet already decided it, or a new
     * request was raised since, the outlet's state is reported as-is.
     *
     * @param  array<string, mixed>  $d
     */
    private function applySerialDeletionDecision(StockHq $hq, ProductSerialService $serials, array $d): void
    {
        $serial = ProductSerial::withTrashed()->find($d['pos_serial_id']);
        $sameRequest = $serial && $serial->deletion_requested_at
            && $serial->deletion_requested_at->timestamp === Carbon::parse($d['requested_at'])->timestamp;

        [$outcome, $message] = match (true) {
            ! $serial => ['void', 'Serial no longer exists at the outlet.'],
            $serial->trashed() => ['approved', null],                                  // already deleted
            ! $sameRequest && $serial->status !== 'available' => ['void', 'Unit was sold before the decision.'],
            ! $sameRequest => ['rejected', 'Request was no longer pending at the outlet.'],
            default => [null, null],
        };

        if ($outcome === null) {
            if ($d['decision'] === 'approved') {
                try {
                    $serials->approveDeletion($serial);   // deletes → HQ gets the -1 write-off as usual
                    [$outcome, $message] = ['approved', null];
                } catch (\RuntimeException $e) {
                    [$outcome, $message] = ['void', 'Unit was sold before the decision.'];
                }
            } else {
                $serials->rejectDeletion($serial);
                [$outcome, $message] = ['rejected', null];
            }
            $this->info("Serial {$serial->serial_number}: HQ {$d['decision']} → {$outcome}");
        }

        $hq->serialDeletionApplied((int) $d['id'], $outcome, $message);
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
                // A receipt (new stock from HQ, no source outlet) may introduce
                // products this outlet doesn't have yet.
                $fromHq = ($transfer['branch_from'] ?? null) === 'HQ';
                $resolved = $this->resolveAndValidate($hq, $leg, $items, $fromHq);
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
     * @return array<int, array{product:?Product, create:?array<string,mixed>, qty:int, serials:array<int,string>, unit_cost:?float}>|string
     */
    private function resolveAndValidate(StockHq $hq, string $leg, array $items, bool $fromHq = false): array|string
    {
        $resolved = [];

        foreach ($items as $item) {
            $sku = (string) $item['product_sku'];
            $qty = (int) $item['quantity'];
            $reqSerials = array_values(array_filter(array_map('strval', $item['serial_numbers'] ?? []), fn ($s) => $s !== ''));

            $product = $hq->productForSku($sku);
            $create = null;
            if (! $product) {
                // Only a barcoded product from an HQ receipt, with its details.
                $details = $fromHq && $leg === 'add' && ! preg_match('/^B\d+-P\d+$/', $sku) ? ($item['product'] ?? null) : null;
                if (! is_array($details) || blank($details['name'] ?? null)) {
                    return "This branch has no product for SKU {$sku}.";
                }
                $create = [
                    'barcode' => $sku,
                    'name' => (string) $details['name'],
                    'category' => (string) ($details['category'] ?? ''),
                    'description' => $details['description'] ?? null,
                    'price' => (float) ($details['price'] ?? 0),
                    'serial_tracked' => (bool) ($details['serial_tracked'] ?? false),
                ];
            }

            if ($product ? $product->serial_tracked : $create['serial_tracked']) {
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

            // HQ stock receipts may carry a unit cost to set at this outlet.
            $unitCost = isset($item['unit_cost']) && is_numeric($item['unit_cost']) ? (float) $item['unit_cost'] : null;

            $resolved[] = ['product' => $product, 'create' => $create, 'qty' => $qty, 'serials' => $reqSerials, 'unit_cost' => $unitCost];
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
     * @param  array{product:?Product, create:?array<string,mixed>, qty:int, serials:array<int,string>, unit_cost:?float}  $r
     */
    private function applyAdd(ProductSerialService $serials, array $r): void
    {
        $product = $r['product'] ?? $this->createFromHq($r['create'], $r['unit_cost']);

        // A receipt from HQ sets this outlet's cost (HQ mirrors it on confirm).
        if ($r['unit_cost'] !== null) {
            $product->forceFill(['cost_price' => $r['unit_cost']])->save();
        }

        if ($product->serial_tracked) {
            // Receipt: create the named units without touching pending_serial_count.
            $serials->addSerials($product, $r['serials'], adjustPending: false);

            return;
        }

        $product->increment('stock', $r['qty']);
    }

    /**
     * Create a product HQ is sending that this outlet doesn't stock yet, from
     * HQ's details. Its category is matched by name (created if missing). A
     * product deleted here earlier still holds the barcode, so it's restored.
     *
     * @param  array{barcode:string, name:string, category:string, description:?string, price:float, serial_tracked:bool}  $d
     */
    private function createFromHq(array $d, ?float $cost): Product
    {
        $trashed = Product::onlyTrashed()->where('barcode', $d['barcode'])->first();
        if ($trashed) {
            $trashed->restore();
            if (! $trashed->serial_tracked) {
                $trashed->forceFill(['stock' => 0])->save();   // whatever it held when deleted is gone
            }

            return $trashed->refresh();
        }

        $category = $this->categoryFor($d['category']);

        return Product::create([
            'name' => $d['name'],
            'description' => $d['description'] ?? null,
            'barcode' => $d['barcode'],
            'category_id' => $category->id,
            'price' => $d['price'],
            'cost_price' => $cost ?? 0,
            'stock' => 0,
            'status' => 'active',
            'serial_tracked' => $d['serial_tracked'],
        ]);
    }
}
