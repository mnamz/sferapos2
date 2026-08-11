<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Role;

function userWithRole(string $role): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function makeOrderWithProfit(User $cashier): Order
{
    $order = Order::factory()->create([
        'user_id' => $cashier->id,
        'status' => 'completed',
        'subtotal' => 100,
        'total' => 100,
        'tax' => 0,
        'profit' => 42,
    ]);

    $product = Product::factory()->create();
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'price' => 100,
        'cost_price' => 58,
        'total' => 100,
        'profit' => 42,
    ]);

    return $order->fresh();
}

it('shows profit on the sales report to admins but hides it from managers', function () {
    $admin = userWithRole('admin');
    makeOrderWithProfit($admin);

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('summary.total_profit')
            ->has('orders.data.0.profit')
        );

    $manager = userWithRole('manager');

    $this->actingAs($manager)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('summary.total_sales')      // sales stay visible
            ->missing('summary.total_profit') // profit is hidden
            ->missing('orders.data.0.profit')
            ->where('profitDetails', [])      // cost breakdown withheld
        );
});

it('shows order profit on the order page to admins but hides it from managers', function () {
    $admin = userWithRole('admin');
    $order = makeOrderWithProfit($admin);

    $this->actingAs($admin)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('order.profit')
            ->has('order.items.0.profit')
        );

    $manager = userWithRole('manager');

    $this->actingAs($manager)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('order.profit')
            ->missing('order.items.0.profit')
            ->has('order.total') // sales figures stay visible
        );
});

it('forbids managers and staff from the cost and profit report routes', function () {
    $adminRoutes = [
        'products.report',
        'products.report.export',
        'products.inventory-cost',
        'products.inventory-cost.export',
    ];

    $admin = userWithRole('admin');
    foreach ($adminRoutes as $name) {
        $this->actingAs($admin)->get(route($name))->assertOk();
    }

    foreach (['manager', 'staff'] as $role) {
        $user = userWithRole($role);
        foreach ($adminRoutes as $name) {
            $this->actingAs($user)->get(route($name))->assertForbidden();
        }
    }
});

it('still lets managers export the sales report (without the profit column)', function () {
    $manager = userWithRole('manager');
    makeOrderWithProfit($manager);

    $this->actingAs($manager)
        ->get(route('reports.export'))
        ->assertOk();
});
