<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

function registerAdmin(): User
{
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

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
    $user = registerAdmin();

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
    $user = registerAdmin();
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
    $user = registerAdmin();
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
    $user = registerAdmin();
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

it('filters by brand on the first word of the product name, case-insensitive', function () {
    $user = registerAdmin();
    $cat = Category::create(['name' => 'DJI']);
    $dji = Product::factory()->create(['name' => 'DJI MINI 4 PRO', 'category_id' => $cat->id]);
    $insta = Product::factory()->create(['name' => 'INSTA360 X4', 'category_id' => $cat->id]);
    $mini = Product::factory()->create(['name' => 'MINIATURE TRIPOD', 'category_id' => $cat->id]);

    makeRegisterOrder(['user_id' => $user->id], [
        ['product_id' => $dji->id, 'product_name' => 'DJI MINI 4 PRO', 'quantity' => 2, 'total' => 200],
        ['product_id' => $insta->id, 'product_name' => 'INSTA360 X4', 'quantity' => 5, 'total' => 500],
        ['product_id' => $mini->id, 'product_name' => 'MINIATURE TRIPOD', 'quantity' => 9, 'total' => 900],
    ]);

    $this->actingAs($user)
        ->get(route('reports.sales-register', [
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'brand' => 'dji',
        ]))
        ->assertInertia(fn ($page) => $page->where('grandTotal.quantity', 2));
});

it('filters by category', function () {
    $user = registerAdmin();
    $cameras = Category::create(['name' => 'DIGITAL CAMERA']);
    $acc = Category::create(['name' => 'ACCESSORIES']);
    $cam = Product::factory()->create(['name' => 'DJI NEO 2', 'category_id' => $cameras->id]);
    $strap = Product::factory()->create(['name' => 'DJI STRAP', 'category_id' => $acc->id]);

    makeRegisterOrder(['user_id' => $user->id], [
        ['product_id' => $cam->id, 'product_name' => 'DJI NEO 2', 'quantity' => 3, 'total' => 300],
        ['product_id' => $strap->id, 'product_name' => 'DJI STRAP', 'quantity' => 8, 'total' => 80],
    ]);

    $this->actingAs($user)
        ->get(route('reports.sales-register', [
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'category_id' => $cameras->id,
        ]))
        ->assertInertia(fn ($page) => $page
            ->where('grandTotal.quantity', 3)
            ->where('groups.0.category', 'DIGITAL CAMERA')
        );
});

it('filters by salesperson and by payment method', function () {
    $alice = registerAdmin();
    $bob = User::factory()->create();
    $cat = Category::create(['name' => 'DJI']);
    $p = Product::factory()->create(['name' => 'DJI AIR 3', 'category_id' => $cat->id]);

    makeRegisterOrder(['user_id' => $alice->id, 'payment_method' => 'cash'], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 1, 'total' => 100],
    ]);
    makeRegisterOrder(['user_id' => $bob->id, 'payment_method' => 'card'], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 6, 'total' => 600],
    ]);

    $this->actingAs($alice)
        ->get(route('reports.sales-register', [
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'user_id' => $alice->id,
        ]))
        ->assertInertia(fn ($page) => $page->where('grandTotal.quantity', 1));

    $this->actingAs($alice)
        ->get(route('reports.sales-register', [
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'payment_method' => 'card',
        ]))
        ->assertInertia(fn ($page) => $page->where('grandTotal.quantity', 6));
});

