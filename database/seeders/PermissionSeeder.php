<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define modules and their actions
        $modules = [
            'employees' => ['view', 'create', 'edit', 'delete'],
            'roles' => ['view', 'create', 'edit', 'delete'],
            'permissions' => ['view', 'create', 'edit', 'delete'],
        ];

        $createdPermissions = [];

        // Create permissions for each module
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $permission = Permission::firstOrCreate([
                    'name' => $action.'_'.$module,
                    'guard_name' => 'web',
                ]);
                $createdPermissions[] = $permission;
            }
        }

        $this->command->info('Permissions created successfully!');

        // Create a default "Administrator" role with all permissions
        $adminRole = Role::firstOrCreate([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);

        // Assign all permissions to Administrator role
        $adminRole->syncPermissions($createdPermissions);

        $this->command->info('Administrator role created with all permissions!');
    }
}
