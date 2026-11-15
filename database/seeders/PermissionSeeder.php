<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        $permissions = [

            // Users Page
            'user-list',
            'user-create',
            'user-edit',
            'user-view',
            'user-delete',
            'user-viewAll',

            // Roles Page
            'role-list',
            'role-create',
            'role-edit',
            'role-delete',

            // Permissions Page
            'permission-list',

            // Companies Page
            'company-list',
            'company-create',
            'company-edit',
            'company-delete',

            // Branches Page
            'branch-list',
            'branch-create',
            'branch-edit',
            'branch-delete',

            // Contact Page
            'contact-list',
            'contact-delete',
            
            // Subscribers Page
            'subscriber-list',
            'subscriber-delete',
            


        ];
        
        $permissionsIds = [];
        foreach ($permissions as $permission) {
            $createdPermission = Permission::updateOrCreate(['name' => $permission]);
            $permissionsIds[] = $createdPermission->id;
        }

        $adminRole = Role::find(1);
        if($adminRole!=null){
            $adminRole->syncPermissions($permissionsIds);
        }
    }
}
