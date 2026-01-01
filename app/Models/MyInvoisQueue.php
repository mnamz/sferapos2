<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MyInvoisQueue extends Model
{
    protected $table = 'myinvois_queue';

    protected $fillable = [
        'order_id',
        'invoice_payload',
        'status',
        'myinvois_id',
        'pushed_at',
    ];

    protected $casts = [
        'invoice_payload' => 'array',
        'pushed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
