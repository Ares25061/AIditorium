<?php

namespace Database\Seeders;

use App\CoursePermissions;
use App\FilePermissions;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Roles;
use App\UserPermissions;
use App\RolePermissions;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userPermissions = UserPermissions::values();
        $rolePermissions = RolePermissions::values();
        $filePermissions = FilePermissions::values();
        $coursePermissions = CoursePermissions::values();
        $permissions = [];
        foreach ($userPermissions as $userPermission) {
            $permissions[] = Permission::create(['name' => $userPermission]);
        }
        foreach ($rolePermissions as $rolePermission) {
            $permissions[] = Permission::create(['name' => $rolePermission]);
        }
        foreach ($filePermissions as $filePermission) {
            $permissions[] = Permission::create(['name' => $filePermission]);
        }
        foreach ($coursePermissions as $coursePermission) {
            $permissions[] = Permission::create(['name' => $coursePermission]);
        }
        $adminRole = Role::create(['name' => Roles::ADMIN->value]);
        $adminRole->permissions()->sync($permissions);
        Role::create(['name' => Roles::USER->value]);
        User::create(['name'=> 'admin', 'email'=> 'admin@gmail.com','password'=> bcrypt('12345678'), 'role_id'=>$adminRole->id]);
    }
}
