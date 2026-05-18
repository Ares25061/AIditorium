<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_review_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('task_review_profiles', 'ai_model_key')) {
                $table->string('ai_model_key')->nullable()->after('supported_formats_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_review_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('task_review_profiles', 'ai_model_key')) {
                $table->dropColumn('ai_model_key');
            }
        });
    }
};
