<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSettings extends Model
{
    protected $table = 'shop_settings';
    
    protected $fillable = [
        'shop_name',
        'shop_address',
        'shop_phone',
        'shop_email',
        'currency',
        'tax_percentage',
        'logo_path',
        'company_number',
        'tax_number',
        'payment_details',
        'footer_text'
    ];

    protected $casts = [
        'tax_percentage' => 'decimal:2',
    ];
} 