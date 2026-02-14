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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignID('course_id')
                ->constrained(table: 'courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('name');
            $table->string('description')
                ->nullable();
            $table->timestamps();
            $table->integer('scores');
            $table->dateTime('deadline')
                ->nullable();
            $table->enum('status', ['active', 'closed'])
                ->default('active');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
