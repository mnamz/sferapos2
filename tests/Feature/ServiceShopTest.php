<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\RepairJob;
use App\Models\User;

function serviceStaff(string $role = 'staff'): User
{
    \Spatie\Permission\Models\Role::findOrCreate($role);
    $user = User::factory()->create(['status' => true]);
    $user->assignRole($role);

    return $user;
}

function salePayload(array $items, array $overrides = []): array
{
    $subtotal = collect($items)->sum(fn ($i) => $i['price'] * $i['quantity']);

    return array_merge([
        'items' => $items,
        'customer_id' => null,
        'subtotal' => $subtotal,
        'tax' => 0,
        'delivery_cost' => 0,
        'discount' => 0,
        'total' => $subtotal,
        'paid_amount' => $subtotal,
        'due_amount' => 0,
        'change_amount' => 0,
        'payment_method' => 'cash',
        'delivery_method' => 'walk-in',
    ], $overrides);
}

it('sells a service without touching stock and a custom charge without a product', function () {
    $user = serviceStaff();
    $service = Product::factory()->create(['type' => 'service', 'stock' => 0, 'price' => 50, 'cost_price' => 0, 'warranty_days' => 7]);
    $part = Product::factory()->create(['type' => 'part', 'stock' => 3, 'price' => 200, 'cost_price' => 120]);

    $this->actingAs($user)->postJson('/orders', salePayload([
        ['id' => $service->id, 'quantity' => 2, 'price' => 50],
        ['id' => $part->id, 'quantity' => 1, 'price' => 200],
        ['name' => 'Data transfer', 'quantity' => 1, 'price' => 30],
    ]))->assertOk()->assertJson(['success' => true]);

    $order = Order::with('items')->latest('id')->first();
    expect($order->items)->toHaveCount(3);
    expect($service->fresh()->stock)->toBe(0);
    expect($part->fresh()->stock)->toBe(2);

    $custom = $order->items->firstWhere('product_name', 'Data transfer');
    expect($custom->product_id)->toBeNull();
    expect($custom->item_type)->toBe('service');
    expect($order->items->firstWhere('product_id', $service->id)->warranty_days)->toBe(7);
});

it('cancelling an order does not inflate service stock', function () {
    $user = serviceStaff('admin');
    $service = Product::factory()->create(['type' => 'service', 'stock' => 0, 'price' => 50]);

    $this->actingAs($user)->postJson('/orders', salePayload([['id' => $service->id, 'quantity' => 1, 'price' => 50]]))->assertOk();
    $order = Order::latest('id')->first();

    $this->actingAs($user)->put(route('orders.updateStatus', $order), ['status' => 'cancelled'])->assertRedirect();
    expect($service->fresh()->stock)->toBe(0);
});

it('rejects a sale when a stocked part is short', function () {
    $user = serviceStaff();
    $part = Product::factory()->create(['type' => 'part', 'stock' => 1, 'price' => 100]);

    $this->actingAs($user)->postJson('/orders', salePayload([['id' => $part->id, 'quantity' => 2, 'price' => 100]]))
        ->assertStatus(422);
    expect(Order::count())->toBe(0);
    expect($part->fresh()->stock)->toBe(1);
});

it('quick-adds a customer with a local phone number and reuses them by phone', function () {
    $user = serviceStaff();

    $first = $this->actingAs($user)->postJson(route('customers.quick'), ['name' => 'Ali', 'phone' => '012-345 6789'])->assertOk()->json();
    expect($first['phone'])->toBe('+60123456789');

    $again = $this->actingAs($user)->postJson(route('customers.quick'), ['name' => 'Ali B', 'phone' => '+60 12 345 6789'])->assertOk()->json();
    expect($again['id'])->toBe($first['id']);
    expect(Customer::count())->toBe(1);

    $found = $this->actingAs($user)->getJson('/api/customers/search?q=0123456')->assertOk()->json();
    expect(collect($found)->pluck('id'))->toContain($first['id']);
});

