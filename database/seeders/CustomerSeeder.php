<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run()
    {
        $customers = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '1234567890',
                'address' => '123 Main St, City',
                'status' => 'active',
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'phone' => '0987654321',
                'address' => '456 Oak Ave, Town',
                'status' => 'active',
            ],
            [
                'name' => 'Bob Johnson',
                'email' => 'bob@example.com',
                'phone' => '5551234567',
                'address' => '789 Pine Rd, Village',
                'status' => 'active',
            ],
            [
                'name' => 'Alice Brown',
                'email' => 'alice@example.com',
                'phone' => '4447890123',
                'address' => '321 Elm St, County',
                'status' => 'active',
            ],
            [
                'name' => 'Charlie Wilson',
                'email' => 'charlie@example.com',
                'phone' => '9990001234',
                'address' => '654 Maple Dr, State',
                'status' => 'active',
            ],
            [
                'name' => 'Walk-in Customer',
                'email' => null,
                'phone' => null,
                'address' => null,
                'status' => 'active',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::create($customer);
        }
    }
} 