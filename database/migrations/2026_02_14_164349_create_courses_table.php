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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id') // ID создавшего курс, автоматически должен получить роль учителя в курсе
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('invite_code') // Код приглашения
                ->unique();
            $table->string('invite_code_teacher') // Код приглашения для учителей, если человек введет этот код, он станет учителем
                ->unique()
                ->nullable();
            $table->string('background_logo') // Фон курса
                ->nullable();
            $table->longText('description')
                ->nullable();
            $table->enum('status', ['active', 'archive']) // Учителя будут не удалять курсы, а переносить в архив
                ->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
