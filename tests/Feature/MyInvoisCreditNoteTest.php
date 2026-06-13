<?php

use App\Models\MyInvoisCreditNote;
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

it('submits a credit note referencing the original e-invoice', function () {
    Http::fake([
        'sandbox-middleware.test/documents/submit/creditNote' => Http::response([
            'submissionUid' => 'CN-SUB-1',
            'acceptedDocuments' => [
                ['uuid' => 'CN-UUID-1', 'invoiceCodeNumber' => 'CN'.'1-TEST'],
            ],
        ], 200),
    ]);

    $order = makeOrder();
    $invoice = makeInvoice($order);

    $result = app(MyInvoisService::class)->submitCreditNote($order, 'Wrong customer details');

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) use ($invoice, $order) {
        $doc = $request->data()['documents'][0];

        return str_contains($request->url(), '/documents/submit/creditNote')
            && $doc['billingReferences'][0]['uuid'] === $invoice->uuid
            && $doc['billingReferences'][0]['internalId'] === $order->id.'-TEST'
            && str_starts_with($doc['id'], 'CN');
    });

    $cn = MyInvoisCreditNote::first();
    expect($cn)->not->toBeNull()
        ->and($cn->order_id)->toBe($order->id)
        ->and($cn->myinvois_invoice_id)->toBe($invoice->id)
        ->and($cn->uuid)->toBe('CN-UUID-1')
        ->and($cn->reason)->toBe('Wrong customer details');

    expect($invoice->fresh()->status)->toBe('credited');
});

it('fails gracefully when middleware rejects the credit note', function () {
    Http::fake([
        'sandbox-middleware.test/*' => Http::response(['error' => 'bad'], 422),
    ]);

    $order = makeOrder();
    $invoice = makeInvoice($order);

    $result = app(MyInvoisService::class)->submitCreditNote($order, 'reason');

    expect($result)->toBeFalse()
        ->and($invoice->fresh()->status)->toBe('active')
        ->and(MyInvoisCreditNote::count())->toBe(0);
});

it('returns false when no active invoice exists', function () {
    $order = makeOrder();

    expect(app(MyInvoisService::class)->submitCreditNote($order, 'reason'))->toBeFalse();
});

it('suffixes the document id when a prior credit note exists for the order', function () {
    Http::fake([
        'sandbox-middleware.test/documents/submit/creditNote' => Http::response([
            'submissionUid' => 'CN-SUB-2',
            'acceptedDocuments' => [['uuid' => 'CN-UUID-2', 'invoiceCodeNumber' => 'CN-2']],
        ], 200),
    ]);

    $order = makeOrder();
    $invoice = makeInvoice($order);

    // Simulate a prior credit-note cycle: one credit note already recorded for this order.
    \App\Models\MyInvoisCreditNote::create([
        'order_id' => $order->id,
        'myinvois_invoice_id' => $invoice->id,
        'reason' => 'previous cycle',
        'request_payload' => ['documents' => [['id' => 'CN'.$order->id.'-TEST']]],
    ]);

    app(MyInvoisService::class)->submitCreditNote($order, 'second correction');

    Http::assertSent(function ($request) use ($order) {
        return str_contains($request->url(), '/documents/submit/creditNote')
            && $request->data()['documents'][0]['id'] === 'CN'.$order->id.'-TEST-2';
    });
});

it('issues a credit note via HTTP and frees the order for reissue', function () {
    Http::fake([
        'sandbox-middleware.test/documents/submit/creditNote' => Http::response([
            'submissionUid' => 'CN-SUB-1',
            'acceptedDocuments' => [['uuid' => 'CN-UUID-1', 'invoiceCodeNumber' => 'CN1-TEST']],
        ], 200),
    ]);

    $order = makeOrder();
    $invoice = makeInvoice($order);
    $invoice->created_at = now()->subHours(100);
    $invoice->save();

    $this->actingAs(User::first())
        ->post(route('orders.creditNoteMyInvois', $order), ['reason' => 'wrong amount'])
        ->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe('credited')
        ->and($order->fresh()->myInvoisInvoice)->toBeNull()
        ->and($order->fresh()->status)->toBe('cancelled')
        ->and(MyInvoisCreditNote::count())->toBe(1);
});
