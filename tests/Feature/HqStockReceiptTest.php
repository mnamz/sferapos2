<?php

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

function fakeHqReceipt(array $items): void
{
    Http::fake([
        'hq.test/api/stock/transfers/pending*' => Http::response(['pending' => [[
            'id' => 77,
            'transfer_number' => 'RCV-000077',
            'action' => 'add',
            'branch_from' => 'HQ',
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
