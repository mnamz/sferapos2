<?php

use App\Services\MyInvoisService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    expect($payload['documents'][0]['id'])->toBe($order->id . '-TEST-R1');
});

it('uses the plain document id on first issue', function () {
    $order = makeOrder();

    $payload = app(MyInvoisService::class)->buildInvoicePayload($order);

    expect($payload['documents'][0]['id'])->toBe($order->id . '-TEST');
});
