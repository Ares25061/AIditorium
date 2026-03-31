<?php

use App\Enums\StatusCourseEnum;
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
            $table->longText('description')
                ->nullable();
            $table->string('status')
                ->default(StatusCourseEnum::ACTIVE->value);
            $table->boolean('is_closed')
                ->default(false);
            $table->string('slug')
                ->nullable();
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
