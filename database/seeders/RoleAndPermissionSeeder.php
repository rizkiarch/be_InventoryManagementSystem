<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['superadmin', 'admin', 'user'];
        $permissions = [
            [
                'name' => '*.create',
                'guard_name' => 'web',
                'description' => 'Create'
            ],
            [
                'name' => '*.read',
                'guard_name' => 'web',
                'description' => 'Read'
            ],
            [
                'name' => '*.update',
                'guard_name' => 'web',
                'description' => 'update'
            ],
            [
                'name' => '*.delete',
                'guard_name' => 'web',
                'description' => 'delete'
            ],
            [
                'name' => '*.import',
                'guard_name' => 'web',
                'description' => 'import'
            ],
            [
                'name' => '*.export',
                'guard_name' => 'web',
                'description' => 'export'
            ],
            [
                'name' => '*.print',
                'guard_name' => 'web',
                'description' => 'print'
            ],
            [
                'name' => '*.upload',
                'guard_name' => 'web',
                'description' => 'upload'
            ],
            [
                'name' => '*.download',
                'guard_name' => 'web',
                'description' => 'download'
            ]
        ];

        foreach ($permissions as $permission) {
            $permission = Permission::updateOrCreate($permission);
        }

        foreach ($roles as $nameRole) {
            $role = Role::firstOrCreate(['name' => $nameRole]);

            if ($nameRole === 'superadmin') {
                $role->givePermissionTo(Permission::all());
            } elseif ($nameRole === 'admin') {
                $role->givePermissionTo(['*.create', '*.read', '*.update']);
            } else {
                $role->givePermissionTo(['*.create', '*.read']);
            }
        }
    }
}
