<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
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
    }
}
