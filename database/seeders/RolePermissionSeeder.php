<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Get menu configuration
        $menus = config('admin_menus.menus');

        // Generate permissions from menu config
        // Format: "{menu_id}.{action}" e.g., "dashboard.view", "orders.create"
        $permissionNames = [];
        foreach ($menus as $menu) {
            foreach ($menu['actions'] as $action) {
                $permName = "{$menu['id']}.{$action}";
                $permissionNames[] = $permName;
                Permission::firstOrCreate(['name' => $permName]);
            }
        }

        // ─── Roles ──────────────────────────────────────────────

        // Super Admin: all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin: most permissions except system management
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $adminPermissions = array_filter($permissionNames, function ($perm) {
            // Admin can't manage roles, permissions, or documentation
            return !in_array($perm, [
                'roles.create',
                'roles.edit',
                'roles.delete',
                'permissions.create',
                'permissions.edit',
                'permissions.delete',
            ]);
        });
        $admin->syncPermissions($adminPermissions);

        // Operator: operational permissions
        $operator = Role::firstOrCreate(['name' => 'operator']);
        $operatorPermissions = [
            'dashboard.view',
            'couriers.view',
            'orders.view',
            'orders.edit',
            'shipments.view',
            'shipments.edit',
            'drop_points.view',
            'ci_work.view',
            'ci_work_attendance.view',
            'ci_work_tasks.view',
            'ci_work_finance.view',
            'users.view',
        ];
        $operator->syncPermissions(array_filter($permissionNames, function ($perm) use ($operatorPermissions) {
            return in_array($perm, $operatorPermissions);
        }));

        // Viewer: read-only
        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewerPermissions = array_filter($permissionNames, function ($perm) {
            return str_ends_with($perm, '.view');
        });
        $viewer->syncPermissions($viewerPermissions);

        // Courier & Customer: no admin permissions
        Role::firstOrCreate(['name' => 'courier']);
        Role::firstOrCreate(['name' => 'customer']);

        // ─── Default Super Admin User ───────────────────────────
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@citycourier.com'],
            [
                'name' => 'Super Admin',
                'phone' => '00000000000',
                'password' => bcrypt('password123'),
                'role' => 'admin',
            ]
        );

        $adminUser->assignRole('super-admin');
    }
}
