<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_blocks', function (Blueprint $table) {
            $table->enum('access_level', ['public', 'auth', 'admin'])->default('public')->after('key');
        });
    }

    public function down(): void
    {
        Schema::table('content_blocks', function (Blueprint $table) {
            $table->dropColumn('access_level');
        });
    }
};
