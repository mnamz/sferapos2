<?php

use App\Jobs\PushProductToHq;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// A product made at HQ is sent to every outlet; the outlet creates it with no
// stock on its every-minute poll and reports what it did.

beforeEach(function () {
    config([
        'stockhq.enabled' => true,
        'stockhq.url' => 'https://hq.test',
        'stockhq.token' => 'token',
        'stockhq.branch_id' => 2,
        'stockhq.transfers_enabled' => true,
    ]);
});

function fakeHqProductPush(array $product): void
{
    Http::fake([
        'hq.test/api/stock/transfers/pending*' => Http::response(['pending' => []]),
        'hq.test/api/stock/serial-deletions/decisions' => Http::response(['decisions' => []]),
        'hq.test/api/stock/product-pushes' => Http::response(['products' => [$product + ['id' => 5]]]),
        '*' => Http::response(['status' => 'ok']),
    ]);
}

it('creates a product HQ sent, with no stock, in the matching category', function () {
    Queue::fake();
    $category = Category::create(['name' => 'Insta360 Accessories', 'status' => 'active']);
    fakeHqProductPush([
        'sku' => '6942590203505', 'name' => 'Insta360 Spin Stick', 'category' => 'INSTA360 ACCESSORIES',
        'description' => 'Compatible with Insta360 X6 and X5', 'price' => 369, 'serial_tracked' => false,
    ]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    $product = Product::where('barcode', '6942590203505')->firstOrFail();
    expect($product->name)->toBe('Insta360 Spin Stick');
    expect($product->category_id)->toBe($category->id);
    expect($product->description)->toBe('Compatible with Insta360 X6 and X5');
    expect((float) $product->price)->toBe(369.0);
    expect((int) $product->stock)->toBe(0);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/product-pushes/5/applied') && $r['outcome'] === 'created');
    // Not muted: the catalogue push tells HQ this outlet now carries it.
    Queue::assertPushed(PushProductToHq::class);
});

it('reports a barcode it already has without touching the product', function () {
    $existing = Product::factory()->create(['barcode' => '6942590203505', 'name' => 'Spin Stick (local)', 'stock' => 4]);
    fakeHqProductPush(['sku' => '6942590203505', 'name' => 'Insta360 Spin Stick', 'category' => null, 'price' => 369, 'serial_tracked' => false]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    expect($existing->refresh()->name)->toBe('Spin Stick (local)');
    expect((int) $existing->stock)->toBe(4);
    expect(Product::where('barcode', '6942590203505')->count())->toBe(1);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/product-pushes/5/applied') && $r['outcome'] === 'existed');
});

it('refuses a placeholder code', function () {
    fakeHqProductPush(['sku' => 'B1-P42', 'name' => 'No barcode', 'category' => null, 'price' => 10, 'serial_tracked' => false]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    expect(Product::where('name', 'No barcode')->exists())->toBeFalse();
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/product-pushes/5/applied') && $r['outcome'] === 'failed');
});

it('sends the description with the catalogue', function () {
    Http::fake(['*' => Http::response(['status' => 'ok'])]);
    $product = Product::factory()->create(['barcode' => '123', 'description' => 'Fits X5']);

    app(\App\Services\StockHq::class)->syncProducts([$product]);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/products') && $r['products'][0]['description'] === 'Fits X5');
});

it('creates the product at the cost price HQ sent', function () {
    Queue::fake();
    fakeHqProductPush([
        'sku' => '6942590203505', 'name' => 'Insta360 Spin Stick', 'category' => null,
        'price' => 369, 'cost_price' => 295.5, 'serial_tracked' => false,
    ]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    expect((float) Product::where('barcode', '6942590203505')->firstOrFail()->cost_price)->toBe(295.5);
});

it('starts the cost at 0 when HQ sent none', function () {
    Queue::fake();
    fakeHqProductPush(['sku' => '6942590203505', 'name' => 'Insta360 Spin Stick', 'category' => null, 'price' => 369, 'serial_tracked' => false]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    expect((float) Product::where('barcode', '6942590203505')->firstOrFail()->cost_price)->toBe(0.0);
});

it('keeps its own cost for a barcode it already has', function () {
    $existing = Product::factory()->create(['barcode' => '6942590203505', 'cost_price' => 280]);
    fakeHqProductPush(['sku' => '6942590203505', 'name' => 'Insta360 Spin Stick', 'category' => null, 'price' => 369, 'cost_price' => 295.5, 'serial_tracked' => false]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    expect((float) $existing->refresh()->cost_price)->toBe(280.0);
});
