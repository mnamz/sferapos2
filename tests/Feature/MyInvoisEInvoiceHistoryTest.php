<?php

use App\Models\MyInvoisCreditNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

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

it('exposes the full e-invoice history on the order show page', function () {
    $order = makeOrder();

    // Original invoice (now credited) + a credit note + an active reissue.
    $credited = makeInvoice($order, ['status' => 'credited']);
    MyInvoisCreditNote::create([
        'order_id' => $order->id,
        'myinvois_invoice_id' => $credited->id,
        'uuid' => 'CN-UUID-1',
        'credit_note_code_number' => 'CN'.$order->id.'-TEST',
        'reason' => 'wrong amount',
        'request_payload' => ['documents' => [['id' => 'CN'.$order->id.'-TEST']]],
    ]);
    makeInvoice($order, ['uuid' => 'UUID-R1', 'invoice_code_number' => $order->id.'-TEST-R1']);

    $this->actingAs(User::first())
        ->get(route('orders.show', $order))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('order.einvoice_history', 3)
        );
});

it('shows the credited e-invoice when no active one exists (view after credit)', function () {
    Http::fake(['sandbox-middleware.test/*' => Http::response([], 200)]);

    $order = makeOrder();
    $credited = makeInvoice($order, ['status' => 'credited']);

    $this->actingAs(User::first())
        ->get(route('orders.eInvoice', $order))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.myinvois_invoice.uuid', $credited->uuid)
        );
});

it('views a specific e-invoice document by id', function () {
    Http::fake(['sandbox-middleware.test/*' => Http::response([], 200)]);

    $order = makeOrder();
    $credited = makeInvoice($order, ['status' => 'credited', 'uuid' => 'OLD-UUID']);
    makeInvoice($order, ['uuid' => 'NEW-UUID', 'invoice_code_number' => $order->id.'-TEST-R1']);

    $this->actingAs(User::first())
        ->get(route('orders.eInvoice', $order).'?document='.$credited->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.myinvois_invoice.uuid', 'OLD-UUID')
        );
});
