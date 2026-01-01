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
use App\Http\Controllers\AccountingController;

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
    Route::get('/orders/{order}/e-invoice', [OrderController::class, 'eInvoice'])->name('orders.eInvoice');
    Route::get('/orders/{order}/e-invoice/pdf', [OrderController::class, 'eInvoicePdf'])->name('orders.eInvoicePdf');
    Route::post('/orders/{order}/send-invoice', [InvoiceController::class, 'send'])->name('orders.send-invoice');
    Route::get('/orders/create', [\App\Http\Controllers\OrderController::class, 'create'])->name('orders.create');
    Route::get('/sales', [\App\Http\Controllers\OrderController::class, 'mySales'])->name(name: 'sales.index');
    Route::resource('quotes', QuoteController::class);
    Route::get('quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');
    
    // Add low stock products route
    Route::get('/products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');
    Route::get('/products/inventory-cost', [ProductController::class, 'inventoryCost'])->name('products.inventory-cost');
    Route::get('/products/inventory-cost/export', [ProductController::class, 'exportInventoryCost'])->name('products.inventory-cost.export');
    Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');
    Route::get('/api/products/search', [ProductController::class, 'search'])->name('api.products.search');

    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::put('/orders/{order}/delivery', [OrderController::class, 'updateDelivery'])->name('orders.updateDelivery');
    Route::put('/orders/{order}/customer', [OrderController::class, 'updateCustomer'])->name('orders.updateCustomer');
    Route::post('/orders/{order}/consolidation', [OrderController::class, 'addToConsolidation'])->name('orders.addToConsolidation');
    Route::post('/orders/{order}/push-myinvois', [OrderController::class, 'pushToMyInvois'])->name('orders.pushMyInvois');
    Route::post('/orders/{order}/clear-queue', [OrderController::class, 'clearFromQueue'])->name('orders.clearQueue');
    Route::post('/orders/{order}/add-to-queue', [OrderController::class, 'addToQueue'])->name('orders.addToQueue');
    Route::put('/orders/{order}/cancel-myinvois', [OrderController::class, 'cancelMyInvoisInvoice'])->name('orders.cancelMyInvois');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    
    // MyInvois Push Status
    Route::get('/myinvois/push-status', [App\Http\Controllers\MyInvoisPushStatusController::class, 'index'])->name('myinvois.pushStatus');
    Route::post('/myinvois/push', [App\Http\Controllers\MyInvoisPushStatusController::class, 'push'])->name('myinvois.push');
    
    // MyInvois Consolidated Invoices
    Route::get('/myinvois/consolidated', [App\Http\Controllers\MyInvoisInvoiceController::class, 'index'])->name('myinvois.consolidated.index');
    Route::get('/myinvois/consolidated/{myInvoisInvoice}', [App\Http\Controllers\MyInvoisInvoiceController::class, 'show'])->name('myinvois.consolidated.show');
    Route::get('/orders/export-csv', [OrderController::class, 'exportCsv'])
        ->middleware(['auth'])
        ->name('orders.exportCsv');

    // Accounting module (admin only)
    // Route::middleware(['role:admin'])->group(function () {
        Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting.index');
        Route::post('/accounting/entries', [AccountingController::class, 'store'])->name('accounting.entries.store');
        Route::put('/accounting/entries/{entry}', [AccountingController::class, 'update'])->name('accounting.entries.update');
        Route::delete('/accounting/entries/{entry}', [AccountingController::class, 'destroy'])->name('accounting.entries.destroy');
        Route::get('/accounting/categories', [AccountingController::class, 'categoriesIndex'])->name('accounting.categories');
        Route::post('/accounting/categories', [AccountingController::class, 'categoriesStore'])->name('accounting.categories.store');
        Route::put('/accounting/categories/{category}', [AccountingController::class, 'categoriesUpdate'])->name('accounting.categories.update');
        Route::delete('/accounting/categories/{category}', [AccountingController::class, 'categoriesDestroy'])->name('accounting.categories.destroy');
        Route::post('/accounting/sync-orders', [AccountingController::class, 'syncFromOrders'])->name('accounting.sync');
    // });
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
