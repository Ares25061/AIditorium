<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peer_review_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->unique()
                ->constrained('tasks')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('mode')->default('blind');
            $table->unsignedTinyInteger('reviews_per_student')->default(2);
            $table->boolean('allow_score')->default(true);
            $table->text('instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('peer_review_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('assignment_key')->nullable();
            $table->foreignId('course_id')
                ->constrained('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('discipline_id')
                ->constrained('disciplines')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('reviewer_id')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('target_user_id')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('file_id')
                ->constrained('files')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->boolean('blind')->default(true);
            $table->boolean('allow_score')->default(true);
            $table->unsignedInteger('max_score')->default(100);
            $table->text('instructions')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'reviewer_id', 'target_user_id', 'file_id'], 'peer_assignments_unique_pair');
            $table->index(['reviewer_id', 'task_id']);
            $table->index(['target_user_id', 'task_id']);
        });

        Schema::create('peer_review_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')
                ->unique()
                ->constrained('peer_review_assignments')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('reviewer_id')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('target_user_id')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('file_id')
                ->constrained('files')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->decimal('grade', 8, 2)->nullable();
            $table->text('comment');
            $table->timestamps();

            $table->index(['task_id', 'target_user_id']);
            $table->index(['reviewer_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_review_results');
        Schema::dropIfExists('peer_review_assignments');
        Schema::dropIfExists('peer_review_settings');
    }
};
