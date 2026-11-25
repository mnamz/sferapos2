<?php

use App\Jobs\PushLevelToHq;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\HqSyncMute;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// A manual stock change (restock, withdraw, stock edited on the form) must reach
// HQ straight away, not at the next hourly snapshot — otherwise transfers made in
// the meantime show a false "possible shortfall" at this outlet.

beforeEach(function () {
    config([
        'stockhq.enabled' => true,
        'stockhq.url' => 'https://hq.test',
        'stockhq.token' => 'token',
        'stockhq.branch_id' => 9,
    ]);
});

function levelAdmin(): User
{
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('queues a level push when stock is restocked', function () {
    Queue::fake();
    $product = Product::factory()->create(['barcode' => '6977644765975', 'stock' => 1, 'serial_tracked' => false]);

    $this->actingAs(levelAdmin())
        ->post(route('products.adjust-stock', $product), ['type' => 'restock', 'quantity' => 3])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->stock)->toBe(4);
    Queue::assertPushedOn('stockhq', PushLevelToHq::class, fn ($job) => $job->productId === $product->id);
});

it('queues a level push when stock is withdrawn', function () {
    Queue::fake();
    $product = Product::factory()->create(['stock' => 5, 'serial_tracked' => false]);

    $this->actingAs(levelAdmin())
        ->post(route('products.adjust-stock', $product), ['type' => 'withdraw', 'quantity' => 2]);

    Queue::assertPushed(PushLevelToHq::class);
});

it('queues a level push when stock is edited on the product form, but not for other edits', function () {
    Queue::fake();
    $category = Category::create(['name' => 'Parts', 'status' => 'active']);
    $product = Product::factory()->create(['stock' => 1, 'category_id' => $category->id, 'serial_tracked' => false]);
    $form = fn (int $stock) => [
        'name' => $product->name, 'price' => 99, 'cost_price' => 65, 'stock' => $stock,
        'category_id' => $category->id, 'status' => 'active', 'serial_tracked' => false,
    ];
    $admin = levelAdmin();

    $this->actingAs($admin)->put(route('products.update', $product), $form(1));
    Queue::assertNotPushed(PushLevelToHq::class);

    $this->actingAs($admin)->put(route('products.update', $product), $form(4));
    Queue::assertPushed(PushLevelToHq::class, 1);
});

it('sends the current on-hand to HQ as a snapshot level', function () {
    Http::fake(['*' => Http::response(['status' => 'ok', 'applied' => 1])]);
    $product = Product::factory()->create(['barcode' => '6977644765975', 'stock' => 4, 'serial_tracked' => false]);

    (new PushLevelToHq($product->id))->handle(app(\App\Services\StockHq::class));

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/levels/snapshot')
        && $r['levels'] === [['sku' => '6977644765975', 'on_hand' => 4]]
        && str_starts_with($r['snapshot_id'], 'manual-'));
    // The catalogue row goes first so HQ knows the sku.
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/products'));
});

it('does not push serial-tracked products, a disabled HQ, or changes HQ itself drove', function () {
    Queue::fake();
    $hq = app(\App\Services\StockHq::class);

    $hq->queueLevelPush(Product::factory()->create(['serial_tracked' => true]));
    HqSyncMute::muted(fn () => $hq->queueLevelPush(Product::factory()->create(['serial_tracked' => false])));
    config(['stockhq.enabled' => false]);
    $hq->queueLevelPush(Product::factory()->create(['serial_tracked' => false]));

    Queue::assertNotPushed(PushLevelToHq::class);
});
