<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role_id' => 1,
            'status' => true,
        ]);

        $this->call([
            ShopSettingsSeeder::class, // First, create shop settings
            CategorySeeder::class,      // Then create categories
            ProductSeeder::class,       // Then create products (needs categories)
            CustomerSeeder::class,      // Finally create customers
        ]);
    }
}
