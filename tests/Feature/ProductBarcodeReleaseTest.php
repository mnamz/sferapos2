<?php

use App\Models\Product;
use App\Models\User;

// Deleting a product soft-deletes it, so its row (and barcode) would otherwise
// keep holding the unique barcode forever — blocking the real product from
// ever using it. Deleting must release the barcode.

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('releases the barcode when a product is deleted', function () {
    $product = Product::factory()->create(['barcode' => '6942590200344']);

    $this->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));

    $trashed = Product::withTrashed()->find($product->id);
    expect($trashed->trashed())->toBeTrue();
    expect($trashed->barcode)->toBeNull();
});

it('lets another product take the barcode of a deleted one', function () {
    $old = Product::factory()->create(['barcode' => '6942590200344']);
    $this->delete(route('products.destroy', $old));

    $other = Product::factory()->create(['barcode' => null]);
    $other->update(['barcode' => '6942590200344']);

    expect($other->fresh()->barcode)->toBe('6942590200344');
});

it('keeps the old barcode in the audit trail of the delete', function () {
    config(['audit.console' => true]);
    Product::clearBootedModels();   // auditing decides at boot whether to listen
    $product = Product::factory()->create(['barcode' => '6942590200344']);

    $this->delete(route('products.destroy', $product));

    $audit = $product->audits()->where('event', 'deleted')->latest('id')->first();
    expect($audit->old_values['barcode'])->toBe('6942590200344');
});
