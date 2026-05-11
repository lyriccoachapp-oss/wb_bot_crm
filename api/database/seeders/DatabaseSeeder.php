<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\BotUser;

/**
 * Сидер: выдаем главному администратору пароль
 */
class DatabaseSeeder extends Seeder
{
	/**
	 * Заполнить базу начальными данными
	 */
	public function run(): void
	{
		$this->call([
            ContentBlockSeeder::class,
            CompanySeeder::class,
        ]);

		$admin = BotUser::find(1);

		if ($admin) {
			$admin->update([
				'password' => Hash::make('Admin@2026!'),
			]);
			$this->command->info("Пароль для {$admin->email} установлен (Admin@2026!).");
		} else {
			$this->command->warn('Пользователь с ID=1 не найден в bot_users.');
		}
	}
}
