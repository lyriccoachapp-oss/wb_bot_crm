<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            if (!Schema::hasColumn('bot_users', 'company_slug')) {
                $table->string('company_slug', 50)->nullable()->after('role_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            if (Schema::hasColumn('bot_users', 'company_slug')) {
                $table->dropColumn('company_slug');
            }
        });
    }
};
