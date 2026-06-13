<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MyInvoisCreditNote extends Model
{
    use HasFactory;

    protected $table = 'myinvois_credit_notes';

    protected $fillable = [
        'order_id',
        'myinvois_invoice_id',
        'submission_uid',
        'uuid',
        'credit_note_code_number',
        'reason',
        'request_payload',
        'response_payload',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function originalInvoice()
    {
        return $this->belongsTo(MyInvoisInvoice::class, 'myinvois_invoice_id');
    }
}
