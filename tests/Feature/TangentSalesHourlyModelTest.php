<?php

use App\Models\TangentSalesHourly;

it('persists an hourly row and enforces the unique (sale_date, hour) key', function () {
    TangentSalesHourly::create([
        'sale_date' => '2026-07-04',
        'hour' => 10,
        'receipt_count' => 3,
        'gto' => 191.54,
        'gst' => 1.55,
        'discount' => 0,
        'cash' => 191.54,
        'gst_registered' => 'N',
        'status' => 'pending',
    ]);

    expect(TangentSalesHourly::count())->toBe(1);

    expect(fn () => TangentSalesHourly::create([
        'sale_date' => '2026-07-04',
        'hour' => 10,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('formats itself into the Tangent API record shape', function () {
    config([
        'services.tangent.machine_id' => '71000005',
        'services.tangent.batch_id' => '1',
    ]);

    $row = new TangentSalesHourly([
        'sale_date' => '2026-07-04',
        'hour' => 5,
        'receipt_count' => 3,
        'gto' => 191.5,
        'gst' => 1.55,
        'discount' => 0,
        'service_charge' => 0,
        'no_of_pax' => 0,
        'cash' => 100,
        'visa' => 91.5,
        'gst_registered' => 'Y',
    ]);

    $record = $row->toApiRecord();

    expect($record)->toMatchArray([
        'machineid' => '71000005',
        'batchid' => '1',
        'date' => '20260704',
        'hour' => '05',
        'receiptcount' => '3',
        'gto' => '191.50',
        'gst' => '1.55',
        'discount' => '0.00',
        'servicecharge' => '0.00',
        'noofpax' => '0',
        'cash' => '100.00',
        'tng' => '0.00',
        'visa' => '91.50',
        'mastercard' => '0.00',
        'amex' => '0.00',
        'voucher' => '0.00',
        'othersamount' => '0.00',
        'gstregistered' => 'Y',
    ]);
});
