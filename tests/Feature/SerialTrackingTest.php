<?php

use App\Models\Product;
use App\Models\ProductSerial;

it('relates serials to a product and scopes available ones', function () {
    $product = Product::factory()->create(['serial_tracked' => true]);

    ProductSerial::create(['product_id' => $product->id, 'serial_number' => 'SN-A', 'status' => 'available']);
    ProductSerial::create(['product_id' => $product->id, 'serial_number' => 'SN-B', 'status' => 'sold']);

    expect($product->serials()->count())->toBe(2);
    expect($product->serials()->available()->count())->toBe(1);
    expect(ProductSerial::first()->product->id)->toBe($product->id);
});
