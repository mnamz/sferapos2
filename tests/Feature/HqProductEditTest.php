<?php

use App\Jobs\PushProductToHq;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// An edit made at HQ reaches every outlet carrying the product. The price only
// comes along when HQ set it for all outlets; otherwise the shop keeps its own.

beforeEach(function () {
    config([
        'stockhq.enabled' => true,
        'stockhq.url' => 'https://hq.test',
        'stockhq.token' => 'token',
        'stockhq.branch_id' => 2,
        'stockhq.transfers_enabled' => true,
    ]);
});

function fakeHqProductEdit(string $sku, array $fields): void
{
    Http::fake([
        'hq.test/api/stock/transfers/pending*' => Http::response(['pending' => []]),
        'hq.test/api/stock/serial-deletions/decisions' => Http::response(['decisions' => []]),
        'hq.test/api/stock/product-pushes' => Http::response(['products' => []]),
        'hq.test/api/stock/product-edits' => Http::response(['edits' => [['id' => 9, 'sku' => $sku, 'fields' => $fields]]]),
        '*' => Http::response(['status' => 'ok']),
    ]);
}

it('applies name, category and description but keeps the outlet price', function () {
    Queue::fake();
    $sticks = Category::create(['name' => 'Insta360 Sticks & Grips', 'status' => 'active']);
    $product = Product::factory()->create(['barcode' => '6942590200948', 'name' => 'Spin Stick', 'price' => 89, 'stock' => 3]);
    fakeHqProductEdit('6942590200948', [
        'name' => 'Insta360 Spin Stick', 'category' => 'INSTA360 STICKS & GRIPS', 'description' => 'Fits X5',
    ]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    $product->refresh();
    expect($product->name)->toBe('Insta360 Spin Stick');
    expect($product->category_id)->toBe($sticks->id);
    expect($product->description)->toBe('Fits X5');
    expect((float) $product->price)->toBe(89.0);
    expect((int) $product->stock)->toBe(3);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/product-edits/9/applied') && $r['outcome'] === 'applied');
    Queue::assertPushed(PushProductToHq::class);   // HQ gets this outlet's values back
});

it('sets the price when HQ sent it for all outlets', function () {
    $product = Product::factory()->create(['barcode' => '6942590200948', 'price' => 89]);
    fakeHqProductEdit('6942590200948', ['name' => $product->name, 'price' => 99.5]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    expect((float) $product->refresh()->price)->toBe(99.5);
});

it('clears the description when HQ cleared it, and leaves the category when HQ sent none', function () {
    $product = Product::factory()->create(['barcode' => '6942590200948', 'description' => 'Old note']);
    $categoryId = $product->category_id;
    fakeHqProductEdit('6942590200948', ['name' => $product->name, 'description' => null]);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    $product->refresh();
    expect($product->description)->toBeNull();
    expect($product->category_id)->toBe($categoryId);
});

it('reports a barcode it no longer has', function () {
    fakeHqProductEdit('0000000000000', ['name' => 'Gone']);

    $this->artisan('stock:transfers-poll')->assertSuccessful();

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/product-edits/9/applied') && $r['outcome'] === 'missing');
});
