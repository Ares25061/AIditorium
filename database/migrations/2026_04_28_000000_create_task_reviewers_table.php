<?php

use App\Enums\Roles;
use App\Enums\TaskPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_reviewers', function (Blueprint $table) {
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->timestamps();

            $table->primary(['task_id', 'user_id']);
        });

        $permissionId = DB::table('permissions')->updateOrInsert(
            ['name' => TaskPermissions::REVIEW_SUBMISSIONS->value],
            ['updated_at' => now(), 'created_at' => now()],
        );

        $permissionId = DB::table('permissions')
            ->where('name', TaskPermissions::REVIEW_SUBMISSIONS->value)
            ->value('id');
        $adminRoleId = DB::table('roles')
            ->where('name', Roles::ADMIN->value)
            ->value('id');

        if ($permissionId && $adminRoleId) {
            DB::table('permission_role')->updateOrInsert(
                [
                    'permission_id' => $permissionId,
                    'role_id' => $adminRoleId,
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reviewers');

        $permissionId = DB::table('permissions')
            ->where('name', TaskPermissions::REVIEW_SUBMISSIONS->value)
            ->value('id');

        if ($permissionId) {
            DB::table('permission_role')
                ->where('permission_id', $permissionId)
                ->delete();
            DB::table('permissions')
                ->where('id', $permissionId)
                ->delete();
        }
    }
};
