<?php

use App\Models\Order;
use App\Models\TangentSalesHourly;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.tangent.enabled' => true,
        'services.tangent.base_url' => 'https://tangent.test/api',
        'services.tangent.username' => 'u',
        'services.tangent.password' => 'p',
        'services.tangent.machine_id' => '71000005',
        'services.tangent.batch_id' => '1',
        'services.tangent.gst_registered' => 'N',
        'services.tangent.timezone' => 'Asia/Kuala_Lumpur',
    ]);
    $this->user = User::factory()->create();
});

function pushOrderAt(int $userId, string $klDateTime, array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'user_id' => $userId, 'subtotal' => 100, 'tax' => 0, 'delivery_cost' => 0,
        'discount' => 0, 'total' => 100, 'profit' => 0, 'paid_amount' => 100,
        'due_amount' => 0, 'change_amount' => 0, 'payment_method' => 'cash',
        'delivery_method' => 'walk-in', 'status' => 'completed',
    ], $overrides));
    $order->created_at = CarbonImmutable::parse($klDateTime, 'Asia/Kuala_Lumpur');
    $order->save();

    return $order;
}

function fakeTangentSuccess(): void
{
    Http::fake([
        '*/token' => Http::response(['access_token' => 'abc', 'expires_in' => 1799], 200),
        '*/SalesHourly' => Http::response(['status' => 'success', 'message' => 'ok'], 200),
    ]);
}

function salesRequestCount(): int
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), 'SalesHourly'))
        ->count();
}

it('does nothing and makes no HTTP call when disabled', function () {
    config(['services.tangent.enabled' => false]);
    Http::fake();
    pushOrderAt($this->user->id, '2026-07-04 10:00:00');

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();

    expect(TangentSalesHourly::count())->toBe(0);
    Http::assertNothingSent();
});

it('sends a day once and marks its 24 rows sent', function () {
    fakeTangentSuccess();
    pushOrderAt($this->user->id, '2026-07-04 10:00:00', ['subtotal' => 100, 'payment_method' => 'cash']);

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();

    $rows = TangentSalesHourly::where('sale_date', '2026-07-04')->get();
    expect($rows)->toHaveCount(24);
    expect($rows->firstWhere('hour', 10)->status)->toBe('sent');
    expect($rows->firstWhere('hour', 10)->receipt_count)->toBe(1);
    expect((float) $rows->firstWhere('hour', 10)->gto)->toBe(100.0);
    expect(salesRequestCount())->toBe(1);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'SalesHourly') && count($r['sales']) === 24);
});

it('is idempotent: an unchanged day is not re-sent', function () {
    fakeTangentSuccess();
    pushOrderAt($this->user->id, '2026-07-04 10:00:00');

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();
    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();

    expect(salesRequestCount())->toBe(1); // second run skipped
});

it('re-sends a day after an order is voided and reflects the deduction', function () {
    fakeTangentSuccess();
    $order = pushOrderAt($this->user->id, '2026-07-04 10:00:00', ['subtotal' => 100]);

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();

    $order->update(['status' => 'cancelled']);
    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();

    expect(salesRequestCount())->toBe(2); // resent after void
    $row = TangentSalesHourly::where('sale_date', '2026-07-04')->where('hour', 10)->first();
    expect($row->receipt_count)->toBe(0);
    expect((float) $row->gto)->toBe(0.0);
    expect($row->status)->toBe('sent');
});

it('marks a day failed on error, then retries and succeeds next run', function () {
    Http::fake([
        '*/token' => Http::response(['access_token' => 'abc', 'expires_in' => 1799], 200),
        '*/SalesHourly' => Http::sequence()
            ->push(['status' => 'error', 'errors' => [['message' => 'boom']]], 500)
            ->push(['status' => 'success', 'message' => 'ok'], 200),
    ]);
    pushOrderAt($this->user->id, '2026-07-04 10:00:00');

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();
    expect(TangentSalesHourly::where('sale_date', '2026-07-04')->where('hour', 10)->first()->status)->toBe('failed');

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();
    expect(TangentSalesHourly::where('sale_date', '2026-07-04')->where('hour', 10)->first()->status)->toBe('sent');
});

it('dry-run writes nothing and sends nothing', function () {
    Http::fake();
    pushOrderAt($this->user->id, '2026-07-04 10:00:00');

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04', '--dry-run' => true])->assertSuccessful();

    expect(TangentSalesHourly::count())->toBe(0);
    Http::assertNothingSent();
});

it('test-connection fetches a token and sends no sales', function () {
    fakeTangentSuccess();

    $this->artisan('tangent:push-sales', ['--test-connection' => true])->assertSuccessful();

    expect(salesRequestCount())->toBe(0);
});
