<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Share current user's permissions to all views
        View::composer('*', function ($view) {
            $user = auth()->user();
            if ($user) {
                // Share flat array of permission names for easy checking
                $permissions = $user->getAllPermissions()->pluck('name')->toArray();
                $view->with('userPermissions', $permissions);
                $view->with('userRoles', $user->getRoleNames()->toArray());
            }
        });
    }
}
