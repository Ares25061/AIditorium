<?php

namespace Database\Seeders;

use App\Enums\CoursePermissions;
use App\Enums\DisciplinePermissions;
use App\Enums\FilePermissions;
use App\Enums\RolePermissions;
use App\Enums\Roles;
use App\Enums\TaskPermissions;
use App\Enums\UserPermissions;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
        $taskPermissions = TaskPermissions::values();
        $disciplinePermissions = DisciplinePermissions::values();
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
        foreach ($taskPermissions as $taskPermission) {
            $permissions[] = Permission::create(['name' => $taskPermission]);
        }
        foreach ($disciplinePermissions as $disciplinePermission) {
            $permissions[] = Permission::create(['name' => $disciplinePermission]);
        }
        $adminRole = Role::create(['name' => Roles::ADMIN->value]);
        $adminRole->permissions()->sync($permissions);
        Role::create(['name' => Roles::USER->value]);
        User::create(['name'=> 'admin', 'email'=> 'admin@gmail.com','password'=> bcrypt('12345678'), 'role_id'=>$adminRole->id]);
    }
}
