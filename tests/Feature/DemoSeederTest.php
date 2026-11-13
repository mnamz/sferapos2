<?php

use App\Models\Product;
use App\Models\RepairJob;
use App\Models\User;
use Database\Seeders\DemoSeeder;

it('seeds a usable demo shop with the configured demo login', function () {
    config(['app.demo.email' => 'demo@sfera.my', 'app.demo.password' => 'demo1234']);

    $this->seed(DemoSeeder::class);

    $demo = User::where('email', 'demo@sfera.my')->firstOrFail();
    expect($demo->hasRole('admin'))->toBeTrue()
        ->and(Hash::check('demo1234', $demo->password))->toBeTrue()
        ->and(Product::where('type', 'service')->count())->toBeGreaterThan(0)
        ->and(RepairJob::count())->toBe(4);

    $this->actingAs($demo)->get(route('repairs.index'))->assertOk();
});
