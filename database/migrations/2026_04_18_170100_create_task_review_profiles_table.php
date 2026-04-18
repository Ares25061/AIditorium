<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_review_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->unique()
                ->constrained('tasks')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->boolean('enabled')->default(false);
            $table->json('rubric_json')->nullable();
            $table->longText('custom_prompt')->nullable();
            $table->json('supported_formats_json')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_review_profiles');
    }
};
