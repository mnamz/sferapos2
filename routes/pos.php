<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ShopSettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    // POS Routes
    Route::get('/pos', function () {
        return Inertia::render('Pos/Index');
    })->name('pos.index');

    // Products Routes
    Route::resource('products', ProductController::class);
    Route::get('/pos-products', [ProductController::class, 'getPosProducts'])->name('pos.products');
    Route::get('products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');

    // Categories Routes
    Route::resource('categories', CategoryController::class);

    Route::resource('customers', CustomerController::class);
    Route::get('/api/customers/search', [CustomerController::class, 'search'])->name('customers.search');

    // Orders Routes
    Route::resource('orders', OrderController::class);

    // Shop Settings Routes
    Route::get('/shop-settings', [ShopSettingsController::class, 'index'])->name('pos.settings');
    Route::post('/shop-settings', [ShopSettingsController::class, 'update'])->name('pos.settings.update');
}); 