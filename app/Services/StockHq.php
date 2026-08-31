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

        return $barcode !== '' ? $barcode : 'B'.$this->branchId().'-P'.$product->id;
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
            'name' => $p->name,
            'category' => optional($p->category)->name,
            'unit' => 'pcs',
            'price' => (float) $p->price,
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
        $reference = $order->invoice_number ?: ($order->order_number ?: ('ORD-'.$order->id));
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