it('runs a repair job from intake through checkout', function () {
    $user = serviceStaff();
    $customer = Customer::factory()->create(['phone' => '+60123456789']);
    $screen = Product::factory()->create(['type' => 'part', 'stock' => 2, 'price' => 300, 'cost_price' => 180, 'name' => 'iPhone 13 Screen']);

    $this->actingAs($user)->post(route('repairs.store'), [
        'customer_id' => $customer->id,
        'device_type' => 'phone',
        'brand' => 'Apple',
        'model' => 'iPhone 13',
        'imei' => '35 1234 5678 9012',
        'passcode_type' => 'pattern',
        'passcode' => '1-2-3-6-9',
        'accessories' => ['SIM tray'],
        'pre_checks' => ['power' => 'ok', 'display' => 'faulty'],
        'issue' => 'Cracked screen',
        'priority' => 'normal',
        'estimated_cost' => 350,
        'deposit' => 100,
        'deposit_method' => 'cash',
    ])->assertRedirect();

    $job = RepairJob::first();
    expect($job->job_number)->toStartWith('RJ'.now()->format('ym').'-');
    expect($job->imei)->toBe('35123456789012');
    expect($job->logs()->count())->toBe(1);
    // The passcode never enters the audit trail.
    expect(\OwenIt\Auditing\Models\Audit::where('auditable_type', RepairJob::class)->get()->pluck('new_values')->flatten()->toJson())->not->toContain('1-2-3-6-9');

    $this->actingAs($user)->post(route('repairs.items.store', $job), ['product_id' => $screen->id, 'quantity' => 1, 'price' => 300])->assertRedirect();
    $this->actingAs($user)->post(route('repairs.items.store', $job), ['name' => 'Labour', 'item_type' => 'service', 'quantity' => 1, 'price' => 50])->assertRedirect();
    $this->actingAs($user)->post(route('repairs.status', $job), ['status' => 'ready', 'note' => 'Screen replaced'])->assertRedirect();
    expect($job->fresh()->status)->toBe('ready');
    expect($job->fresh()->completed_at)->not->toBeNull();

    // "collected" is only reachable through checkout.
    $this->actingAs($user)->post(route('repairs.status', $job), ['status' => 'collected'])->assertRedirect();
    expect($job->fresh()->status)->toBe('ready');

    $this->actingAs($user)->get(route('orders.create', ['repair_job' => $job->id]))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Orders/Create')->where('repair_job.deposit', 100)->has('repair_job.items', 2));

    $this->actingAs($user)->postJson('/orders', salePayload([
        ['id' => $screen->id, 'quantity' => 1, 'price' => 300],
        ['name' => 'Labour', 'quantity' => 1, 'price' => 50],
    ], ['repair_job_id' => $job->id, 'customer_id' => $customer->id]))->assertOk();

    $job->refresh();
    $order = Order::latest('id')->first();
    expect($job->status)->toBe('collected');
    expect($job->order_id)->toBe($order->id);
    expect($order->repair_job_id)->toBe($job->id);
    expect($screen->fresh()->stock)->toBe(1);
    expect($job->isUnderWarranty())->toBeTrue();

    // A second checkout of the same job is refused.
    $this->actingAs($user)->postJson('/orders', salePayload([['name' => 'x', 'quantity' => 1, 'price' => 1]], ['repair_job_id' => $job->id]))->assertStatus(422);
});

it('renders the repair pages and job sheet', function () {
    $user = serviceStaff();
    $job = RepairJob::create([
        'job_number' => RepairJob::nextJobNumber(),
        'customer_id' => Customer::factory()->create(['phone' => '+60129998888'])->id,
        'device_type' => 'phone',
        'brand' => 'Samsung',
        'model' => 'S23',
        'passcode_type' => 'none',
        'issue' => 'Battery',
        'status' => 'received',
    ]);

    $this->actingAs($user)->get(route('repairs.index'))->assertOk();
    $this->actingAs($user)->get(route('repairs.create'))->assertOk();
    $this->actingAs($user)->get(route('repairs.show', $job))->assertOk();
    $this->actingAs($user)->get(route('repairs.edit', $job))->assertOk();
    $this->actingAs($user)->get(route('repairs.print', $job))->assertOk()->assertSee($job->job_number);
    $this->actingAs($user)->get(route('repairs.print', $job).'?format=thermal')->assertOk();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('lets a customer track a repair only with the matching phone', function () {
    $job = RepairJob::create([
        'job_number' => 'RJ2609-0042',
        'customer_id' => Customer::factory()->create(['phone' => '+60123334444'])->id,
        'device_type' => 'phone',
        'passcode_type' => 'none',
        'issue' => 'No power',
        'status' => 'in_progress',
    ]);

    $this->get(route('track.index'))->assertOk();
    $this->post(route('track.lookup'), ['job_number' => 'rj2609-0042', 'phone' => '0000'])->assertSessionHasErrors('job_number');
    $this->post(route('track.lookup'), ['job_number' => 'rj2609-0042', 'phone' => '012-333 4444'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Track')->where('result.status', 'in_progress'));
});

it('creates service products without stock', function () {
    $admin = serviceStaff('admin');
    $category = \App\Models\Category::factory()->create();

    $this->actingAs($admin)->post(route('products.store'), [
        'name' => 'Screen replacement labour',
        'type' => 'service',
        'price' => 80,
        'cost_price' => 0,
        'stock' => '',
        'category_id' => $category->id,
        'status' => 'active',
        'warranty_days' => 30,
    ])->assertRedirect(route('products.index'));

    $p = Product::where('name', 'Screen replacement labour')->first();
    expect($p->type)->toBe('service');
    expect($p->stock)->toBe(0);

    $pos = $this->actingAs($admin)->getJson(route('pos.products'))->json('products');
    expect(collect($pos)->pluck('id'))->toContain($p->id);
});
