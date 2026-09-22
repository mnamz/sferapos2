<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

function exportAdmin(): User
{
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function exportedSheetRows(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    file_put_contents($path, $contents);
    $rows = IOFactory::load($path)->getActiveSheet()->toArray();
    unlink($path);

    return $rows;
}

it('includes serial numbers of order items in the excel export', function () {
    $user = exportAdmin();

    $order = Order::factory()->create(['status' => 'completed', 'user_id' => $user->id]);
    $order->forceFill(['created_at' => '2026-06-15 10:00:00'])->save();

    $product = Product::factory()->create(['name' => 'Insta360 Luna Ultra', 'serial_tracked' => true]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'price' => 100,
        'cost_price' => 50,
        'total' => 200,
        'profit' => 100,
    ]);

    foreach (['BTLB3ABGCWEDJE', 'BTLB3ABGCWEDJF'] as $serial) {
        ProductSerial::create([
            'product_id' => $product->id,
            'serial_number' => $serial,
            'status' => 'sold',
            'order_item_id' => $item->id,
            'order_id' => $order->id,
        ]);
    }

    $response = $this->actingAs($user)->get(route('reports.export', [
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
    ]));

    $response->assertOk();

    $rows = exportedSheetRows($response->streamedContent());
    $header = $rows[0];
    $index = array_search('Serial Numbers', $header, true);

    expect($index)->not->toBeFalse();
    expect($rows[1][$index])->toBe('BTLB3ABGCWEDJE, BTLB3ABGCWEDJF');
});

it('leaves the serial column empty for orders without serial-tracked items', function () {
    $user = exportAdmin();

    $order = Order::factory()->create(['status' => 'completed', 'user_id' => $user->id]);
    $order->forceFill(['created_at' => '2026-06-15 10:00:00'])->save();

    $product = Product::factory()->create(['name' => 'Tripod']);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'price' => 50,
        'cost_price' => 20,
        'total' => 50,
        'profit' => 30,
    ]);

    $response = $this->actingAs($user)->get(route('reports.export', [
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
    ]));

    $response->assertOk();

    $rows = exportedSheetRows($response->streamedContent());
    $index = array_search('Serial Numbers', $rows[0], true);

    expect($rows[1][$index])->toBeNull();
});
