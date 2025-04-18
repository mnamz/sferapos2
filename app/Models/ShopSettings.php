<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSettings extends Model
{
    protected $table = 'shop_settings';
    
    protected $fillable = [
        'currency',
        'tax_percentage',
        // Add other settings fields here
    ];

    protected $casts = [
        'tax_percentage' => 'decimal:2',
    ];
} 