<?php

use App\Models\MyInvoisCreditNote;
use App\Models\User;
use App\Services\MyInvoisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

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

function adminUser(): \App\Models\User
{
    $user = \App\Models\User::first() ?? \App\Models\User::factory()->create();
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $user->assignRole('admin');

    return $user;
}

it('submits a credit note referencing the original e-invoice', function () {
    Http::fake([
        'sandbox-middleware.test/documents/submit/credit-note' => Http::response([
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

        return str_contains($request->url(), '/documents/submit/credit-note')
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
        'sandbox-middleware.test/documents/submit/credit-note' => Http::response([
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
        return str_contains($request->url(), '/documents/submit/credit-note')
            && $request->data()['documents'][0]['id'] === 'CN'.$order->id.'-TEST-2';
    });
});

it('issues a credit note via HTTP and frees the order for reissue', function () {
    Http::fake([
        'sandbox-middleware.test/documents/submit/credit-note' => Http::response([
            'submissionUid' => 'CN-SUB-1',
            'acceptedDocuments' => [['uuid' => 'CN-UUID-1', 'invoiceCodeNumber' => 'CN1-TEST']],
        ], 200),
    ]);

    $order = makeOrder();
    $invoice = makeInvoice($order);
    $invoice->created_at = now()->subHours(100);
    $invoice->save();

    $this->actingAs(adminUser())
        ->post(route('orders.creditNoteMyInvois', $order), ['reason' => 'wrong amount'])
        ->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe('credited')
        ->and($order->fresh()->myInvoisInvoice)->toBeNull()
        ->and($order->fresh()->status)->toBe('cancelled')
        ->and(MyInvoisCreditNote::count())->toBe(1);
});

it('forbids a non-admin from issuing a credit note', function () {
    Http::fake(['sandbox-middleware.test/*' => Http::response([], 200)]);

    $order = makeOrder();
    $invoice = makeInvoice($order);
    $invoice->created_at = now()->subHours(100);
    $invoice->save();

    // makeOrder()'s user has no admin role.
    $this->actingAs(User::first())
        ->post(route('orders.creditNoteMyInvois', $order), ['reason' => 'wrong amount'])
        ->assertForbidden();

    expect($invoice->fresh()->status)->toBe('active')
        ->and(MyInvoisCreditNote::count())->toBe(0);
});

it('reissues a corrected invoice with a unique -R1 id after a credit note', function () {
    Http::fake([
        'sandbox-middleware.test/documents/submit/credit-note' => Http::response([
            'submissionUid' => 'CN-SUB-1',
            'acceptedDocuments' => [['uuid' => 'CN-UUID-1', 'invoiceCodeNumber' => 'CN1-TEST']],
        ], 200),
        'sandbox-middleware.test/documents/submit/invoice' => Http::response([
            'submissionUid' => 'RE-SUB-1',
            'acceptedDocuments' => [['uuid' => 'RE-UUID-1', 'invoiceCodeNumber' => 'RE1-TEST']],
        ], 200),
    ]);

    $order = makeOrder();
    $original = makeInvoice($order);

    $service = app(MyInvoisService::class);

    expect($service->submitCreditNote($order, 'wrong amount'))->toBeTrue();
    expect($order->fresh()->myInvoisInvoice)->toBeNull();

    // Reissue a corrected invoice (the LHDN procedure after a credit note).
    expect($service->submitInvoice($order->fresh(), true))->toBeTrue();

    // The reissued invoice document id is uniquified to avoid LHDN duplicate-id rejection.
    Http::assertSent(function ($request) use ($order) {
        return str_contains($request->url(), '/documents/submit/invoice')
            && $request->data()['documents'][0]['id'] === $order->id.'-TEST-R1';
    });

    // A new active invoice row exists and the active-scoped relation resolves to it.
    $reissued = $order->fresh()->myInvoisInvoice;
    expect($reissued)->not->toBeNull()
        ->and($reissued->status)->toBe('active')
        ->and($reissued->id)->not->toBe($original->id)
        ->and(\App\Models\MyInvoisInvoice::where('order_id', $order->id)->count())->toBe(2);
});

it('sends the configured X-API-Key header to the middleware', function () {
    config(['services.myinvois.api_key' => 'SECRET-KEY']);

    Http::fake([
        'sandbox-middleware.test/documents/submit/credit-note' => Http::response([
            'submissionUid' => 'CN-SUB-1',
            'acceptedDocuments' => [['uuid' => 'CN-UUID-1', 'invoiceCodeNumber' => 'CN1-TEST']],
        ], 200),
    ]);

    $order = makeOrder();
    makeInvoice($order);

    app(MyInvoisService::class)->submitCreditNote($order, 'reason');

    Http::assertSent(fn ($request) => $request->hasHeader('X-API-Key', 'SECRET-KEY'));
});

it('emails the reissued e-invoice to the customer on a reissue push', function () {
    Mail::fake();
    Http::fake([
        'sandbox-middleware.test/documents/submit/invoice' => Http::response([
            'submissionUid' => 'RE-SUB-1',
            'acceptedDocuments' => [['uuid' => 'RE-UUID-1', 'invoiceCodeNumber' => 'RE1-TEST']],
        ], 200),
        'sandbox-middleware.test/documents/*' => Http::response([], 200),
    ]);

    $customer = \App\Models\Customer::create(['name' => 'Jane', 'email' => 'jane@test.local']);
    $order = makeOrder();
    $order->update(['customer_id' => $customer->id]);

    // A prior credited submission means this push is a reissue (post-credit-note state).
    makeInvoice($order, ['status' => 'credited']);

    $this->actingAs(adminUser())
        ->post(route('orders.pushMyInvois', $order))
        ->assertSessionHas('success');

    Mail::assertSent(\App\Mail\EInvoiceEmail::class, fn ($mail) => $mail->hasTo('jane@test.local'));
});

it('reissue push revives a cancelled order and creates a new active e-invoice', function () {
    Mail::fake();
    Http::fake([
        'sandbox-middleware.test/documents/submit/invoice' => Http::response([
            'submissionUid' => 'RE-SUB-1',
            'acceptedDocuments' => [['uuid' => 'RE-UUID-1', 'invoiceCodeNumber' => 'RE1-TEST']],
        ], 200),
        'sandbox-middleware.test/documents/*' => Http::response([], 200),
    ]);

    $order = makeOrder();
    makeInvoice($order, ['status' => 'credited']);
    // Post-credit-note state: order was cancelled, original credited, no active invoice.
    $order->update(['status' => 'cancelled']);

    $this->actingAs(adminUser())
        ->post(route('orders.pushMyInvois', $order))
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('completed')
        ->and($order->myInvoisInvoice)->not->toBeNull()
        ->and($order->myInvoisInvoice->status)->toBe('active');
});

it('forbids a non-admin from reissuing an e-invoice', function () {
    Http::fake(['sandbox-middleware.test/*' => Http::response([], 200)]);

    $order = makeOrder();
    makeInvoice($order, ['status' => 'credited']); // prior invoice → reissue state
    $order->update(['status' => 'cancelled']);

    // makeOrder()'s user has no admin role.
    $this->actingAs(User::first())
        ->post(route('orders.pushMyInvois', $order))
        ->assertForbidden();

    expect($order->fresh()->myInvoisInvoices()->count())->toBe(1)
        ->and($order->fresh()->status)->toBe('cancelled');
});

it('does not email on the first e-invoice submission', function () {
    Mail::fake();
    Http::fake([
        'sandbox-middleware.test/documents/submit/invoice' => Http::response([
            'submissionUid' => 'SUB-1',
            'acceptedDocuments' => [['uuid' => 'UUID-1', 'invoiceCodeNumber' => '1-TEST']],
        ], 200),
        'sandbox-middleware.test/documents/*' => Http::response([], 200),
    ]);

    $customer = \App\Models\Customer::create(['name' => 'Jane', 'email' => 'jane@test.local']);
    $order = makeOrder();
    $order->update(['customer_id' => $customer->id]);

    // No prior invoice → first submission, not a reissue.
    $this->actingAs(User::first())
        ->post(route('orders.pushMyInvois', $order))
        ->assertSessionHas('success');

    Mail::assertNotSent(\App\Mail\EInvoiceEmail::class);
});
