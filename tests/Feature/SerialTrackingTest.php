<?php

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\User;
use App\Services\ProductSerialService;

function serialAdmin(): User
{
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

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

it('bulk-adds serials via the endpoint and syncs stock', function () {
    $admin = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true, 'stock' => 0]);

    $this->actingAs($admin)
        ->post(route('products.serials.store', $product), ['serials' => ['SN-1', 'SN-2', 'SN-3']])
        ->assertRedirect();

    expect($product->fresh()->stock)->toBe(3);
});

it('rejects a globally-duplicate serial on add', function () {
    $admin = serialAdmin();
    $p1 = Product::factory()->create(['serial_tracked' => true]);
    $p2 = Product::factory()->create(['serial_tracked' => true]);
    ProductSerial::create(['product_id' => $p1->id, 'serial_number' => 'DUP', 'status' => 'available']);

    $this->actingAs($admin)
        ->post(route('products.serials.store', $p2), ['serials' => ['DUP']])
        ->assertSessionHasErrors('serials.0');

    expect($p2->fresh()->stock)->toBe(0);
});

it('rejects duplicate serials within the same request', function () {
    $admin = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true]);

    $this->actingAs($admin)
        ->post(route('products.serials.store', $product), ['serials' => ['SAME', 'SAME']])
        ->assertSessionHasErrors();

    expect($product->fresh()->stock)->toBe(0);
});

it('removes an available serial via the endpoint', function () {
    $admin = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true]);
    app(ProductSerialService::class)->addSerials($product, ['SN-1', 'SN-2']);
    $serial = $product->serials()->where('serial_number', 'SN-1')->first();

    $this->actingAs($admin)
        ->delete(route('products.serials.destroy', [$product, $serial]))
        ->assertRedirect();

    expect($product->fresh()->stock)->toBe(1);
});

it('rejects integer stock adjustment for a serial-tracked product', function () {
    $admin = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true]);

    $this->actingAs($admin)
        ->post(route('products.adjust-stock', $product), ['quantity' => 5, 'type' => 'restock'])
        ->assertSessionHas('error');
});

it('forces stock to zero when creating a serial-tracked product', function () {
    $admin = serialAdmin();
    $category = \App\Models\Category::factory()->create();

    $this->actingAs($admin)->post(route('products.store'), [
        'name' => 'Tracked Device', 'price' => 100, 'cost_price' => 50,
        'stock' => 99, 'category_id' => $category->id, 'status' => 'active',
        'serial_tracked' => true,
    ])->assertRedirect();

    expect(Product::where('name', 'Tracked Device')->first()->stock)->toBe(0);
});

it('returns 404 when removing a serial that belongs to a different product', function () {
    $admin = serialAdmin();
    $p1 = Product::factory()->create(['serial_tracked' => true]);
    $p2 = Product::factory()->create(['serial_tracked' => true]);
    app(ProductSerialService::class)->addSerials($p1, ['SN-1']);
    $serial = $p1->serials()->first();

    $this->actingAs($admin)
        ->delete(route('products.serials.destroy', [$p2, $serial]))
        ->assertNotFound();

    expect($p1->fresh()->stock)->toBe(1); // untouched
});

it('refuses to remove a serial that is already sold', function () {
    $admin = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true]);
    $order = makeOrder();
    app(ProductSerialService::class)->addSerials($product, ['SN-1']);
    $item = \App\Models\OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
        'quantity' => 1, 'price' => 10, 'cost_price' => 0, 'total' => 10, 'profit' => 10,
    ]);
    app(ProductSerialService::class)->allocate($item, $product, ['SN-1']);
    $serial = $product->serials()->where('status', 'sold')->first();

    $this->actingAs($admin)
        ->delete(route('products.serials.destroy', [$product, $serial]))
        ->assertSessionHas('error');

    expect($product->serials()->where('status', 'sold')->count())->toBe(1); // still sold
});

it('blocks enabling serial tracking on a product that still has stock', function () {
    $admin = serialAdmin();
    $category = \App\Models\Category::factory()->create();
    $product = Product::factory()->create(['serial_tracked' => false, 'stock' => 5, 'category_id' => $category->id]);

    $this->actingAs($admin)->put(route('products.update', $product), [
        'name' => $product->name, 'price' => 100, 'cost_price' => 50,
        'stock' => 5, 'category_id' => $category->id, 'status' => 'active',
        'serial_tracked' => true,
    ])->assertSessionHas('error');

    expect($product->fresh()->serial_tracked)->toBeFalse();
});
