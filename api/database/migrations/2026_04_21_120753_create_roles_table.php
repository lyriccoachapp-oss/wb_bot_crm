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
		Schema::create('crm_roles', function (Blueprint $table) {
			$table->id();
			$table->string('name')->comment('Название роли');
			$table->string('slug')->unique()->comment('Системное имя (например, admin)');
			$table->json('permissions')->nullable()->comment('Массив прав (например, ["users.view", "objects.manage"])');
			$table->timestamps();
		});

		\Illuminate\Support\Facades\DB::table('crm_roles')->insert([
			[
				'name' => 'Администратор',
				'slug' => 'admin',
				'permissions' => json_encode(['users.view', 'users.manage', 'objects.view', 'objects.manage', 'receipts.manage', 'receipts.view_all', 'reports.view', 'time_entries.manage', 'roles.manage']),
				'created_at' => now(),
				'updated_at' => now(),
			],
			[
				'name' => 'Пользователь',
				'slug' => 'user',
				'permissions' => json_encode([]),
				'created_at' => now(),
				'updated_at' => now(),
			]
		]);

		Schema::table('bot_users', function (Blueprint $table) {
			$table->unsignedBigInteger('role_id')->nullable()->after('admin')->comment('ID роли (связь с crm_roles)');
			$table->foreign('role_id')->references('id')->on('crm_roles')->onDelete('set null');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('bot_users', function (Blueprint $table) {
			$table->dropForeign(['role_id']);
			$table->dropColumn('role_id');
		});

		Schema::dropIfExists('crm_roles');
	}
};
