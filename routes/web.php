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
use App\Http\Controllers\BackupController;
use App\Http\Controllers\QuoteController;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::resource('suppliers', SupplierController::class)->except(['edit', 'update']);
    Route::get('/api/suppliers/search', [SupplierController::class, 'search'])->name('api.suppliers.search');
    Route::resource('users', UserController::class)->except(['edit', 'update']);

    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
        Route::get('/shop-settings', [ShopSettingsController::class, 'index'])->name('settings.index');
        Route::post('/shop-settings', [ShopSettingsController::class, 'update'])->name('settings.update');
    });
    Route::get('/orders/{order}/invoice', [InvoiceController::class, 'generate'])->name('orders.invoice');
    Route::get('/orders/{order}/e-invoice', [OrderController::class, 'eInvoice'])->name('orders.eInvoice');
    Route::get('/orders/{order}/e-invoice/pdf', [OrderController::class, 'eInvoicePdf'])->name('orders.eInvoicePdf');
    Route::post('/orders/{order}/send-invoice', [InvoiceController::class, 'send'])->name('orders.send-invoice');
    Route::get('/orders/create', [\App\Http\Controllers\OrderController::class, 'create'])->name('orders.create');
    Route::get('/sales', [\App\Http\Controllers\OrderController::class, 'mySales'])->name(name: 'sales.index');
    Route::resource('quotes', QuoteController::class)->except(['edit', 'update']);
    Route::get('quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');

    // Add low stock products route
    Route::get('/products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');
    Route::get('/products/inventory-cost', [ProductController::class, 'inventoryCost'])->name('products.inventory-cost');
    Route::get('/products/inventory-cost/export', [ProductController::class, 'exportInventoryCost'])->name('products.inventory-cost.export');
    Route::get('/products/report', [ProductController::class, 'report'])->name('products.report');
    Route::get('/products/report/export', [ProductController::class, 'exportReport'])->name('products.report.export');
    Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');
    Route::get('/api/products/search', [ProductController::class, 'search'])->name('api.products.search');

    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::post('/orders/{order}/push-myinvois', [OrderController::class, 'pushToMyInvois'])->name('orders.pushMyInvois');
    Route::post('/orders/{order}/clear-queue', [OrderController::class, 'clearFromQueue'])->name('orders.clearQueue');
    Route::post('/orders/{order}/add-to-queue', [OrderController::class, 'addToQueue'])->name('orders.addToQueue');
    Route::put('/orders/{order}/cancel-myinvois', [OrderController::class, 'cancelMyInvoisInvoice'])->name('orders.cancelMyInvois');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    Route::get('/orders/export-csv', [OrderController::class, 'exportCsv'])
        ->middleware(['auth'])
        ->name('orders.exportCsv');

    // Edit/update routes restricted to admin and manager
    Route::middleware('role:admin|manager')->group(function () {
        Route::resource('suppliers', SupplierController::class)->only(['edit', 'update']);
        Route::resource('users', UserController::class)->only(['edit', 'update']);
        Route::resource('quotes', QuoteController::class)->only(['edit', 'update']);
    });
});

Route::get('/backup/sql', [BackupController::class, 'downloadSql'])
    ->middleware(['auth'])
    ->name('backup.sql');

// API endpoint for external systems to submit MyInvois invoice with custom customer info
// Excluded from CSRF protection for external API access
Route::post('/api/orders/{orderId}/submit-myinvois', [OrderController::class, 'apiSubmitMyInvois'])
    ->withoutMiddleware(['web'])
    ->name('api.orders.submitMyInvois');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/pos.php';
