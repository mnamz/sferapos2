<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSerial;
use Illuminate\Support\Facades\DB;

class ProductSerialService
{
    public function syncStock(Product $product): void
    {
        $count = $product->serials()->where('status', 'available')->count();
        $product->forceFill(['stock' => $count])->save();
    }

    public function addSerials(Product $product, array $serialNumbers, bool $adjustPending = true): void
    {
        foreach ($serialNumbers as $serial) {
            $product->serials()->create([
                'serial_number' => $serial,
                'status' => 'available',
            ]);
        }

        // Each keyed-in serial covers one unit that was awaiting assignment; tick
        // the pending reminder down (never below zero). Transfer receipts pass
        // $adjustPending=false — those units were never "awaiting entry" here.
        if ($adjustPending && $product->pending_serial_count > 0) {
            $product->pending_serial_count = max(0, $product->pending_serial_count - count($serialNumbers));
        }

        $this->syncStock($product);
    }

    public function removeSerial(ProductSerial $serial): void
    {
        if ($serial->status !== 'available') {
            throw new \RuntimeException('Only available serials can be removed.');
        }

        $product = $serial->product;
        $serial->delete();
        $this->syncStock($product);
    }

    // Manager-initiated deletion: flag the serial for admin approval instead of
    // removing it. The serial stays available/sellable until an admin approves.
    public function requestDeletion(ProductSerial $serial, int $userId): void
    {
        if ($serial->status !== 'available') {
            throw new \RuntimeException('Only available serials can be deleted.');
        }

        $serial->update([
            'deletion_requested_at' => now(),
            'deletion_requested_by' => $userId,
        ]);
    }

    // Admin approves a pending request → the serial is actually removed.
    public function approveDeletion(ProductSerial $serial): void
    {
        if ($serial->status !== 'available') {
            // It was sold before approval — void the stale request instead.
            $this->rejectDeletion($serial);
            throw new \RuntimeException('Serial is no longer available to delete.');
        }

        $this->removeSerial($serial);
    }

    // Admin rejects a pending request → clear the request, keep the serial.
    public function rejectDeletion(ProductSerial $serial): void
    {
        $serial->update([
            'deletion_requested_at' => null,
            'deletion_requested_by' => null,
        ]);
    }

    public function allocate(OrderItem $orderItem, Product $product, array $serialNumbers): int
    {
        $serialNumbers = array_values(array_unique($serialNumbers));

        return DB::transaction(function () use ($orderItem, $product, $serialNumbers) {
            $serials = $product->serials()
                ->where('status', 'available')
                ->whereIn('serial_number', $serialNumbers)
                ->lockForUpdate()
                ->get();

            if ($serials->count() !== count($serialNumbers)) {
                throw new \RuntimeException("Some serial numbers are not available for product: {$product->name}");
            }

            foreach ($serials as $serial) {
                $serial->update([
                    'status' => 'sold',
                    'order_id' => $orderItem->order_id,
                    'order_item_id' => $orderItem->id,
                ]);
            }

            $this->syncStock($product);

            return $serials->count();
        });
    }

    public function release(int $orderId): void
    {
        $serials = ProductSerial::where('order_id', $orderId)
            ->where('status', 'sold')
            ->get();

        $productIds = $serials->pluck('product_id')->unique();

        foreach ($serials as $serial) {
            $serial->update([
                'status' => 'available',
                'order_id' => null,
                'order_item_id' => null,
            ]);
        }

        foreach ($productIds as $productId) {
            if ($product = Product::find($productId)) {
                $this->syncStock($product);
            }
        }
    }
}
