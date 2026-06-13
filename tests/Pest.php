<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function makeShopSettings(): \App\Models\ShopSettings
{
    return \App\Models\ShopSettings::create([
        'shop_name' => 'Test Shop',
        'shop_address' => '1 Test Street',
        'shop_phone' => '+60123456789',
        'shop_email' => 'shop@test.local',
        'currency' => 'MYR',
        'tax_percentage' => 0,
    ]);
}

function makeOrder(): \App\Models\Order
{
    $user = \App\Models\User::factory()->create();

    return \App\Models\Order::create([
        'user_id' => $user->id,
        'subtotal' => 100,
        'tax' => 0,
        'delivery_cost' => 0,
        'discount' => 0,
        'total' => 100,
        'profit' => 0,
        'paid_amount' => 100,
        'due_amount' => 0,
        'change_amount' => 0,
        'payment_method' => 'cash',
        'delivery_method' => 'walk-in',
        'status' => 'completed',
    ]);
}

function makeInvoice(\App\Models\Order $order, array $overrides = []): \App\Models\MyInvoisInvoice
{
    return \App\Models\MyInvoisInvoice::create(array_merge([
        'order_id' => $order->id,
        'submission_uid' => 'SUB123',
        'uuid' => 'UUID123',
        'invoice_code_number' => $order->id . '-TEST',
        'request_payload' => ['documents' => [['id' => $order->id . '-TEST']]],
        'response_payload' => ['ok' => true],
    ], $overrides));
}
