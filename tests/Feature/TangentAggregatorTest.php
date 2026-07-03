<?php

use App\Models\Order;
use App\Models\ShopSettings;
use App\Models\User;
use App\Services\Tangent\HourlySalesAggregator;
use Carbon\CarbonImmutable;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.tangent.machine_id' => '71000005',
        'services.tangent.batch_id' => '1',
        'services.tangent.timezone' => 'Asia/Kuala_Lumpur',
        'services.tangent.gst_registered' => 'N',
    ]);
    $this->user = User::factory()->create();
});

function tangentOrderAt(int $userId, string $klDateTime, array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'user_id' => $userId,
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
    ], $overrides));

    $order->created_at = CarbonImmutable::parse($klDateTime, 'Asia/Kuala_Lumpur');
    $order->save();

    return $order;
}

it('returns exactly 24 zero-filled hours when there are no sales', function () {
    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows)->toHaveCount(24);
    expect($rows[0]['receipt_count'])->toBe(0);
    expect($rows[23]['gto'])->toBe(0.0);
    expect($rows[10]['gst_registered'])->toBe('N');
});

it('buckets an order into its KL hour with ex-tax GTO', function () {
    tangentOrderAt($this->user->id, '2026-07-04 10:30:00', [
        'subtotal' => 100, 'discount' => 0, 'tax' => 6, 'payment_method' => 'cash',
    ]);

    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows[10]['receipt_count'])->toBe(1);
    expect($rows[10]['gto'])->toBe(100.0);   // ex-tax net (subtotal - discount)
    expect($rows[10]['gst'])->toBe(6.0);
    expect($rows[10]['cash'])->toBe(100.0);
    expect($rows[9]['receipt_count'])->toBe(0);
});

it('subtracts discount from GTO and the tender', function () {
    tangentOrderAt($this->user->id, '2026-07-04 12:00:00', [
        'subtotal' => 100, 'discount' => 10, 'tax' => 5.4, 'payment_method' => 'card',
    ]);

    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows[12]['gto'])->toBe(90.0);
    expect($rows[12]['discount'])->toBe(10.0);
    expect($rows[12]['visa'])->toBe(90.0); // card -> visa
});

it('excludes cancelled and soft-deleted orders', function () {
    tangentOrderAt($this->user->id, '2026-07-04 10:00:00', ['subtotal' => 100]);
    tangentOrderAt($this->user->id, '2026-07-04 10:05:00', ['subtotal' => 50, 'status' => 'cancelled']);
    $deleted = tangentOrderAt($this->user->id, '2026-07-04 10:10:00', ['subtotal' => 30]);
    $deleted->delete(); // soft delete

    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows[10]['receipt_count'])->toBe(1);
    expect($rows[10]['gto'])->toBe(100.0);
});

it('maps payment methods to tender fields and keeps sum(tenders) == gto', function () {
    $map = [
        'cash' => 'cash', 'card' => 'visa', 'e-wallet' => 'tng',
        'online_transfer' => 'others_amount', 'bank_transfer' => 'others_amount',
        'mystery' => 'others_amount',
    ];
    foreach (array_keys($map) as $i => $method) {
        tangentOrderAt($this->user->id, '2026-07-04 10:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00', [
            'subtotal' => 100, 'payment_method' => $method,
        ]);
    }

    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows[10]['cash'])->toBe(100.0);
    expect($rows[10]['visa'])->toBe(100.0);
    expect($rows[10]['tng'])->toBe(100.0);
    expect($rows[10]['others_amount'])->toBe(300.0); // online_transfer + bank_transfer + mystery
    expect($rows[10]['gto'])->toBe(600.0);

    $tenders = $rows[10]['cash'] + $rows[10]['tng'] + $rows[10]['visa']
        + $rows[10]['mastercard'] + $rows[10]['amex'] + $rows[10]['voucher']
        + $rows[10]['others_amount'];
    expect($tenders)->toBe($rows[10]['gto']);
});

it('derives gstregistered from shop settings when config is null', function () {
    config(['services.tangent.gst_registered' => null]);
    ShopSettings::create([
        'shop_name' => 'Test',
        'shop_address' => '1 Test Street',
        'shop_phone' => '+60123456789',
        'shop_email' => 'shop@test.local',
        'currency' => 'MYR',
        'tax_percentage' => 6,
        'tax_number' => 'SST-123',
    ]);

    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows[0]['gst_registered'])->toBe('Y');
});
