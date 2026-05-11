<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('bot_users', 'reset_password_validity')) {
            DB::statement("ALTER TABLE bot_users CHANGE reset_password_validity reset_password_validity VARCHAR(50) NULL DEFAULT NULL COMMENT 'Stores user selected emoji avatar'");
        } else {
            Schema::table('bot_users', function (Blueprint $table) {
                $table->string('reset_password_validity', 50)->nullable()->comment('Stores user selected emoji avatar');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            //
        });
    }
};