it('provides brand and category filter options', function () {
    $user = registerAdmin();
    $cat = Category::create(['name' => 'DIGITAL CAMERA']);
    Product::factory()->create(['name' => 'DJI NEO 2', 'category_id' => $cat->id]);
    Product::factory()->create(['name' => 'INSTA360 X4', 'category_id' => $cat->id]);
    Customer::factory()->create(['name' => 'Acme Corp']);

    $this->actingAs($user)
        ->get(route('reports.sales-register'))
        ->assertInertia(fn ($page) => $page
            ->where('filterOptions.brands', fn ($brands) => collect($brands)->contains('DJI') && collect($brands)->contains('INSTA360'))
            ->where('filterOptions.categories', fn ($cats) => collect($cats)->pluck('name')->contains('DIGITAL CAMERA'))
            ->where('filterOptions.customers', fn ($custs) => collect($custs)->pluck('name')->contains('Acme Corp'))
            ->where('filterOptions.paymentMethods', fn ($methods) => collect($methods)->contains('e-wallet') && collect($methods)->contains('online_transfer'))
            ->where('filterOptions.deliveryMethods', fn ($methods) => collect($methods)->contains('walk-in'))
            ->has('filterOptions.salespersons')
        );
});

it('exports the sales register as an xlsx download', function () {
    $user = registerAdmin();
    $cat = Category::create(['name' => 'DJI']);
    $p = Product::factory()->create(['name' => 'DJI AIR 3', 'category_id' => $cat->id]);
    makeRegisterOrder(['user_id' => $user->id], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 2, 'total' => 200],
    ]);

    $response = $this->actingAs($user)->get(route('reports.sales-register.export', [
        'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

it('bundles matching orders into a single invoices PDF', function () {
    makeShopSettings();
    $user = registerAdmin();
    $cat = Category::create(['name' => 'DJI']);
    $p = Product::factory()->create(['name' => 'DJI AIR 3', 'category_id' => $cat->id]);
    makeRegisterOrder(['user_id' => $user->id], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 1, 'price' => 100, 'total' => 100],
    ]);

    $response = $this->actingAs($user)->get(route('reports.sales-register.invoices', [
        'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('redirects back when no orders match the invoices bundle', function () {
    $user = registerAdmin();

    $this->actingAs($user)
        ->get(route('reports.sales-register.invoices', [
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
        ]))
        ->assertRedirect();
});

it('includes orders at the end_date upper boundary and excludes the next day', function () {
    $user = registerAdmin();
    $cat = Category::create(['name' => 'DJI']);
    $p = Product::factory()->create(['name' => 'DJI AIR 3', 'category_id' => $cat->id]);

    makeRegisterOrder(['user_id' => $user->id, 'created_at' => '2026-06-30 23:59:59'], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 2, 'total' => 200],
    ]);
    makeRegisterOrder(['user_id' => $user->id, 'created_at' => '2026-07-01 00:00:00'], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 5, 'total' => 500],
    ]);

    $this->actingAs($user)
        ->get(route('reports.sales-register', ['start_date' => '2026-06-01', 'end_date' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page->where('grandTotal.quantity', 2));
});

it('exports multiple categories with per-category totals', function () {
    $user = registerAdmin();
    $cameras = Category::create(['name' => 'DIGITAL CAMERA']);
    $acc = Category::create(['name' => 'ACCESSORIES']);
    $cam = Product::factory()->create(['name' => 'DJI NEO 2', 'category_id' => $cameras->id]);
    $strap = Product::factory()->create(['name' => 'DJI STRAP', 'category_id' => $acc->id]);

    makeRegisterOrder(['user_id' => $user->id], [
        ['product_id' => $cam->id, 'product_name' => 'DJI NEO 2', 'quantity' => 3, 'total' => 300],
        ['product_id' => $strap->id, 'product_name' => 'DJI STRAP', 'quantity' => 8, 'total' => 80],
    ]);

    $response = $this->actingAs($user)->get(route('reports.sales-register.export', [
        'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

it('forbids non-admin users from all sales register routes', function () {
    \Spatie\Permission\Models\Role::findOrCreate('manager');
    \Spatie\Permission\Models\Role::findOrCreate('staff');

    foreach (['manager', 'staff'] as $roleName) {
        $user = User::factory()->create();
        $user->assignRole($roleName);

        foreach (['reports.sales-register', 'reports.sales-register.export', 'reports.sales-register.invoices'] as $routeName) {
            $this->actingAs($user)
                ->get(route($routeName, ['start_date' => '2026-06-01', 'end_date' => '2026-06-30']))
                ->assertForbidden();
        }
    }
});
