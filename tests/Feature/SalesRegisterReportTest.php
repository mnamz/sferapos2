<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

function makeRegisterOrder(array $orderAttrs, array $items): Order
{
    $createdAt = $orderAttrs['created_at'] ?? '2026-06-15 10:00:00';
    unset($orderAttrs['created_at']);

    $order = Order::factory()->create($orderAttrs);
    $order->forceFill(['created_at' => $createdAt])->save();

    foreach ($items as $item) {
        OrderItem::create(array_merge([
            'order_id' => $order->id,
            'quantity' => 1,
            'price' => 0,
            'cost_price' => 0,
            'total' => 0,
            'profit' => 0,
        ], $item));
    }

    return $order->fresh();
}

it('renders the sales register report page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.sales-register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/SalesRegister')
            ->has('groups')
            ->has('grandTotal')
            ->has('filterOptions')
            ->has('filters')
        );
});

it('groups products by category with per-product qty and gross sales', function () {
    $user = User::factory()->create();
    $cameras = Category::create(['name' => 'DIGITAL CAMERA']);
    $drone = Product::factory()->create(['name' => 'DJI NEO 2', 'category_id' => $cameras->id]);

    makeRegisterOrder(['user_id' => $user->id], [
        ['product_id' => $drone->id, 'product_name' => 'DJI NEO 2', 'quantity' => 2, 'price' => 100, 'total' => 200],
        ['product_id' => $drone->id, 'product_name' => 'DJI NEO 2', 'quantity' => 3, 'price' => 100, 'total' => 300],
    ]);

    $this->actingAs($user)
        ->get(route('reports.sales-register', ['start_date' => '2026-06-01', 'end_date' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->where('groups.0.category', 'DIGITAL CAMERA')
            ->where('groups.0.products.0.name', 'DJI NEO 2')
            ->where('groups.0.products.0.quantity', 5)
            ->where('groups.0.products.0.sales', 500)
            ->where('groups.0.quantity_total', 5)
            ->where('groups.0.sales_total', 500)
            ->where('grandTotal.quantity', 5)
            ->where('grandTotal.sales', 500)
        );
});

it('excludes cancelled and soft-deleted orders from figures', function () {
    $user = User::factory()->create();
    $cat = Category::create(['name' => 'DJI']);
    $p = Product::factory()->create(['name' => 'DJI MINI 4 PRO', 'category_id' => $cat->id]);

    makeRegisterOrder(['user_id' => $user->id], [
        ['product_id' => $p->id, 'product_name' => 'DJI MINI 4 PRO', 'quantity' => 1, 'price' => 50, 'total' => 50],
    ]);
    makeRegisterOrder(['user_id' => $user->id, 'status' => 'cancelled'], [
        ['product_id' => $p->id, 'product_name' => 'DJI MINI 4 PRO', 'quantity' => 9, 'price' => 50, 'total' => 450],
    ]);
    $deleted = makeRegisterOrder(['user_id' => $user->id], [
        ['product_id' => $p->id, 'product_name' => 'DJI MINI 4 PRO', 'quantity' => 7, 'price' => 50, 'total' => 350],
    ]);
    $deleted->delete();

    $this->actingAs($user)
        ->get(route('reports.sales-register', ['start_date' => '2026-06-01', 'end_date' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->where('grandTotal.quantity', 1)
            ->where('grandTotal.sales', 50)
        );
});

it('respects the date range boundaries', function () {
    $user = User::factory()->create();
    $cat = Category::create(['name' => 'DJI']);
    $p = Product::factory()->create(['name' => 'DJI AIR 3', 'category_id' => $cat->id]);

    makeRegisterOrder(['user_id' => $user->id, 'created_at' => '2026-05-31 23:59:59'], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 4, 'total' => 400],
    ]);
    makeRegisterOrder(['user_id' => $user->id, 'created_at' => '2026-06-01 00:00:00'], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 1, 'total' => 100],
    ]);

    $this->actingAs($user)
        ->get(route('reports.sales-register', ['start_date' => '2026-06-01', 'end_date' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page->where('grandTotal.quantity', 1));
});
