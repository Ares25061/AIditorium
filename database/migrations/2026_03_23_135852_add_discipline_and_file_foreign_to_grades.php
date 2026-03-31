<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->foreignId('discipline_id')
                ->nullable()
                ->after('task_id')
                ->constrained('disciplines')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('file_id')
                ->nullable()
                ->after('discipline_id')
                ->constrained('files')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->integer('grade')
                ->nullable()
                ->after('file_id');

            $table->foreignId('graded_by')
                ->nullable()
                ->after('grade')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->timestamp('graded_at')
                ->nullable()
                ->after('graded_by');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropForeign(['discipline_id']);
            $table->dropColumn('discipline_id');
            $table->dropForeign(['file_id']);
            $table->dropColumn('file_id');
            $table->dropColumn('grade');
            $table->dropForeign(['graded_by']);
            $table->dropColumn('graded_by');
            $table->dropColumn('graded_at');
        });
    }
};

