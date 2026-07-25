<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'status' => 'completed',
        ];
    }
}
