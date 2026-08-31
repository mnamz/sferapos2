<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
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
