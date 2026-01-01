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
        'invoice_logo_path',
        'company_number',
        'tax_number',
        'identification_number',
        'identification_scheme',
        'industry_classification_code',
        'industry_classification_name',
        'payment_details',
        'footer_text'
    ];

    protected $casts = [
        'tax_percentage' => 'decimal:2',
    ];
} 