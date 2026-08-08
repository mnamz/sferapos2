<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductSerial;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductSerialFactory extends Factory
{
    protected $model = ProductSerial::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'serial_number' => strtoupper($this->faker->unique()->bothify('SN-####-????')),
            'status' => 'available',
        ];
    }
}
