<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairJobItem extends Model
{
    protected $fillable = [
        'repair_job_id',
        'product_id',
        'name',
        'item_type',
        'quantity',
        'price',
        'cost_price',
        'warranty_days',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'warranty_days' => 'integer',
    ];

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
