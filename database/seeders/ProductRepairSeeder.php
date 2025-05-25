<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ProductRepairSeeder extends Seeder
{
    public function run()
    {
        // Read the CSV file
        $csvPath = storage_path('product_repair_mrc.csv');
        $csvContent = File::get($csvPath);
        $rows = array_map('str_getcsv', explode("\n", $csvContent));
        
        // Remove header row
        array_shift($rows);
        
        // Get unique categories from the CSV
        $categories = collect($rows)
            ->filter() // Remove empty rows
            ->map(function ($row) {
                return $row[2]; // Category is in the third column
            })
            ->unique()
            ->values()
            ->toArray();

        // Create categories
        $categoryMap = [];
        foreach ($categories as $categoryName) {
            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                [
                    'description' => "Products for {$categoryName}",
                    'status' => 'active'
                ]
            );
            $categoryMap[$categoryName] = $category->id;
        }

        // Create products
        foreach ($rows as $row) {
            if (empty($row[0])) continue; // Skip empty rows

            $product = [
                'name' => $row[1], // Product name
                'description' => $row[6] ?: null, // Description
                'price' => floatval(str_replace(',', '', $row[4])), // Sale Price
                'cost_price' => floatval(str_replace(',', '', $row[3])), // Purchase Price
                'stock' => intval($row[5]), // Stock
                'category_id' => $categoryMap[$row[2]], // Category
                'status' => 'active',
            ];

            Product::create($product);
        }
    }
} 