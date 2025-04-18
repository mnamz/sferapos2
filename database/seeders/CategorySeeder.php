<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => 'Beverages',
                'description' => 'Drinks, sodas, and other beverages',
                'status' => 'active',
            ],
            [
                'name' => 'Food',
                'description' => 'Ready-to-eat food items',
                'status' => 'active',
            ],
            [
                'name' => 'Snacks',
                'description' => 'Chips, candies, and other snack items',
                'status' => 'active',
            ],
            [
                'name' => 'Groceries',
                'description' => 'Basic grocery items',
                'status' => 'active',
            ],
            [
                'name' => 'Electronics',
                'description' => 'Electronic devices and accessories',
                'status' => 'active',
            ],
            [
                'name' => 'Household',
                'description' => 'Household items and supplies',
                'status' => 'active',
            ],
            [
                'name' => 'Personal Care',
                'description' => 'Personal hygiene and care products',
                'status' => 'active',
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}