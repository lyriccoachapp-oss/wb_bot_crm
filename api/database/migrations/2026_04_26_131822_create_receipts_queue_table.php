<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
	public function up(): void
	{
		Schema::create('receipts_queue', function (Blueprint $table) {
			$table->id();
			$table->unsignedInteger('user_id')->comment('ID пользователя из bot_users (автор загрузки)');
			$table->string('original_filename', 500)->nullable();
			$table->string('gdrive_id', 255)->nullable()->comment('ID файла на Google Drive');
			$table->string('status', 50)->default('pending')->comment('pending, processing, ready, failed');
			$table->text('error_message')->nullable();
			$table->json('parsed_data')->nullable()->comment('Спарсенные данные из GPT');
			$table->string('global_company', 255)->nullable();
			$table->string('global_employee', 255)->nullable();
			$table->string('global_object', 255)->nullable();
			$table->timestamps();

			$table->foreign('user_id')->references('id')->on('bot_users')->onDelete('cascade');
			$table->index('status');
		});
	}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts_queue');
    }
};
