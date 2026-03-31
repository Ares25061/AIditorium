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
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignID('discipline_id')
                ->constrained(table: 'disciplines')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->integer('task_number');
            $table->unique(['discipline_id', 'task_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discipline_id');
        });
        Schema::dropIfExists('tasks');
    }
};
