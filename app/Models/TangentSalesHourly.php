<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TangentSalesHourly extends Model
{
    protected $table = 'tangent_sales_hourly';

    protected $fillable = [
        'sale_date', 'hour', 'receipt_count', 'gto', 'gst', 'discount',
        'service_charge', 'no_of_pax', 'cash', 'tng', 'visa', 'mastercard',
        'amex', 'voucher', 'others_amount', 'gst_registered', 'payload_hash',
        'status', 'synced_at', 'response_status', 'response_body',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'hour' => 'integer',
        'receipt_count' => 'integer',
        'no_of_pax' => 'integer',
        'gto' => 'decimal:2',
        'gst' => 'decimal:2',
        'discount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'cash' => 'decimal:2',
        'tng' => 'decimal:2',
        'visa' => 'decimal:2',
        'mastercard' => 'decimal:2',
        'amex' => 'decimal:2',
        'voucher' => 'decimal:2',
        'others_amount' => 'decimal:2',
        'synced_at' => 'datetime',
        'response_status' => 'integer',
    ];

    /**
     * Build the Tangent SalesHourly record for this hour.
     *
     * @return array<string, string>
     */
    public function toApiRecord(): array
    {
        return [
            'machineid' => (string) config('services.tangent.machine_id'),
            'batchid' => (string) config('services.tangent.batch_id', '1'),
            'date' => $this->sale_date->format('Ymd'),
            'hour' => str_pad((string) $this->hour, 2, '0', STR_PAD_LEFT),
            'receiptcount' => (string) (int) $this->receipt_count,
            'gto' => $this->money($this->gto),
            'gst' => $this->money($this->gst),
            'discount' => $this->money($this->discount),
            'servicecharge' => $this->money($this->service_charge),
            'noofpax' => (string) (int) $this->no_of_pax,
            'cash' => $this->money($this->cash),
            'tng' => $this->money($this->tng),
            'visa' => $this->money($this->visa),
            'mastercard' => $this->money($this->mastercard),
            'amex' => $this->money($this->amex),
            'voucher' => $this->money($this->voucher),
            'othersamount' => $this->money($this->others_amount),
            'gstregistered' => $this->gst_registered ?: 'N',
        ];
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
