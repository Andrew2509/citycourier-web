<?php

namespace App\Providers;

use App\Models\Courier;
use App\Models\Order;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

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
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Share sidebar badge counts with all admin views
        View::composer('layouts.admin', function ($view) {
            if (Auth::check()) {
                $view->with([
                    'unverified' => Courier::where('is_verified', false)->count(),
                    'pendingOrders' => Order::where('status', 'pending')->count(),
                ]);
            }
        });
    }
}
