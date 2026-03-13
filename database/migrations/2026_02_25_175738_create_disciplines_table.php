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
        Schema::create('disciplines', function (Blueprint $table) {
            $table->id();
            $table->foreignID('course_id')
                ->constrained(table: 'courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('name');
            $table->integer('hours')
                ->nullable();
            $table->string('slug')
                ->nullable();
            $table->longText('description')
                ->nullable();
            $table->integer('discipline_number');
            $table->foreignID('created_by')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->timestamps();
            $table->unique(['course_id', 'discipline_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disciplines');
    }
};
