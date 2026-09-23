<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Role;

function exportUser(string $role): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets admins and managers export the product list without cost', function (string $role) {
    $category = Category::factory()->create(['name' => 'INSTA360']);
    Product::factory()->create([
        'name' => 'Insta360 Mic Pro',
        'category_id' => $category->id,
        'price' => 419,
        'cost_price' => 335,
        'stock' => 5,
    ]);

    $response = $this->actingAs(exportUser($role))->get(route('products.export-list'));

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Name,Category,Price,Stock')
        ->and($csv)->toContain('"Insta360 Mic Pro",INSTA360,419.00,5')
        ->and($csv)->not->toContain('335');
})->with(['admin', 'manager']);

it('forbids staff from exporting the product list', function () {
    $this->actingAs(exportUser('staff'))
        ->get(route('products.export-list'))
        ->assertForbidden();
});
