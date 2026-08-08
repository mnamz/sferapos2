<?php

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Services\ProductSerialService;

it('relates serials to a product and scopes available ones', function () {
    $product = Product::factory()->create(['serial_tracked' => true]);

    ProductSerial::create(['product_id' => $product->id, 'serial_number' => 'SN-A', 'status' => 'available']);
    ProductSerial::create(['product_id' => $product->id, 'serial_number' => 'SN-B', 'status' => 'sold']);

    expect($product->serials()->count())->toBe(2);
    expect($product->serials()->available()->count())->toBe(1);
    expect(ProductSerial::first()->product->id)->toBe($product->id);
});

it('syncs product stock to the count of available serials', function () {
    $product = Product::factory()->create(['serial_tracked' => true, 'stock' => 0]);
    $service = app(ProductSerialService::class);

    $service->addSerials($product, ['SN-1', 'SN-2', 'SN-3']);

    expect($product->fresh()->stock)->toBe(3);
    expect($product->serials()->available()->count())->toBe(3);
});

it('removing an available serial recounts stock', function () {
    $product = Product::factory()->create(['serial_tracked' => true]);
    $service = app(ProductSerialService::class);
    $service->addSerials($product, ['SN-1', 'SN-2']);

    $serial = $product->serials()->where('serial_number', 'SN-1')->first();
    $service->removeSerial($serial);

    expect($product->fresh()->stock)->toBe(1);
    expect($product->serials()->count())->toBe(1);
});

it('allocates serials to an order item, marks them sold, and links the order', function () {
    $product = Product::factory()->create(['serial_tracked' => true]);
    $order = makeOrder();
    $service = app(ProductSerialService::class);
    $service->addSerials($product, ['SN-1', 'SN-2', 'SN-3']);

    $item = OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
        'quantity' => 2, 'price' => 10, 'cost_price' => 0, 'total' => 20, 'profit' => 20,
    ]);

    $count = $service->allocate($item, $product, ['SN-1', 'SN-2']);

    expect($count)->toBe(2);
    expect($product->fresh()->stock)->toBe(1);
    $sold = $product->serials()->where('status', 'sold')->get();
    expect($sold->pluck('serial_number')->sort()->values()->all())->toBe(['SN-1', 'SN-2']);
    expect($sold->every(fn ($s) => $s->order_id === $order->id && $s->order_item_id === $item->id))->toBeTrue();
});

it('throws when allocating a serial that is not available', function () {
    $product = Product::factory()->create(['serial_tracked' => true]);
    $order = makeOrder();
    $service = app(ProductSerialService::class);
    $service->addSerials($product, ['SN-1']);
    $item = OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
        'quantity' => 1, 'price' => 10, 'cost_price' => 0, 'total' => 10, 'profit' => 10,
    ]);

    $service->allocate($item, $product, ['SN-1', 'SN-DOES-NOT-EXIST']);
})->throws(\RuntimeException::class);

it('releases serials for an order back to the available pool', function () {
    $product = Product::factory()->create(['serial_tracked' => true]);
    $order = makeOrder();
    $service = app(ProductSerialService::class);
    $service->addSerials($product, ['SN-1', 'SN-2']);
    $item = OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
        'quantity' => 2, 'price' => 10, 'cost_price' => 0, 'total' => 20, 'profit' => 20,
    ]);
    $service->allocate($item, $product, ['SN-1', 'SN-2']);

    $service->release($order->id);

    expect($product->fresh()->stock)->toBe(2);
    expect($product->serials()->available()->count())->toBe(2);
    expect(ProductSerial::where('order_id', $order->id)->count())->toBe(0);
});
