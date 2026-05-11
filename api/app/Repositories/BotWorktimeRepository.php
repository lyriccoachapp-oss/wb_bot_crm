<?php

namespace App\Repositories;

use App\Models\BotWorktime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Repository для учёта рабочего времени
 *
 * Читает и записывает в таблицу bot_wp_worktime.
 */
class BotWorktimeRepository
{
	/**
	 * Получить записи рабочего времени с фильтрами
	 *
	 * @param  array $filters  [telegram_id, place_id, date_from, date_to]
	 * @param  int   $page
	 * @param  int   $limit
	 * @return LengthAwarePaginator
	 */
	public function paginate(array $filters = [], int $page = 1, int $limit = 20): LengthAwarePaginator
	{
		$query = BotWorktime::with(['place', 'botUser']);

		$this->applyFilters($query, $filters);

		return $query->orderBy('workday', 'desc')
			->orderBy('id_worktime', 'desc')
			->paginate($limit, ['*'], 'page', $page);
	}

	/**
	 * Получить запись за текущий день для пользователя
	 *
	 * @param  int    $telegramId
	 * @param  string $date       Y-m-d
	 * @return BotWorktime|null
	 */
	public function findTodayActiveEntry(int $telegramId, string $date): ?BotWorktime
	{
		return BotWorktime::where('id_telegram', $telegramId)
			->where('workday', $date)
			->where(function (Builder $query) {
				$query->whereNull('checkout')
					  ->orWhere('checkout', '0000-00-00 00:00:00');
			})
			->orderBy('id_worktime', 'desc')
			->first();
	}

	/**
	 * Найти запись по ID
	 */
	public function findById(int $id): ?BotWorktime
	{
		return BotWorktime::with(['place', 'botUser'])->find($id);
	}

	/**
	 * Создать запись рабочего дня (check-in)
	 *
	 * @param  array $data
	 * @return BotWorktime
	 */
	public function create(array $data): BotWorktime
	{
		return BotWorktime::create($data);
	}

	/**
	 * Обновить запись (checkout, обед, локация и т.д.)
	 *
	 * @param  BotWorktime $entry
	 * @param  array       $data
	 * @return BotWorktime
	 */
	public function update(BotWorktime $entry, array $data): BotWorktime
	{
		$entry->update($data);

		return $entry->fresh(['place', 'botUser']);
	}

	/**
	 * Получить данные для отчёта по объекту за период
	 *
	 * @param  int    $placeId
	 * @param  string $dateFrom Y-m-d
	 * @param  string $dateTo   Y-m-d
	 * @return Collection
	 */
	public function getByPlaceAndPeriod(int $placeId, string $dateFrom, string $dateTo): Collection
	{
		return BotWorktime::where('id_place', $placeId)
			->whereBetween('workday', [$dateFrom, $dateTo])
			->orderBy('workday')
			->orderBy('id_telegram')
			->get();
	}

	/**
	 * Получить данные за период для отчёта по времени
	 *
	 * @param  array $filters
	 * @return Collection
	 */
	public function getForReport(array $filters = []): Collection
	{
		$query = BotWorktime::with(['place', 'botUser']);

		$this->applyFilters($query, $filters);

		return $query->orderBy('workday')
			->orderBy('id_telegram')
			->get();
	}

	/**
	 * Применить фильтры к запросу
	 *
	 * @param  Builder $query
	 * @param  array   $filters
	 */
	private function applyFilters(Builder $query, array $filters): void
	{
		if (!empty($filters['telegram_id'])) {
			if (is_array($filters['telegram_id'])) {
				$query->whereIn('id_telegram', $filters['telegram_id']);
			} else {
				$query->where('id_telegram', $filters['telegram_id']);
			}
		}

		if (!empty($filters['place_id'])) {
			if (is_array($filters['place_id'])) {
				$query->whereIn('id_place', $filters['place_id']);
			} else {
				$query->where('id_place', $filters['place_id']);
			}
		}

		if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
			$query->where(function (Builder $q) use ($filters) {
				$q->where(function (Builder $sub) use ($filters) {
					if (!empty($filters['date_from'])) {
						$sub->where('workday', '>=', $filters['date_from']);
					}
					if (!empty($filters['date_to'])) {
						$sub->where('workday', '<=', $filters['date_to']);
					}
				});

				if (!empty($filters['include_open'])) {
					$q->orWhere(function (Builder $openSub) {
						$openSub->whereNull('checkout')
							  ->orWhere('checkout', '0000-00-00 00:00:00');
					});
				}
			});
		}
	}
}
