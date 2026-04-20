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

        // Create Default Permissions
        $permissions = [
            'view dashboard',
            'manage couriers',
            'verify couriers',
            'manage orders',
            'manage users',
            'manage roles',
            'manage permissions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Roles and Assign Permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        // super-admin gets all permissions via implicit check or explicit assignment
        $superAdminRole->syncPermissions(Permission::all());

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions([
            'view dashboard',
            'manage couriers',
            'verify couriers',
            'manage orders',
        ]);

        $courierRole = Role::firstOrCreate(['name' => 'courier']);
        // Courir specific UI logic may not heavily depend on explicit spatie permissions, 
        // but let's give an empty or basic permission if needed.
        
        $customerRole = Role::firstOrCreate(['name' => 'customer']);

        // Create a default Super Admin user if not exists
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@citycourier.com'],
            [
                'name' => 'Super Admin',
                'phone' => '00000000000',
                'password' => bcrypt('password123'),
                // The 'role' column acts as a fallback or display, 
                // but we also rely on Spatie Roles now.
                'role' => 'admin', 
            ]
        );

        $adminUser->assignRole('super-admin');
    }
}
