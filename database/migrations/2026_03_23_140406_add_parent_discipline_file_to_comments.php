<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {

            $table->foreignId('parent_id')
                ->nullable()
                ->after('task_id')
                ->constrained('comments')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('discipline_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('disciplines')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('file_id')
                ->nullable()
                ->after('discipline_id')
                ->constrained('files')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->boolean('is_edited')
                ->default(false)
                ->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
            $table->dropForeign(['discipline_id']);
            $table->dropColumn('discipline_id');
            $table->dropForeign(['file_id']);
            $table->dropColumn('file_id');
            $table->dropColumn('is_edited');
        });
    }
};
