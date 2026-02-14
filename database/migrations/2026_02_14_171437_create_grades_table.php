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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignID('user_id')
                ->constrained(table: 'users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignID('course_id')
                ->constrained(table: 'courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignID('task_id')
                ->constrained(table: 'tasks')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->enum('type',['teacher','student','AI'])->default('teacher');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
