<?php

namespace App\Repositories;

use App\Models\BotUser;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository для пользователей бота (bot_users)
 *
 * Отвечает за операции с пользователями.
 */
class BotUserRepository
{
	/**
	 * Найти пользователя бота по email
	 */
	public function findByEmail(string $email): ?BotUser
	{
		return BotUser::where('email', $email)->first();
	}

	/**
	 * Найти пользователя по telegram_id
	 */
	public function findByTelegramId(int $telegramId): ?BotUser
	{
		return BotUser::where('id_telegram', $telegramId)->first();
	}

	/**
	 * Найти пользователя по ID
	 */
	public function findById(int $id): ?BotUser
	{
		return BotUser::find($id);
	}

	/**
	 * Обновить пользователя
	 */
	public function update(BotUser $user, array $data): BotUser
	{
		$data['date_upd'] = date('Y-m-d H:i:s');
		$user->update($data);
		return $user->fresh();
	}

	/**
	 * Получить список пользователей с пагинацией
	 */
	public function paginate(int $page = 1, int $limit = 20, array $filters = [], array $sort = []): LengthAwarePaginator
	{
		$query = BotUser::with(['role', 'company']);

		if (!empty($filters['status'])) {
			$query->where('status', $filters['status']);
		}
		if (!empty($filters['company_slug'])) {
			if ($filters['company_slug'] === '__none__') {
				$query->whereNull('company_slug');
			} else {
				$query->where('company_slug', $filters['company_slug']);
			}
		}

		if (!empty($filters['search'])) {
			$s = '%' . $filters['search'] . '%';
			$query->where(function($q) use ($s) {
				$q->where('firstname', 'LIKE', $s)
				  ->orWhere('lastname', 'LIKE', $s)
				  ->orWhere('email', 'LIKE', $s)
				  ->orWhere('username', 'LIKE', $s);
			});
		}

		if (!empty($sort['by'])) {
			$dir = (!empty($sort['dir']) && strtolower($sort['dir']) === 'desc') ? 'desc' : 'asc';
			$query->orderBy($sort['by'], $dir);
		} else {
			$query->orderBy('lastname')->orderBy('firstname');
		}

		return $query->paginate($limit, ['*'], 'page', $page);
	}

	/**
	 * Получить список зарегистрированных сотрудников (для справочника OCR)
	 */
	public function getEmployeesList(): array
	{
		return BotUser::whereIn('status', ['registred', 'работает', 'обед'])
			->orderBy('lastname')
			->get(['id_telegram', 'firstname', 'lastname'])
			->map(fn ($u) => [
				'id_telegram' => $u->id_telegram,
				'name'        => trim($u->firstname . ' ' . $u->lastname),
			])
			->toArray();
	}
}
