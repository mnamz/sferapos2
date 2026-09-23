<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RepairJobController;
use App\Http\Controllers\ShopSettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    // POS Routes — the service-shop build uses the unified sale screen.
    Route::get('/pos', fn () => redirect()->route('orders.create'))->name('pos.index');

    // Repair jobs (service tickets)
    Route::get('repairs/{repair}/print', [RepairJobController::class, 'print'])->name('repairs.print');
    Route::patch('repairs/{repair}/quick', [RepairJobController::class, 'patch'])->name('repairs.patch');
    Route::post('repairs/{repair}/status', [RepairJobController::class, 'updateStatus'])->name('repairs.status');
    Route::post('repairs/{repair}/notes', [RepairJobController::class, 'addNote'])->name('repairs.notes');
    Route::post('repairs/{repair}/items', [RepairJobController::class, 'addItem'])->name('repairs.items.store');
    Route::delete('repairs/{repair}/items/{item}', [RepairJobController::class, 'removeItem'])->name('repairs.items.destroy');
    Route::resource('repairs', RepairJobController::class)->except(['destroy']);
    Route::post('/api/customers/quick', [CustomerController::class, 'quickStore'])->name('customers.quick');

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
        Route::delete('repairs/{repair}', [RepairJobController::class, 'destroy'])->name('repairs.destroy');
        Route::post('products/{product}/serials', [ProductController::class, 'addSerials'])->name('products.serials.store');
        Route::delete('products/{product}/serials/{serial}', [ProductController::class, 'removeSerial'])->name('products.serials.destroy');
    });
});
