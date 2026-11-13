<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RepairJob;
use App\Models\ShopSettings;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Self-contained sample data for a throwaway phone-shop demo instance
 * (provisioned by deploy/demo/demo.sh). Needs no CSV files.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // No factories: Faker is a dev dependency and demos run --no-dev.
        $demo = User::forceCreate([
            'name' => 'Demo Owner',
            'email' => config('app.demo.email'),
            'password' => Hash::make(config('app.demo.password')),
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $demo->assignRole('admin');

        $tech = User::forceCreate([
            'name' => 'Hafiz (Technician)',
            'email' => 'tech@demo.local',
            'password' => Hash::make(str()->random(32)),
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $tech->assignRole('staff');

        ShopSettings::create([
            'shop_name' => config('app.name'),
            'shop_address' => 'Lot 12, Jalan Demo 1, 50000 Kuala Lumpur',
            'shop_phone' => '+60312345678',
            'shop_email' => 'hello@demo.local',
            'currency' => 'RM',
            'tax_percentage' => 0,
            'footer_text' => 'Thank you! This is a demo receipt.',
            'repair_terms' => 'Devices not collected within 30 days may be disposed of. Warranty covers replaced parts only.',
            'default_warranty_days' => 30,
        ]);

        $categories = collect(['Phones', 'Accessories', 'Spare Parts', 'Services'])
            ->mapWithKeys(fn ($name) => [$name => Category::create([
                'name' => $name,
                'description' => "Demo {$name}",
                'status' => 'active',
            ])->id]);

        $products = [
            // [name, type, category, cost, price, stock, brand, warranty]
            ['iPhone 15 128GB', 'product', 'Phones', 3300, 3899, 4, 'Apple', 365],
            ['Samsung Galaxy A55 256GB', 'product', 'Phones', 1350, 1699, 6, 'Samsung', 365],
            ['Xiaomi Redmi Note 13', 'product', 'Phones', 650, 899, 8, 'Xiaomi', 365],
            ['20W USB-C Charger', 'product', 'Accessories', 18, 49, 40, 'Anker', 180],
            ['USB-C to Lightning Cable 1m', 'product', 'Accessories', 8, 29, 60, 'Ugreen', 90],
            ['Tempered Glass (universal)', 'product', 'Accessories', 2, 25, 120, null, 0],
            ['Clear Silicone Case', 'product', 'Accessories', 4, 29, 80, null, 0],
            ['iPhone 13 Screen (OLED)', 'part', 'Spare Parts', 220, 280, 5, 'Apple', 90],
            ['iPhone 11 Battery', 'part', 'Spare Parts', 45, 80, 10, 'Apple', 90],
            ['Galaxy A54 Charging Port Board', 'part', 'Spare Parts', 30, 60, 6, 'Samsung', 90],
            ['Screen Replacement (labour)', 'service', 'Services', 0, 120, 0, null, 30],
            ['Battery Replacement (labour)', 'service', 'Services', 0, 60, 0, null, 30],
            ['Diagnosis Fee', 'service', 'Services', 0, 30, 0, null, 0],
            ['Data Transfer / Backup', 'service', 'Services', 0, 50, 0, null, 0],
        ];

        foreach ($products as [$name, $type, $category, $cost, $price, $stock, $brand, $warranty]) {
            Product::create([
                'name' => $name,
                'type' => $type,
                'category_id' => $categories[$category],
                'cost_price' => $cost,
                'price' => $price,
                'stock' => $stock,
                'brand' => $brand,
                'warranty_days' => $warranty,
                'status' => 'active',
            ]);
        }

        $customers = collect([
            ['Walk-in Customer', null, null],
            ['Aiman Zulkifli', '+60123456701', 'aiman@example.com'],
            ['Mei Ling Tan', '+60123456702', 'meiling@example.com'],
            ['Ravi Kumar', '+60123456703', null],
            ['Nurul Huda', '+60123456704', 'nurul@example.com'],
        ])->map(fn ($c) => Customer::create([
            'name' => $c[0],
            'phone' => $c[1],
            'email' => $c[2],
            'status' => 'active',
        ]));

        $jobs = [
            // [customer idx, brand, model, issue, status, estimate, days ago]
            [1, 'Apple', 'iPhone 13', 'Cracked screen, touch still works', 'in_progress', 340, 1],
            [2, 'Apple', 'iPhone 11', 'Battery drains fast, health 71%', 'ready', 150, 2],
            [3, 'Samsung', 'Galaxy A54', 'Not charging, port loose', 'awaiting_parts', 120, 3],
            [4, 'Xiaomi', 'Redmi Note 12', 'Dropped in water, no power', 'diagnosing', 30, 0],
        ];

        foreach ($jobs as [$customer, $brand, $model, $issue, $status, $estimate, $daysAgo]) {
            $job = RepairJob::create([
                'job_number' => RepairJob::nextJobNumber(),
                'customer_id' => $customers[$customer]->id,
                'user_id' => $demo->id,
                'technician_id' => $tech->id,
                'device_type' => 'phone',
                'brand' => $brand,
                'model' => $model,
                'issue' => $issue,
                'status' => $status,
                'estimated_cost' => $estimate,
                'promised_at' => now()->addDays(2),
            ]);
            $job->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();
        }
    }
}
