<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSerial;

class ProductSerialService
{
    public function syncStock(Product $product): void
    {
        $count = $product->serials()->where('status', 'available')->count();
        $product->forceFill(['stock' => $count])->save();
    }

    public function addSerials(Product $product, array $serialNumbers): void
    {
        foreach ($serialNumbers as $serial) {
            $product->serials()->create([
                'serial_number' => $serial,
                'status' => 'available',
            ]);
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

    public function allocate(OrderItem $orderItem, Product $product, array $serialNumbers): int
    {
        $serialNumbers = array_values(array_unique($serialNumbers));

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
