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

it('creates an order for a tracked product with quantity derived from serials', function () {
    makeShopSettings();
    $user = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true, 'price' => 100, 'cost_price' => 40]);
    app(ProductSerialService::class)->addSerials($product, ['SN-1', 'SN-2', 'SN-3']);

    $this->actingAs($user)->postJson(route('orders.store'), [
        'items' => [[
            'id' => $product->id, 'quantity' => 999, 'price' => 100,
            'serials' => ['SN-1', 'SN-2'],
        ]],
        'subtotal' => 200, 'tax' => 0, 'delivery_cost' => 0, 'total' => 200,
        'paid_amount' => 200, 'due_amount' => 0, 'change_amount' => 0, 'discount' => 0,
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
    ])->assertJson(['success' => true]);

    $product->refresh();
    expect($product->stock)->toBe(1);
    expect($product->serials()->where('status', 'sold')->count())->toBe(2);

    $item = \App\Models\OrderItem::where('product_id', $product->id)->first();
    expect($item->quantity)->toBe(2); // derived from serials, not the client's 999

    $order = \App\Models\Order::latest('id')->first();
    expect((float) $order->profit)->toBe(120.0); // (100-40)*2, not inflated by client quantity 999
});

it('rolls back an order when a requested serial is unavailable', function () {
    makeShopSettings();
    $user = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true, 'price' => 100]);
    app(ProductSerialService::class)->addSerials($product, ['SN-1']);

    $this->actingAs($user)->postJson(route('orders.store'), [
        'items' => [[
            'id' => $product->id, 'quantity' => 2, 'price' => 100,
            'serials' => ['SN-1', 'SN-GHOST'],
        ]],
        'subtotal' => 200, 'tax' => 0, 'delivery_cost' => 0, 'total' => 200,
        'paid_amount' => 200, 'due_amount' => 0, 'change_amount' => 0, 'discount' => 0,
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
    ])->assertStatus(422);

    expect($product->fresh()->stock)->toBe(1); // unchanged, no partial write
    expect(\App\Models\Order::count())->toBe(0);
});

it('handles a mixed order with tracked and untracked products', function () {
    makeShopSettings();
    $user = serialAdmin();
    $tracked = Product::factory()->create(['serial_tracked' => true, 'price' => 100]);
    $untracked = Product::factory()->create(['serial_tracked' => false, 'stock' => 10, 'price' => 20]);
    app(ProductSerialService::class)->addSerials($tracked, ['SN-1', 'SN-2']);

    $this->actingAs($user)->postJson(route('orders.store'), [
        'items' => [
            ['id' => $tracked->id, 'quantity' => 1, 'price' => 100, 'serials' => ['SN-1']],
            ['id' => $untracked->id, 'quantity' => 3, 'price' => 20],
        ],
        'subtotal' => 160, 'tax' => 0, 'delivery_cost' => 0, 'total' => 160,
        'paid_amount' => 160, 'due_amount' => 0, 'change_amount' => 0, 'discount' => 0,
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
    ])->assertJson(['success' => true]);

    expect($tracked->fresh()->stock)->toBe(1);
    expect($untracked->fresh()->stock)->toBe(7);
});

it('releases old serials and allocates new ones when an order is edited', function () {
    makeShopSettings();
    $user = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true, 'price' => 100, 'cost_price' => 0]);
    app(ProductSerialService::class)->addSerials($product, ['SN-1', 'SN-2', 'SN-3']);

    // Create with SN-1, SN-2
    $this->actingAs($user)->postJson(route('orders.store'), [
        'items' => [['id' => $product->id, 'quantity' => 2, 'price' => 100, 'serials' => ['SN-1', 'SN-2']]],
        'subtotal' => 200, 'tax' => 0, 'delivery_cost' => 0, 'total' => 200,
        'paid_amount' => 200, 'due_amount' => 0, 'change_amount' => 0, 'discount' => 0,
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
    ])->assertJson(['success' => true]);

    $order = \App\Models\Order::latest('id')->first();

    // Edit to SN-3 only
    $this->actingAs($user)->put(route('orders.update', $order), [
        'items' => [[
            'product_id' => $product->id, 'product_name' => $product->name,
            'quantity' => 1, 'price' => 100, 'total' => 100, 'serials' => ['SN-3'],
        ]],
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
        'delivery_cost' => 0, 'paid_amount' => 100, 'due_amount' => 0,
        'change_amount' => 0, 'discount' => 0,
    ])->assertRedirect();

    $product->refresh();
    expect($product->stock)->toBe(2); // SN-1, SN-2 back to available
    expect($product->serials()->where('serial_number', 'SN-3')->first()->status)->toBe('sold');
    expect($product->serials()->where('status', 'sold')->count())->toBe(1);
});
