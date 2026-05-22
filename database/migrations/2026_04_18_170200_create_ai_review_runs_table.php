<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_review_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')
                ->constrained('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('discipline_id')
                ->nullable()
                ->constrained('disciplines')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('file_id')
                ->constrained('files')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('student_id')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('requested_by')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('status')->default('queued');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->json('criteria_snapshot_json')->nullable();
            $table->json('extracted_artifacts_json')->nullable();
            $table->json('result_json')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedTinyInteger('recommended_score')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'file_id']);
            $table->index(['task_id', 'student_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_review_runs');
    }
};
