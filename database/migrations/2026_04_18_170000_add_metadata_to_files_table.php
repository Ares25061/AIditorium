<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('path');
            $table->string('mime_type')->nullable()->after('original_name');
            $table->string('extension', 32)->nullable()->after('mime_type');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('extension');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'mime_type', 'extension', 'size_bytes']);
        });
    }
};
