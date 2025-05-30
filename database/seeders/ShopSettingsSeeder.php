<?php

namespace Database\Seeders;

use App\Models\ShopSettings;
use Illuminate\Database\Seeder;

class ShopSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ShopSettings::create([
            'shop_name' => 'SferaPOS',
            'shop_address' => '123 Main Street, City, Country',
            'shop_phone' => '+1234567890',
            'shop_email' => 'info@sferapos.com',
            'currency' => 'RM',
            'tax_percentage' => 10.00,
            'default_payment_method' => 'cash',
            'default_delivery_method' => 'pickup',
            'default_delivery_cost' => 10.00,
            'receipt_header' => 'Thank you for shopping with us!',
            'receipt_footer' => 'Please come again!',
            'timezone' => 'UTC',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'low_stock_threshold' => 10,
            'enable_guest_checkout' => true,
            'enable_tax' => true,
            'enable_delivery' => true,
        ]);
    }
} 