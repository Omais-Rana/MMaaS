<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignAdminRole extends Command
{
    protected $signature = 'roles:assign-admin';

    protected $description = 'Assign Administrator role to all sub-admins';

    public function handle()
    {
        $role = Role::where('name', 'Administrator')->first();

        if (! $role) {
            $this->error('Administrator role not found. Run PermissionSeeder first.');

            return 1;
        }

        $subAdmins = User::where('role', 'subadmin')->get();

        foreach ($subAdmins as $subAdmin) {
            $subAdmin->assignRole($role);
            $this->info("Assigned Administrator role to: {$subAdmin->name}");
        }

        $this->info('Done! All sub-admins now have the Administrator role.');

        return 0;
    }
}
