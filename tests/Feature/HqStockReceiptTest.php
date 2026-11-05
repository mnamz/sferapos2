<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

// An HQ stock receipt is a transfer with no source outlet ("HQ") that this
// outlet only adds. A unit cost on the line becomes the product's cost price.

beforeEach(function () {
    config([
        'stockhq.enabled' => true,
        'stockhq.url' => 'https://hq.test',
        'stockhq.token' => 'token',
        'stockhq.branch_id' => 2,
        'stockhq.transfers_enabled' => true,
    ]);
});

function fakeHqReceipt(array $items, string $from = 'HQ'): void
{
    Http::fake([
        'hq.test/api/stock/transfers/pending*' => Http::response(['pending' => [[
            'id' => 77,
            'transfer_number' => 'RCV-000077',
            'action' => 'add',
            'branch_from' => $from,
            'branch_to' => 'KL',
            'items' => $items,
        ]]]),
        'hq.test/api/stock/transfers/77/claim' => Http::response(['status' => 'ok']),
        'hq.test/api/stock/transfers/77/confirm' => Http::response(['status' => 'ok']),
        'hq.test/api/stock/serial-deletions/decisions' => Http::response(['decisions' => []]),
        '*' => Http::response(['status' => 'ok']),
    ]);
}

it('adds received stock and sets the unit cost from HQ', function () {
    $product = Product::factory()->create(['barcode' => '6970357854486', 'stock' => 2, 'cost_price' => 80, 'serial_tracked' => false]);
    fakeHqReceipt([[
        'product_sku' => '6970357854486', 'product_name' => 'Mini Remote', 'quantity' => 10,
        'serial_numbers' => [], 'unit_cost' => 72.5,
    ]]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    $product->refresh();
    expect($product->stock)->toBe(12);
    expect((float) $product->cost_price)->toBe(72.5);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/transfers/77/confirm') && $r['action'] === 'added');
});

it('leaves the cost alone when the receipt has none', function () {
    $product = Product::factory()->create(['barcode' => '6970357854486', 'stock' => 0, 'cost_price' => 80, 'serial_tracked' => false]);
    fakeHqReceipt([[
        'product_sku' => '6970357854486', 'product_name' => 'Mini Remote', 'quantity' => 3,
        'serial_numbers' => [], 'unit_cost' => null,
    ]]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    $product->refresh();
    expect($product->stock)->toBe(3);
    expect((float) $product->cost_price)->toBe(80.0);
});

it('creates the received serials for a serial-tracked product', function () {
    $product = Product::factory()->create(['barcode' => '6942590202638', 'stock' => 0, 'serial_tracked' => true]);
    fakeHqReceipt([[
        'product_sku' => '6942590202638', 'product_name' => 'Luna Pro', 'quantity' => 2,
        'serial_numbers' => ['SN-A', 'SN-B'], 'unit_cost' => 1500,
    ]]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    $product->refresh();
    expect($product->stock)->toBe(2);
    expect($product->serials()->pluck('serial_number')->sort()->values()->all())->toBe(['SN-A', 'SN-B']);
    expect((float) $product->cost_price)->toBe(1500.0);
});

function newProductLine(array $overrides = []): array
{
    return array_merge([
        'product_sku' => '6977644768273', 'product_name' => 'Throwpod Remote Kit', 'quantity' => 4,
        'serial_numbers' => [], 'unit_cost' => 95,
        'product' => ['name' => 'Throwpod Remote Kit', 'category' => 'insta360 accessories', 'price' => 149, 'serial_tracked' => false],
    ], $overrides);
}

it('creates a product it does not stock yet, in the matching category', function () {
    $category = Category::factory()->create(['name' => 'Insta360 Accessories']);
    fakeHqReceipt([newProductLine()]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    $product = Product::where('barcode', '6977644768273')->firstOrFail();
    expect($product->name)->toBe('Throwpod Remote Kit');
    expect($product->category_id)->toBe($category->id);   // matched case-insensitively
    expect((float) $product->price)->toBe(149.0);
    expect((float) $product->cost_price)->toBe(95.0);
    expect($product->stock)->toBe(4);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/transfers/77/confirm'));
});

it('creates the category when the outlet has none by that name', function () {
    fakeHqReceipt([newProductLine(['product' => ['name' => 'Kit', 'category' => '', 'price' => 10, 'serial_tracked' => false]])]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    expect(Product::where('barcode', '6977644768273')->first()->category->name)->toBe('Uncategorized');
});

it('creates a serial-tracked product with the received serials', function () {
    fakeHqReceipt([newProductLine([
        'quantity' => 2, 'serial_numbers' => ['LUNA-1', 'LUNA-2'],
        'product' => ['name' => 'Luna Pro', 'category' => 'Cameras', 'price' => 2999, 'serial_tracked' => true],
    ])]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    $product = Product::where('barcode', '6977644768273')->firstOrFail();
    expect($product->serial_tracked)->toBeTrue();
    expect($product->stock)->toBe(2);
    expect($product->serials()->count())->toBe(2);
});

it('restores a product deleted here that still holds the barcode', function () {
    $old = Product::factory()->create(['barcode' => '6977644768273', 'stock' => 7, 'serial_tracked' => false]);
    $old->delete();
    fakeHqReceipt([newProductLine()]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    $old->refresh();
    expect($old->trashed())->toBeFalse();
    expect($old->stock)->toBe(4);   // stale count dropped, then the 4 received
    expect(Product::withTrashed()->where('barcode', '6977644768273')->count())->toBe(1);
});

it('does not create products for a transfer from another outlet', function () {
    fakeHqReceipt([newProductLine()], from: 'SC');

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    expect(Product::where('barcode', '6977644768273')->exists())->toBeFalse();
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/transfers/77/reject'));
});
