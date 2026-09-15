<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Automatically checks if the authenticated user has the required permission
 * for the current route, based on config/admin_menus.php.
 * 
 * Route name mapping:
 *   admin.dashboard       → dashboard.view
 *   admin.orders          → orders.view
 *   admin.orders.destroy  → orders.delete
 *   admin.shipments.index → shipments.view
 *   admin.shipments.show  → shipments.view
 *   admin.shipments.update / addLog → shipments.edit
 *   admin.shipments.destroy → shipments.delete
 *   etc.
 */
class CheckMenuPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super-admin bypasses all permission checks
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        $permission = $this->resolvePermission($request->route()->getName());

        if ($permission && !$user->hasPermissionTo($permission)) {
            // If AJAX request, return JSON
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk mengakses halaman ini.',
                ], 403);
            }

            return redirect()->route('admin.dashboard')
                ->with('error', 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        return $next($request);
    }

    /**
     * Resolve the permission name from the route name.
     */
    private function resolvePermission(?string $routeName): ?string
    {
        if (!$routeName) return null;

        // Map route name prefixes to permission menu IDs
        $routePermissionMap = [
            // Dashboard
            'admin.dashboard' => 'dashboard.view',

            // Couriers
            'admin.couriers' => 'couriers.view',
            'admin.couriers.export' => 'couriers.view',
            'admin.couriers.store' => 'couriers.create',
            'admin.couriers.update' => 'couriers.edit',
            'admin.couriers.verify' => 'couriers.edit',
            'admin.couriers.toggle-active' => 'couriers.edit',

            // Orders
            'admin.orders' => 'orders.view',
            'admin.orders.detail' => 'orders.view',
            'admin.orders.destroy' => 'orders.delete',

            // Shipments
            'admin.shipments.index' => 'shipments.view',
            'admin.shipments.show' => 'shipments.view',
            'admin.shipments.update' => 'shipments.edit',
            'admin.shipments.logs.store' => 'shipments.edit',
            'admin.shipments.destroy' => 'shipments.delete',
            'admin.shipments.destroy-all' => 'shipments.delete',

            // Drop Points
            'admin.drop-points.index' => 'drop_points.view',
            'admin.drop-points.create' => 'drop_points.create',
            'admin.drop-points.store' => 'drop_points.create',
            'admin.drop-points.show' => 'drop_points.view',
            'admin.drop-points.edit' => 'drop_points.edit',
            'admin.drop-points.update' => 'drop_points.edit',
            'admin.drop-points.destroy' => 'drop_points.delete',
            'admin.drop-points.export' => 'drop_points.view',
            'admin.drop-points.toggle-active' => 'drop_points.edit',
            'admin.drop-points.radius' => 'drop_points.edit',

            // Ci-Work
            'admin.ci-work.index' => 'ci_work.view',
            'admin.ci-work.tasks.refresh' => 'ci_work.view',
            'admin.ci-work.auto-assign' => 'ci_work.edit',
            'admin.ci-work.queue.assign' => 'ci_work.edit',
            'admin.ci-work.attendance' => 'ci_work_attendance.view',
            'admin.ci-work.attendance.locations' => 'ci_work_attendance.view',
            'admin.ci-work.attendance.export' => 'ci_work_attendance.view',
            'admin.ci-work.attendance.toggle-active' => 'ci_work_attendance.edit',
            'admin.ci-work.tasks' => 'ci_work_tasks.view',
            'admin.ci-work.finance' => 'ci_work_finance.view',
            'admin.ci-work.finance.commission' => 'ci_work_finance.edit',
            'admin.ci-work.finance.export' => 'ci_work_finance.view',
            'admin.ci-work.finance.reconcile' => 'ci_work_finance.edit',
            'admin.ci-work.finance.withdrawal.update' => 'ci_work_finance.edit',

            // Users
            'admin.users.index' => 'users.view',
            'admin.users.create' => 'users.create',
            'admin.users.store' => 'users.create',
            'admin.users.show' => 'users.view',
            'admin.users.edit' => 'users.edit',
            'admin.users.update' => 'users.edit',
            'admin.users.destroy' => 'users.delete',

            // Roles
            'admin.roles.index' => 'roles.view',
            'admin.roles.create' => 'roles.create',
            'admin.roles.store' => 'roles.create',
            'admin.roles.show' => 'roles.view',
            'admin.roles.edit' => 'roles.edit',
            'admin.roles.update' => 'roles.edit',
            'admin.roles.destroy' => 'roles.delete',

            // Permissions
            'admin.permissions.index' => 'permissions.view',
            'admin.permissions.create' => 'permissions.create',
            'admin.permissions.store' => 'permissions.create',
            'admin.permissions.show' => 'permissions.view',
            'admin.permissions.edit' => 'permissions.edit',
            'admin.permissions.update' => 'permissions.edit',
            'admin.permissions.destroy' => 'permissions.delete',

            // Settings
            'admin.settings.providers' => 'settings.view',
            'admin.settings.whatsapp' => 'settings.view',
            'admin.settings.whatsapp.update' => 'settings.edit',
            'admin.settings.whatsapp.test' => 'settings.edit',
            'admin.settings.rajaongkir' => 'settings.view',
            'admin.settings.rajaongkir.update' => 'settings.edit',
            'admin.settings.rajaongkir.test' => 'settings.edit',
            'admin.settings.payment' => 'settings.view',
            'admin.settings.payment.update' => 'settings.edit',
            'admin.settings.payment.test' => 'settings.edit',
            'admin.settings.map' => 'settings.view',
            'admin.settings.map.update' => 'settings.edit',
            'admin.settings.map.test' => 'settings.edit',
            'admin.settings.dana' => 'settings.view',
            'admin.settings.dana.update' => 'settings.edit',
            'admin.settings.dana.test' => 'settings.edit',

            // App Download
            'admin.app-download' => 'app_download.view',
            'admin.app-download.store' => 'app_download.create',
            'admin.app-download.destroy' => 'app_download.delete',
            'admin.app-download.set-active' => 'app_download.edit',
        ];

        // Try exact match first
        if (isset($routePermissionMap[$routeName])) {
            return $routePermissionMap[$routeName];
        }

        // Try prefix match (for resource routes)
        $parts = explode('.', $routeName);
        while (count($parts) > 2) {
            array_pop($parts);
            $prefix = implode('.', $parts);
            if (isset($routePermissionMap[$prefix])) {
                return $routePermissionMap[$prefix];
            }
        }

        // Fallback: check if route starts with admin. and try to match menu ID
        if (str_starts_with($routeName, 'admin.')) {
            $menuPart = $parts[1] ?? '';
            $menuId = str_replace('-', '_', $menuPart);
            $menus = config('admin_menus.menus', []);
            foreach ($menus as $menu) {
                if ($menu['id'] === $menuId) {
                    return "{$menuId}.view";
                }
            }
        }

        return null;
    }
}
