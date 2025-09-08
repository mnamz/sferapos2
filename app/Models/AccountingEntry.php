<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'order_id',
        'date',
        'due_date',
        'amount',
        'type', // credit | debit
        'ar_ap_type', // AR | AP
        'description',
        'party_id',
        'party_name',
        'reference',
        'is_payroll',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'datetime',
        'due_date' => 'datetime',
        'is_payroll' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(AccountingCategory::class, 'category_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}


