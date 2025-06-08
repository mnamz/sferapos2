<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ShopSettingsController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ActivityLogController;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
    Route::resource('suppliers', SupplierController::class);
    Route::get('/api/suppliers/search', [SupplierController::class, 'search'])->name('api.suppliers.search');
    Route::resource('users', UserController::class); 
    Route::get('/shop-settings', [ShopSettingsController::class, 'index'])->name('settings.index');
    Route::post('/shop-settings', [ShopSettingsController::class, 'update'])->name('settings.update');
    Route::get('/orders/{order}/invoice', [InvoiceController::class, 'generate'])->name('orders.invoice');
    Route::post('/orders/{order}/send-invoice', [InvoiceController::class, 'send'])->name('orders.send-invoice');
    Route::get('/orders/create', [\App\Http\Controllers\OrderController::class, 'create'])->name('orders.create');
    Route::get('/sales', [\App\Http\Controllers\OrderController::class, 'mySales'])->name(name: 'sales.index');
    
    // Add low stock products route
    Route::get('/products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');
    Route::get('/products/inventory-cost', [ProductController::class, 'inventoryCost'])->name('products.inventory-cost');
    Route::get('/products/inventory-cost/export', [ProductController::class, 'exportInventoryCost'])->name('products.inventory-cost.export');
    Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');

    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/pos.php';
