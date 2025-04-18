<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run()
    {
        // Get all category IDs
        $categories = Category::all();

        // Beverages
        $beverages = $categories->where('name', 'Beverages')->first();
        if ($beverages) {
            $this->createBeverages($beverages->id);
        }

        // Food
        $food = $categories->where('name', 'Food')->first();
        if ($food) {
            $this->createFood($food->id);
        }

        // Snacks
        $snacks = $categories->where('name', 'Snacks')->first();
        if ($snacks) {
            $this->createSnacks($snacks->id);
        }

        // Groceries
        $groceries = $categories->where('name', 'Groceries')->first();
        if ($groceries) {
            $this->createGroceries($groceries->id);
        }

        // Electronics
        $electronics = $categories->where('name', 'Electronics')->first();
        if ($electronics) {
            $this->createElectronics($electronics->id);
        }
    }

    private function createBeverages($categoryId)
    {
        $products = [
            [
                'name' => 'Coca Cola 330ml',
                'description' => 'Classic Coca Cola can',
                'price' => 1.50,
                'stock' => 100,
                'barcode' => '5449000000996',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
            [
                'name' => 'Mineral Water 500ml',
                'description' => 'Pure mineral water bottle',
                'price' => 1.00,
                'stock' => 150,
                'barcode' => '5449000001023',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
            [
                'name' => 'Orange Juice 1L',
                'description' => 'Fresh orange juice',
                'price' => 2.50,
                'stock' => 50,
                'barcode' => '5449000001030',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }

    private function createFood($categoryId)
    {
        $products = [
            [
                'name' => 'Chicken Sandwich',
                'description' => 'Fresh chicken sandwich',
                'price' => 4.99,
                'stock' => 20,
                'barcode' => '5449000002001',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
            [
                'name' => 'Pizza Slice',
                'description' => 'Cheese pizza slice',
                'price' => 3.50,
                'stock' => 30,
                'barcode' => '5449000002018',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
            [
                'name' => 'Salad Bowl',
                'description' => 'Fresh garden salad',
                'price' => 5.99,
                'stock' => 15,
                'barcode' => '5449000002025',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }

    private function createSnacks($categoryId)
    {
        $products = [
            [
                'name' => 'Lays Classic',
                'description' => 'Classic potato chips',
                'price' => 1.99,
                'stock' => 80,
                'barcode' => '5449000003001',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
            [
                'name' => 'Snickers Bar',
                'description' => 'Chocolate bar',
                'price' => 1.20,
                'stock' => 100,
                'barcode' => '5449000003018',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
            [
                'name' => 'Pringles Original',
                'description' => 'Original flavor chips',
                'price' => 2.49,
                'stock' => 60,
                'barcode' => '5449000003025',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }

    private function createGroceries($categoryId)
    {
        $products = [
            [
                'name' => 'Milk 1L',
                'description' => 'Fresh whole milk',
                'price' => 2.99,
                'stock' => 40,
                'barcode' => '5449000004001',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
            [
                'name' => 'Bread',
                'description' => 'White bread loaf',
                'price' => 1.99,
                'stock' => 30,
                'barcode' => '5449000004018',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
            [
                'name' => 'Eggs 12pk',
                'description' => 'Fresh large eggs',
                'price' => 3.49,
                'stock' => 25,
                'barcode' => '5449000004025',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }

    private function createElectronics($categoryId)
    {
        $products = [
            [
                'name' => 'USB Cable',
                'description' => 'Type-C USB cable',
                'price' => 9.99,
                'stock' => 50,
                'barcode' => '5449000005001',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
            [
                'name' => 'Power Bank',
                'description' => '10000mAh power bank',
                'price' => 24.99,
                'stock' => 20,
                'barcode' => '5449000005018',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
            [
                'name' => 'Earphones',
                'description' => 'Wired earphones',
                'price' => 14.99,
                'stock' => 30,
                'barcode' => '5449000005025',
                'category_id' => $categoryId,
                'status' => 'active',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
} 