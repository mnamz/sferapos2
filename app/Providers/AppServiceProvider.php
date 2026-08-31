<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Observers\OrderObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductSerialObserver;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Inertia::share([
            'auth' => function () {
                $user = auth()->user();
                return [
                    'user' => $user,
                    'roles' => $user ? $user->getRoleNames() : [],
                    'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
                ];
            },
        ]);

        // Push sales to the central Stock HQ dashboard (queued; no-op unless configured).
        Order::observe(OrderObserver::class);

        // Push catalogue changes (create/edit/restore) to HQ. Guarded so sales,
        // which only touch stock levels, never trigger a push.
        Product::observe(ProductObserver::class);

        // Live-sync serial receipts (created) and removals (deleted) to HQ.
        // A serial going to 'sold' is a sale, carried by the order event instead.
        ProductSerial::observe(ProductSerialObserver::class);
    }
}
