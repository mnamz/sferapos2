<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopSettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    // POS Routes
    Route::get('/pos', function () {
        return Inertia::render('Pos/Index');
    })->name('pos.index');

    // Products Routes
    Route::resource('products', ProductController::class)->except(['edit', 'update']);
    Route::get('/pos-products', [ProductController::class, 'getPosProducts'])->name('pos.products');
    Route::get('products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');
    Route::get('products/{product}/serials', [ProductController::class, 'getSerials'])->name('products.serials.index');

    // Categories Routes
    Route::resource('categories', CategoryController::class)->except(['edit', 'update']);

    Route::resource('customers', CustomerController::class)->except(['edit', 'update']);
    Route::get('/api/customers/search', [CustomerController::class, 'search'])->name('customers.search');

    // Orders Routes
    Route::resource('orders', OrderController::class)->except(['edit', 'update']);

    // Shop Settings Routes (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/shop-settings', [ShopSettingsController::class, 'index'])->name('pos.settings');
        Route::post('/shop-settings', [ShopSettingsController::class, 'update'])->name('pos.settings.update');

        // Approving/rejecting a manager's serial-deletion request is admin-only.
        Route::post('products/{product}/serials/{serial}/approve-deletion', [ProductController::class, 'approveSerialDeletion'])->name('products.serials.approve-deletion');
        Route::post('products/{product}/serials/{serial}/reject-deletion', [ProductController::class, 'rejectSerialDeletion'])->name('products.serials.reject-deletion');
    });

    // Edit/update routes restricted to admin and manager
    Route::middleware('role:admin|manager')->group(function () {
        Route::resource('products', ProductController::class)->only(['edit', 'update']);
        Route::resource('categories', CategoryController::class)->only(['edit', 'update']);
        Route::resource('customers', CustomerController::class)->only(['edit', 'update']);
        Route::resource('orders', OrderController::class)->only(['edit', 'update']);
        Route::post('products/{product}/serials', [ProductController::class, 'addSerials'])->name('products.serials.store');
        Route::delete('products/{product}/serials/{serial}', [ProductController::class, 'removeSerial'])->name('products.serials.destroy');
    });
});
