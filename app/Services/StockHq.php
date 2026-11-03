<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSerial;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the central Stock HQ dashboard. Pushes this branch's product
 * catalogue and sales to HQ's /api/stock endpoints, authenticated with the
 * branch's bearer token.
 */
class StockHq
{
    public function enabled(): bool
    {
        return (bool) config('stockhq.enabled')
            && filled(config('stockhq.url'))
            && filled(config('stockhq.token'))
            && filled(config('stockhq.branch_id'));
    }

    public function branchId(): int
    {
        return (int) config('stockhq.branch_id');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('stockhq.url'), '/').'/api/stock')
            ->withToken((string) config('stockhq.token'))
            ->acceptJson()
            ->timeout((int) config('stockhq.timeout', 15));
    }

    /**
     * A stable, cross-branch SKU: the barcode when present, otherwise a
     * branch-scoped id so barcode-less products from different branches don't
     * collide into one HQ product.
     */
    public function skuFor(Product $product): string
    {
        $barcode = trim((string) $product->barcode);

        return $barcode !== '' ? $barcode : $this->placeholderSku($product);
    }

    /** The branch-scoped SKU a product has while it has no barcode. */
    public function placeholderSku(Product $product): string
    {
        return 'B'.$this->branchId().'-P'.$product->id;
    }

    /**
     * Upsert a set of products into HQ's catalogue.
     *
     * @param  iterable<Product>  $products
     */
    public function syncProducts(iterable $products): void
    {
        $payload = collect($products)->filter()->unique('id')->map(fn (Product $p) => [
            'sku' => $this->skuFor($p),
            // Once a product has a barcode, also name the placeholder it was
            // known by before, so HQ folds any old placeholder product into
            // this one instead of keeping a duplicate.
            'legacy_sku' => $this->skuFor($p) !== $this->placeholderSku($p) ? $this->placeholderSku($p) : null,
            'name' => $p->name,
            'category' => optional($p->category)->name,
            'unit' => 'pcs',
            'price' => (float) $p->price,
            // This outlet's cost for the item (null when not filled in), so HQ
            // can show it on transfers and flag products with no cost.
            'cost_price' => (float) $p->cost_price > 0 ? (float) $p->cost_price : null,
            'is_active' => filter_var($p->status, FILTER_VALIDATE_BOOLEAN) || $p->status === 'active',
            'serial_tracked' => (bool) ($p->serial_tracked ?? false),
        ])->values()->all();

        if (empty($payload)) {
            return;
        }

        $this->http()->post('/products', ['products' => $payload])->throw();
    }

    /**
     * Snapshot current on-hand (and in-stock serials) to HQ. HQ reconciles each
     * against its own ledger, so this is idempotent and safe to run repeatedly.
     * $snapshotId is one token per run (shared across chunks) so HQ dedupes a
     * retried delivery but still applies a genuinely new run.
     *
     * @param  iterable<Product>  $products
     * @return array<string, mixed>  HQ's reconciliation summary (empty if nothing sent)
     */
    public function pushLevels(iterable $products, string $snapshotId): array
    {
        $levels = collect($products)->filter()->unique('id')->map(function (Product $p) {
            $row = [
                'sku' => $this->skuFor($p),
                'on_hand' => (int) $p->stock,
            ];

            if ($p->serial_tracked) {
                $serials = $p->relationLoaded('serials')
                    ? $p->serials
                    : $p->serials()->where('status', 'available')->get();

                $row['serial_numbers'] = $serials
                    ->where('status', 'available')
                    ->pluck('serial_number')
                    ->filter()
                    ->values()
                    ->all();
            }

            return $row;
        })->values()->all();

        if (empty($levels)) {
            return [];
        }

        return $this->http()->post('/levels/snapshot', [
            'snapshot_id' => $snapshotId,
            'levels' => $levels,
        ])->throw()->json() ?? [];
    }

    /**
     * Resolve the local product for an HQ sku (the reverse of skuFor): HQ's sku
     * is this branch's barcode, or "B{branchId}-P{id}" for barcode-less products.
     * Cross-store transfers therefore resolve only for barcoded products (the
     * "B{id}-P" form is scoped to the branch that coined it).
     */
    public function productForSku(string $sku): ?Product
    {
        // A branch-scoped id (barcode-less product) resolves ONLY at the branch
        // that coined it; a foreign one is undeliverable here and must NOT fall
        // through to a barcode lookup (which could hit an unrelated product).
        if (preg_match('/^B(\d+)-P(\d+)$/', $sku, $m)) {
            return (int) $m[1] === $this->branchId() ? Product::find((int) $m[2]) : null;
        }

        return Product::where('barcode', $sku)->first();
    }

    /**
     * Destination claims the add leg (deducted -> adding) at HQ before applying
     * it locally, so a concurrent rollback cannot fire mid-add. Returns true
     * only if HQ granted the claim (safe to proceed).
     */
    public function claimTransfer(int $transferId): bool
    {
        return $this->http()->post("/transfers/{$transferId}/claim", [
            'branch_id' => $this->branchId(),
        ])->successful();
    }

    /**
     * Transfer commands awaiting this branch (deduct as source / add as dest).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingTransfers(): array
    {
        return $this->http()->get('/transfers/pending', ['branch_id' => $this->branchId()])
            ->throw()->json('pending') ?? [];
    }

    /**
     * Confirm a completed leg back to HQ.
     *
     * @param  'deducted'|'added'  $action
     * @param  array<int, array{product_sku:string, quantity_confirmed:int}>  $items
     */
    public function confirmTransfer(int $transferId, string $action, array $items): void
    {
        $this->http()->post("/transfers/{$transferId}/confirm", [
            'branch_id' => $this->branchId(),
            'action' => $action,
            'items' => $items,
        ])->throw();
    }

    /**
     * Reject a leg this branch cannot fulfil (e.g. the product isn't in its
     * catalogue). HQ moves the transfer to a resting/failed state HQ can act on.
     */
    public function rejectTransfer(int $transferId, string $reason): void
    {
        $this->http()->post("/transfers/{$transferId}/reject", [
            'branch_id' => $this->branchId(),
            'reason' => $reason,
        ])->throw();
    }

    /**
     * Push a batch of stock events to HQ.
     *
     * @param  array<int, array<string, mixed>>  $events
     */
    public function pushEvents(array $events): void
    {
        if (empty($events)) {
            return;
        }

        $this->http()->post('/events/batch', ['events' => $events])->throw();
    }

    /**
     * The reference HQ files this order's sale lines under — the order header
     * must use the same value so HQ can join the two.
     */
    public function referenceFor(Order $order): string
    {
        return $order->invoice_number ?: ($order->order_number ?: ('ORD-'.$order->id));
    }

    /**
     * HQ order header for an order: customer, money, payment and status, as
     * the POS order list shows them. Re-sent on every change; HQ keeps the
     * newest by updated_at.
     *
     * @return array<string, mixed>
     */
    public function orderHeader(Order $order): array
    {
        return [
            'pos_order_id' => $order->id,
            'reference' => $this->referenceFor($order),
            'customer_name' => optional($order->customer)->name,
            'subtotal' => (float) $order->subtotal,
            'discount' => (float) $order->discount,
            'tax' => (float) $order->tax,
            'delivery_cost' => (float) $order->delivery_cost,
            'total' => (float) $order->total,
            'profit' => (float) $order->profit,
            'paid_amount' => (float) $order->paid_amount,
            'due_amount' => (float) $order->due_amount,
            'payment_method' => $order->payment_method,
            'delivery_method' => $order->delivery_method,
            'status' => $order->status,
            'occurred_at' => optional($order->created_at)->toIso8601String(),
            'updated_at' => optional($order->updated_at)->toIso8601String(),
            'deleted_at' => optional($order->deleted_at)->toIso8601String(),
        ];
    }

    /**
     * Push a batch of order headers to HQ (max 500 per call).
     *
     * @param  array<int, array<string, mixed>>  $headers
     */
    public function pushOrderHeaders(array $headers): void
    {
        foreach (array_chunk($headers, 500) as $chunk) {
            $this->http()->post('/orders', ['orders' => $chunk])->throw();
        }
    }

    /**
     * A serial-deletion request as HQ records it. `state` is pending for a new
     * request, or what the outlet decided (approved / rejected / void when the
     * unit sold first). Build it BEFORE approving/rejecting, while the request
     * fields are still on the serial.
     *
     * @return array<string, mixed>
     */
    public function serialDeletionPayload(ProductSerial $serial, string $state, ?string $decidedBy = null): array
    {
        $serial->loadMissing(['product.category', 'deletionRequester:id,name']);

        return [
            'pos_serial_id' => $serial->id,
            'requested_at' => optional($serial->deletion_requested_at)->toIso8601String(),
            'serial_number' => $serial->serial_number,
            'product_sku' => $serial->product ? $this->skuFor($serial->product) : null,
            'product_name' => optional($serial->product)->name,
            'reason' => $serial->deletion_reason,
            'requested_by' => optional($serial->deletionRequester)->name,
            'state' => $state,
            'decided_by' => $decidedBy,
            'decided_at' => $state === 'pending' ? null : now()->toIso8601String(),
        ];
    }

    /** @param  array<int, array<string, mixed>>  $requests */
    public function pushSerialDeletions(array $requests): void
    {
        foreach (array_chunk($requests, 500) as $chunk) {
            $this->http()->post('/serial-deletions', ['requests' => $chunk])->throw();
        }
    }

    /**
     * HQ approve/reject decisions this outlet hasn't carried out yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function serialDeletionDecisions(): array
    {
        return $this->http()->get('/serial-deletions/decisions')->throw()->json('decisions') ?? [];
    }

    /** Report what actually happened to the serial after applying an HQ decision. */
    public function serialDeletionApplied(int $id, string $outcome, ?string $message = null): void
    {
        $this->http()->post("/serial-deletions/{$id}/applied", ['outcome' => $outcome, 'message' => $message])->throw();
    }

    /**
     * Push one serial's arrival ('in' → purchase, lands in_stock at this branch)
     * or removal ('out' → negative adjustment, written off) to HQ. Idempotent:
     * the event_id is deterministic per serial + direction, so a retry is a
     * no-op at HQ.
     */
    public function pushSerialEvent(ProductSerial $serial, string $direction): void
    {
        $product = $serial->product;
        if (! $product || ! $product->serial_tracked) {
            return;
        }

        $branch = $this->branchId();
        $base = [
            'branch_id' => $branch,
            'product_sku' => $this->skuFor($product),
            'created_by' => 'observer',
            'serial_numbers' => [$serial->serial_number],
        ];

        if ($direction === 'in') {
            $event = $base + [
                'event_id' => 'B'.$branch.'-serialin-'.$serial->id,
                'type' => 'purchase',
                'quantity' => 1,
                'reference' => 'serial-receipt',
                'occurred_at' => optional($serial->created_at)->toIso8601String(),
            ];
        } else {
            $event = $base + [
                'event_id' => 'B'.$branch.'-serialout-'.$serial->id,
                'type' => 'adjustment',
                'quantity' => -1,
                'reference' => 'serial-removal',
                'occurred_at' => optional($serial->updated_at)->toIso8601String(),
            ];
        }

        $this->pushEvents([$event]);
    }

    /**
     * Build HQ sale events (one per line item) for an order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function saleEventsForOrder(Order $order): array
    {
        $branch = $this->branchId();
        $reference = $this->referenceFor($order);
        $cashier = optional($order->user)->name ?? ('user#'.$order->user_id);

        $events = [];
        foreach ($order->items as $item) {
            $product = $item->product;
            $sku = $product ? $this->skuFor($product) : ('B'.$branch.'-P'.$item->product_id);

            $event = [
                'event_id' => 'B'.$branch.'-O'.$order->id.'-I'.$item->id,
                'branch_id' => $branch,
                'product_sku' => $sku,
                'type' => 'sale',
                'quantity' => (int) $item->quantity,
                'reference' => $reference,
                'created_by' => $cashier,
                'occurred_at' => optional($order->created_at)->toIso8601String(),
            ];

            // Only send serials when they fully cover the quantity (HQ requires
            // serial count == quantity for serial-tracked lines).
            $serials = $item->serials->pluck('serial_number')->filter()->values()->all();
            if (! empty($serials) && count($serials) === (int) $item->quantity) {
                $event['serial_numbers'] = $serials;
            }

            $events[] = $event;
        }

        return $events;
    }
}
