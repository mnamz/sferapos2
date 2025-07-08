<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    protected $fillable = [
        'quote_id', 'product_id', 'product_name', 'quantity', 'price', 'total', 'remark', 'custom_fields'
    ];

    protected $casts = [
        'custom_fields' => 'array',
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
