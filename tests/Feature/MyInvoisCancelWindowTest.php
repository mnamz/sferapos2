<?php

use App\Models\User;
use App\Services\MyInvoisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.myinvois.enabled' => true,
        'services.myinvois.base_url' => 'https://sandbox-middleware.test',
        'services.myinvois.cancellation_window_hours' => 72,
        'services.myinvois.branch' => 'TEST',
    ]);
    makeShopSettings();
});

it('reports invoice within the cancellation window', function () {
    $order = makeOrder();
    $invoice = makeInvoice($order);

    expect(app(MyInvoisService::class)->isWithinCancellationWindow($invoice))->toBeTrue();
});

it('reports invoice outside the cancellation window after 72 hours', function () {
    $order = makeOrder();
    $invoice = makeInvoice($order);
    $invoice->created_at = now()->subHours(73);
    $invoice->save();

    expect(app(MyInvoisService::class)->isWithinCancellationWindow($invoice->fresh()))->toBeFalse();
});

it('suffixes the document id on reissue', function () {
    $order = makeOrder();
    makeInvoice($order, ['status' => 'credited']);

    $payload = app(MyInvoisService::class)->buildInvoicePayload($order);

    expect($payload['documents'][0]['id'])->toBe($order->id.'-TEST-R1');
});

it('uses the plain document id on first issue', function () {
    $order = makeOrder();

    $payload = app(MyInvoisService::class)->buildInvoicePayload($order);

    expect($payload['documents'][0]['id'])->toBe($order->id.'-TEST');
});

it('blocks cancellation via HTTP after the window lapses', function () {
    $order = makeOrder();
    $invoice = makeInvoice($order);
    $invoice->created_at = now()->subHours(73);
    $invoice->save();

    $this->actingAs(User::first())
        ->put(route('orders.cancelMyInvois', $order), ['reason' => 'typo in name'])
        ->assertSessionHas('error');

    expect($invoice->fresh()->status)->toBe('active');
});

it('cancels within the window and keeps the row for audit', function () {
    Http::fake([
        'sandbox-middleware.test/documents/*/cancel*' => Http::response(['ok' => true], 200),
    ]);

    $order = makeOrder();
    $invoice = makeInvoice($order);

    $this->actingAs(User::first())
        ->put(route('orders.cancelMyInvois', $order), ['reason' => 'typo in name'])
        ->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe('cancelled')
        ->and($order->fresh()->status)->toBe('cancelled')
        ->and($order->fresh()->myInvoisInvoice)->toBeNull();
});
