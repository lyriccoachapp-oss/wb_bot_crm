<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция: создание таблиц CRM
 *
 * Создаёт новые таблицы для CRM-системы.
 * Таблицы bot_* не затрагиваются.
 */
return new class extends Migration
{
	/**
	 * Выполнить миграцию
	 */
	public function up(): void
	{
		// Добавляем поля для авторизации в bot_users
		Schema::table('bot_users', function (Blueprint $table) {
			$table->string('password', 255)->nullable()->comment('Пароль для Web-админки');
			$table->rememberToken();
		});

		// JWT refresh-токены (привязываем к bot_users)
		Schema::create('crm_tokens', function (Blueprint $table) {
			$table->id();
			$table->unsignedInteger('user_id')->comment('ID пользователя из bot_users');
			$table->string('token_hash', 255)->unique()->comment('Хэш refresh-токена');
			$table->timestamp('expires_at')->comment('Срок действия токена');
			$table->string('ip', 45)->nullable()->comment('IP адрес');
			$table->string('user_agent', 500)->nullable()->comment('User-Agent');
			$table->timestamps();

			$table->foreign('user_id')->references('id')->on('bot_users')->onDelete('cascade');
			$table->index('token_hash');
			$table->index('expires_at');
		});

		// Сброс пароля
		Schema::create('crm_password_resets', function (Blueprint $table) {
			$table->id();
			$table->string('email', 255)->comment('Email пользователя');
			$table->string('token_hash', 255)->unique()->comment('Хэш токена сброса');
			$table->timestamp('expires_at')->comment('Срок действия токена');
			$table->timestamps();

			$table->index('email');
			$table->index('token_hash');
		});
	}

	/**
	 * Откатить миграцию
	 */
	public function down(): void
	{
		Schema::dropIfExists('crm_password_resets');
		Schema::dropIfExists('crm_tokens');
		
		Schema::table('bot_users', function (Blueprint $table) {
			$table->dropColumn(['password', 'remember_token']);
		});
	}
};
