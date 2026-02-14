<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('courses_users', function (Blueprint $table) {
            $table->foreignID('user_id')
                ->constrained(table: 'users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignID('course_id')
                ->constrained(table: 'courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->enum('role', ['teacher', 'student'])->default('student'); // У нас будет система, в которой будет глобальная роль, а также роль в конкретном курсе
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses_users');
    }
};
