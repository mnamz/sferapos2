<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MyInvoisInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'submission_uid',
        'uuid',
        'invoice_code_number',
        'request_payload',
        'response_payload'
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
} 